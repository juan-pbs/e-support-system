<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

use App\Models\User;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\OrdenServicio;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\CreditoCliente;

use App\Services\Ordenes\OrdenServicioService;

class OrdenServicioController extends Controller
{
    public function __construct(private OrdenServicioService $svc) {}

    /* ==================== Listado ==================== */

    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $ordenId = $request->input('orden_id');
        $estado  = $request->input('estado');
        $tipo    = $request->input('tipo_orden');

        $ordenes = OrdenServicio::query()
            ->with(['cliente', 'tecnico', 'tecnicos'])
            ->when($estado, fn($w) => $w->where('estado', $estado))
            ->when($tipo, fn($w) => $w->where('tipo_orden', $tipo))
            ->when($ordenId, fn($w) => $w->whereKey($ordenId))
            ->when(!$ordenId && $q !== '', function ($w) use ($q) {
                $like = "%{$q}%";
                $num  = preg_replace('/\D+/', '', $q);

                $w->where(function ($sub) use ($like, $num) {
                    if ($num !== '') {
                        $sub->orWhere('id_orden_servicio', (int) $num);
                    }

                    $sub->orWhere('tipo_orden', 'like', $like)
                        ->orWhere('prioridad', 'like', $like)
                        ->orWhere('estado', 'like', $like)
                        ->orWhereHas('cliente', function ($c) use ($like) {
                            $c->where('nombre', 'like', $like)
                                ->orWhere('nombre_empresa', 'like', $like);
                        });

                    if (Schema::hasColumn('orden_servicio', 'servicio')) {
                        $sub->orWhere('servicio', 'like', $like);
                    }
                    if (Schema::hasColumn('orden_servicio', 'descripcion')) {
                        $sub->orWhere('descripcion', 'like', $like);
                    }
                    if (Schema::hasColumn('orden_servicio', 'descripcion_servicio')) {
                        $sub->orWhere('descripcion_servicio', 'like', $like);
                    }
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('vistas-gerente.orden-servicio.index', compact('ordenes'));
    }

    public function create()
    {
        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        $data['firmaFromCotizacion'] = false;

        return view('vistas-gerente.orden-servicio.create', $data + ['firma' => $firma]);
    }

    /**
     * ✅ Vista de edición
     * - Replica la vista de create
     * - Precarga TODOS los datos de la orden (incluyendo N/S en cada línea)
     */
    public function edit($id)
    {
        $orden = OrdenServicio::with(['cliente', 'tecnico', 'tecnicos'])->findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return redirect()->route('ordenes.index')
                ->with('error', 'La orden está cerrada por acta firmada y no puede modificarse.');
        }

        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        // ===== Detalles + seriales (para que se vean en la tabla) =====
        $detalles = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->get();

        $seriesMap = [];
        $detIds = $detalles->pluck('id_orden_producto')->filter();
        if ($detIds->isNotEmpty()) {
            $rows = DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)
                ->get(['id_orden_producto', 'numero_serie']);

            $rows->groupBy('id_orden_producto')->each(function ($col, $k) use (&$seriesMap) {
                $seriesMap[(int)$k] = $col->pluck('numero_serie')->filter()->values()->toArray();
            });
        }

        $productosPrefill = $detalles->map(function ($d) use ($seriesMap) {
            $serials = $seriesMap[(int)$d->id_orden_producto] ?? [];

            $codigo = $d->codigo_producto ? (int)$d->codigo_producto : null;
            $hasSerial = !empty($serials);

            // stock disponible para mostrar (no bloquea por N/S asignados; solo informativo)
            $stock = null;
            if ($codigo) {
                try {
                    $stock = $this->svc->calculateAvailableForProduct($codigo);
                } catch (\Throwable $e) {
                    $stock = null;
                }
            }

            $qty = $hasSerial ? count($serials) : (float)($d->cantidad ?? 0);

            return [
                'codigo_producto'   => $codigo ?: null,
                'nombre_producto'   => $d->nombre_producto ?? null,
                'descripcion'       => (string)($d->descripcion ?? ''),
                'cantidad'          => $qty,
                'precio'            => (float)($d->precio_unitario ?? 0),
                'ns_asignados'      => $serials,
                'stock_disponible'  => $stock,
                'stock'             => $stock,
                'disponible'        => $stock,
                'stock_max'         => $stock,
                'faltante'          => 0,
                'sin_stock'         => false,
                'has_serial'        => $hasSerial,
            ];
        })->values()->toArray();

        $data['firmaFromCotizacion'] = false;

        return view('vistas-gerente.orden-servicio.edit', $data + [
            'firma'            => $firma,
            'orden'            => $orden,
            'productosPrefill' => $productosPrefill,
        ]);
    }

    /* ==================== Guardar (manual) ==================== */

    public function store(Request $request)
    {
        $data  = $this->svc->validateOrden($request, false);
        $token = !empty($data['serial_token']) ? (string)$data['serial_token'] : null;

        // ✅ Stock check (considera reservas por token)
        $check = $this->svc->preflightStockCheck($data['productos'] ?? [], $token);
        $this->svc->failIfShortage($check);

        $ordenId = null;

        try {
            DB::transaction(function () use ($data, $request, $token, &$ordenId) {
                $orden = new OrdenServicio();
                $this->svc->fillOrden($orden, $data);

                $orden->id_cotizacion  = null;
                $orden->autorizado_por = auth()->id();

                $this->svc->handleUploads($orden, $request);
                $orden->save();

                if (!empty($data['tecnicos_ids'])) {
                    $orden->tecnicos()->sync($data['tecnicos_ids']);
                } elseif (!empty($data['id_tecnico'])) {
                    $orden->tecnicos()->sync([$data['id_tecnico']]);
                } else {
                    $orden->tecnicos()->sync([]);
                }

                $productos           = $data['productos'] ?? [];
                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productos, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden($orden, $productosConsumidos, $orden->moneda ?? 'MXN');
                }

                // ✅ Finalizar reservas de N/S (marcar como asignado para auditoría)
                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int) $orden->getKey());
                }

                $adicional = 0.0;
                try {
                    $adicional = (float) $orden->total_adicional;
                } catch (\Throwable $e) {
                    $adicional = 0.0;
                }

                $this->svc->recalcularYGuardarImpuestos(
                    $orden,
                    $productosConsumidos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $totales = $this->svc->calculateTotals(
                    $productosConsumidos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $anticipoInfo = $this->svc->applyAnticipoToOrden($orden, $data, $totales);
                $orden->save();

                if ((string) $orden->tipo_pago === 'credito_cliente') {

                    $importeParaCreditoMXN = (float) ($anticipoInfo['saldo_mxn'] ?? 0);
                    if ($importeParaCreditoMXN <= 0) {
                        $ordenId = $orden->getKey();
                        return;
                    }

                    if (strtoupper((string) $orden->moneda) === 'USD' && (float) $orden->tasa_cambio <= 0) {
                        throw new HttpResponseException(response()->json([
                            'message' => 'Tipo de cambio inválido para usar crédito en USD.',
                            'errors'  => ['tasa_cambio' => ['Tipo de cambio inválido.']],
                        ], 422));
                    }

                    $credito = CreditoCliente::where('clave_cliente', $orden->id_cliente)
                        ->lockForUpdate()
                        ->first();

                    if (!$credito) {
                        throw new HttpResponseException(response()->json([
                            'message' => 'El cliente no tiene línea de crédito asignada.',
                            'errors'  => ['tipo_pago' => ['Cliente sin línea de crédito.']],
                        ], 422));
                    }

                    $venc = $this->svc->checkCreditoVencido($credito);
                    if ($venc['expired'] === true) {
                        throw new HttpResponseException(response()->json([
                            'message' => 'El crédito del cliente está vencido. No es posible usarlo para esta orden.',
                            'errors'  => ['tipo_pago' => ['Crédito vencido.']],
                        ], 422));
                    }

                    $disponible = max((float) $credito->monto_maximo - (float) $credito->monto_usado, 0);

                    if ($importeParaCreditoMXN > $disponible) {
                        throw new HttpResponseException(response()->json([
                            'message' => 'Crédito insuficiente para cubrir el saldo pendiente de la orden.',
                            'errors'  => ['tipo_pago' => ['Crédito insuficiente.']],
                        ], 422));
                    }

                    $credito->monto_usado = round((float) $credito->monto_usado + $importeParaCreditoMXN, 2);
                    $credito->save();
                }

                $ordenId = $orden->getKey();
            });
        } catch (\Throwable $e) {
            // ✅ Robustez: si algo falla, liberar reservas del token (no asignadas) para no “atorar” N/S.
            if ($token) {
                try { $this->svc->releaseSeries($token); } catch (\Throwable $t) {}
            }
            throw $e;
        }

        if (!$ordenId) {
            throw new HttpResponseException(response()->json([
                'message' => 'No se pudo generar la orden (ID nulo).',
            ], 500));
        }

        $this->svc->generarYGuardarPdfOrden((int) $ordenId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'           => true,
                'id'           => $ordenId,
                'pdf_url'      => route('ordenes.pdf', ['id' => $ordenId]),
                'download_url' => route('ordenes.pdf', ['id' => $ordenId, 'download' => 1]),
                'redirect'     => route('ordenes.index'),
                'message'      => 'Orden guardada correctamente.',
            ]);
        }

        return redirect()->route('ordenes.index')->with('success', 'Orden de servicio creada correctamente.');
    }

    /* ==================== Guardar desde cotización ==================== */

    public function guardarDesdeCotizacion(Request $request)
    {
        $data  = $this->svc->validateOrden($request, true);
        $token = !empty($data['serial_token']) ? (string)$data['serial_token'] : null;

        $cotizacion = Cotizacion::with(['productos', 'servicio'])
            ->findOrFail($data['cotizacion_id']);

        $productosBase = $data['productos'] ?? [];

        if (empty($productosBase) && $cotizacion->productos && $cotizacion->productos->count()) {
            $productosBase = $cotizacion->productos->map(function ($d) {
                return [
                    'codigo_producto' => $d->codigo_producto ?? null,
                    'descripcion'     => $d->descripcion_item ?? ($d->nombre_producto ?? ''),
                    'nombre_producto' => $d->nombre_producto ?? null,
                    'cantidad'        => (int) ($d->cantidad ?? 1),
                    'precio'          => (float) ($d->precio_unitario ?? 0),
                ];
            })->values()->toArray();
        }

        $check = $this->svc->preflightStockCheck($productosBase, $token);
        $this->svc->failIfShortage($check);

        $ordenId = null;

        try {
            DB::transaction(function () use ($data, $request, $token, &$ordenId, $cotizacion, $productosBase) {

                if (!empty($cotizacion->orden_servicio_id)) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Esta cotización ya tiene una orden de servicio vinculada.',
                    ], 422));
                }

                $orden = new OrdenServicio();
                $this->svc->fillOrden($orden, $data);

                $orden->precio               = $orden->precio ?? (optional($cotizacion->servicio)->precio ?? 0);
                $orden->costo_operativo      = $orden->costo_operativo ?? ($cotizacion->costo_operativo ?? 0);
                $orden->descripcion_servicio = $orden->descripcion_servicio ?? (optional($cotizacion->servicio)->descripcion);

                if (!$orden->servicio && $cotizacion->servicio) {
                    $orden->servicio = 'Servicio cotizado';
                }

                $orden->id_cotizacion  = $cotizacion->getKey();
                $orden->autorizado_por = auth()->id();

                $this->svc->handleUploads($orden, $request);
                $orden->save();

                if (!empty($data['tecnicos_ids'])) {
                    $orden->tecnicos()->sync($data['tecnicos_ids']);
                } elseif (!empty($data['id_tecnico'])) {
                    $orden->tecnicos()->sync([$data['id_tecnico']]);
                } else {
                    $orden->tecnicos()->sync([]);
                }

                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productosBase, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden(
                        $orden,
                        $productosConsumidos,
                        $cotizacion->moneda ?? ($orden->moneda ?? 'MXN')
                    );
                }

                // ✅ Finalizar reservas
                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int) $orden->getKey());
                }

                $adicional = 0.0;
                try {
                    $adicional = (float) $orden->total_adicional;
                } catch (\Throwable $e) {
                    $adicional = 0.0;
                }

                $this->svc->recalcularYGuardarImpuestos(
                    $orden,
                    $productosConsumidos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $totales = $this->svc->calculateTotals(
                    $productosConsumidos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $anticipoInfo = $this->svc->applyAnticipoToOrden($orden, $data, $totales);
                $orden->save();

                if ((string) $orden->tipo_pago === 'credito_cliente') {

                    $importeParaCreditoMXN = (float) ($anticipoInfo['saldo_mxn'] ?? 0);

                    if ($importeParaCreditoMXN > 0) {

                        if (strtoupper((string) $orden->moneda) === 'USD' && (float) $orden->tasa_cambio <= 0) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'Tipo de cambio inválido para usar crédito en USD.',
                                'errors'  => ['tasa_cambio' => ['Tipo de cambio inválido.']],
                            ], 422));
                        }

                        $credito = CreditoCliente::where('clave_cliente', $orden->id_cliente)
                            ->lockForUpdate()
                            ->first();

                        if (!$credito) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'El cliente no tiene línea de crédito asignada.',
                                'errors'  => ['tipo_pago' => ['Cliente sin línea de crédito.']],
                            ], 422));
                        }

                        $venc = $this->svc->checkCreditoVencido($credito);
                        if ($venc['expired'] === true) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'El crédito del cliente está vencido. No es posible usarlo para esta orden.',
                                'errors'  => ['tipo_pago' => ['Crédito vencido.']],
                            ], 422));
                        }

                        $disponible = max((float) $credito->monto_maximo - (float) $credito->monto_usado, 0);

                        if ($importeParaCreditoMXN > $disponible) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'Crédito insuficiente para cubrir el saldo pendiente de la orden.',
                                'errors'  => ['tipo_pago' => ['Crédito insuficiente.']],
                            ], 422));
                        }

                        $credito->monto_usado = round((float) $credito->monto_usado + $importeParaCreditoMXN, 2);
                        $credito->save();
                    }
                }

                $cotizacion->estado_cotizacion = $data['estado_cotizacion'] ?? 'procesada';
                $cotizacion->process_count     = (int) ($cotizacion->process_count ?? 0) + 1;
                $cotizacion->last_processed_at = Carbon::now();

                if (property_exists($cotizacion, 'orden_servicio_id') || isset($cotizacion->orden_servicio_id)) {
                    $cotizacion->orden_servicio_id = $orden->getKey();
                }

                $cotizacion->save();

                $ordenId = $orden->getKey();
            });
        } catch (\Throwable $e) {
            if ($token) {
                try { $this->svc->releaseSeries($token); } catch (\Throwable $t) {}
            }
            throw $e;
        }

        if (!$ordenId) {
            throw new HttpResponseException(response()->json([
                'message' => 'No se pudo generar la orden (ID nulo).',
            ], 500));
        }

        $this->svc->generarYGuardarPdfOrden((int) $ordenId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'           => true,
                'id'           => $ordenId,
                'redirect'     => route('ordenes.index'),
                'pdf_url'      => route('ordenes.pdf', ['id' => $ordenId]),
                'download_url' => route('ordenes.pdf', ['id' => $ordenId, 'download' => 1]),
                'message'      => 'Orden de servicio creada desde cotización correctamente.',
            ]);
        }

        return redirect()->route('ordenes.index')->with('success', 'Orden de servicio creada desde cotización correctamente.');
    }

    /* ==================== Otros CRUD ==================== */

    public function show($id)
    {
        return redirect()->route('ordenes.index');
    }

    public function update(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede modificarse.');
        }

        $data  = $this->svc->validateOrden($request, !empty($orden->id_cotizacion));
        $token = !empty($data['serial_token']) ? (string)$data['serial_token'] : null;

        $check = $this->svc->preflightStockCheck($data['productos'] ?? [], $token);
        $this->svc->failIfShortage($check);

        try {
            DB::transaction(function () use ($orden, $data, $request) {

                $this->svc->fillOrden($orden, $data);
                $this->svc->handleUploads($orden, $request);
                $orden->save();

                if (!empty($data['tecnicos_ids'])) {
                    $orden->tecnicos()->sync($data['tecnicos_ids']);
                } elseif (!empty($data['id_tecnico'])) {
                    $orden->tecnicos()->sync([$data['id_tecnico']]);
                } else {
                    $orden->tecnicos()->sync([]);
                }

                $productos = $data['productos'] ?? [];

                $detIds = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
                    ->pluck('id_orden_producto');

                if ($detIds->isNotEmpty()) {
                    DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)->delete();
                }

                DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->delete();

                if (!empty($productos)) {
                    // update NO consume inventario
                    $this->svc->insertDetallesOrden($orden, $productos, $orden->moneda ?? 'MXN');
                }

                $adicional = 0.0;
                try {
                    $adicional = (float) $orden->total_adicional;
                } catch (\Throwable $e) {
                    $adicional = 0.0;
                }

                $this->svc->recalcularYGuardarImpuestos(
                    $orden,
                    $productos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $totales = $this->svc->calculateTotals(
                    $productos,
                    $orden->precio,
                    $orden->costo_operativo,
                    $adicional
                );

                $this->svc->applyAnticipoToOrden($orden, $data, $totales);
                $orden->save();
            });
        } catch (\Throwable $e) {
            if ($token) {
                try { $this->svc->releaseSeries($token); } catch (\Throwable $t) {}
            }
            throw $e;
        }

        $this->svc->generarYGuardarPdfOrden((int) $orden->getKey());

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'           => true,
                'id'           => $orden->getKey(),
                'pdf_url'      => route('ordenes.pdf', ['id' => $orden->getKey()]),
                'download_url' => route('ordenes.pdf', ['id' => $orden->getKey(), 'download' => 1]),
                'redirect'     => route('ordenes.index'),
                'message'      => 'Orden actualizada correctamente.',
            ]);
        }

        return redirect()->route('ordenes.index')->with('success', 'Orden de servicio actualizada correctamente.');
    }

    public function destroy($id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede eliminarse.');
        }

        DB::transaction(function () use ($orden) {
            $detIds = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
                ->pluck('id_orden_producto');

            if ($detIds->isNotEmpty()) {
                DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)->delete();
            }

            DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->delete();

            if (!empty($orden->id_cotizacion)) {
                $cot = Cotizacion::find($orden->id_cotizacion);
                if ($cot) {
                    if (property_exists($cot, 'orden_servicio_id') || isset($cot->orden_servicio_id)) {
                        $cot->orden_servicio_id = null;
                    }
                    $cot->save();
                }
            }

            $orden->tecnicos()->sync([]);

            // ✅ Robustez: si la orden consumió N/S (SerieReserva->asignado), liberarlos al borrar.
            // (Deja inventario intacto; solo quita la marca de “asignado” para que vuelva a estar disponible)
            $this->svc->deleteAssignedSeriesBySource('orden_servicio', (int)$orden->getKey());

            $this->svc->deleteArchivoPdfIfExists($orden);

            $orden->delete();
        });

        return back()->with('success', 'Orden eliminada correctamente.');
    }

    /* ==================== Asignación ==================== */

    public function asignar(Request $request)
    {
        $ordenes = OrdenServicio::whereNull('id_tecnico')
            ->doesntHave('tecnicos')
            ->orderByDesc('created_at')
            ->paginate(10);

        $tecnicos = User::where('puesto', 'tecnico')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('vistas-gerente.orden-servicio.asignar', compact('ordenes', 'tecnicos'));
    }

    public function guardarAsignacion(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede re-asignarse.');
        }

        $data = $request->validate([
            'tecnicos_ids'   => ['nullable', 'array'],
            'tecnicos_ids.*' => ['integer', 'exists:users,id'],
            'id_tecnico'     => ['nullable', 'integer', 'exists:users,id'],
            'prioridad'      => ['nullable', 'in:Baja,Media,Alta,Urgente'],
        ]);

        $orden->id_tecnico = $data['id_tecnico'] ?? ($data['tecnicos_ids'][0] ?? null);

        if (!empty($data['prioridad'])) {
            $orden->prioridad = $data['prioridad'];
        }

        $orden->save();

        $orden->tecnicos()->sync(
            $data['tecnicos_ids'] ?? (!empty($data['id_tecnico']) ? [$data['id_tecnico']] : [])
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'redirect' => route('ordenes.index'),
                'message'  => 'Asignación actualizada.',
            ]);
        }

        return redirect()->route('seguimiento')->with('success', 'Asignación actualizada.');
    }

    public function agregarSeguimiento(Request $request, $id)
    {
        $request->validate([
            'descripcion' => ['required', 'string'],
            'estado'      => ['nullable', 'string'],
            'imagenes.*'  => ['nullable', 'image', 'max:4096'],
        ]);

        return back()->with('success', 'Seguimiento registrado (placeholder).');
    }

    /* ===================== autocomplete ===================== */

    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->get('term', ''));
        if (mb_strlen($term) < 2) return response()->json([]);

        $like = "%{$term}%";
        $num  = preg_replace('/\D+/', '', $term);

        $items = OrdenServicio::query()
            ->with(['cliente:clave_cliente,nombre,nombre_empresa'])
            ->where(function ($q) use ($like, $num) {
                if ($num !== '') {
                    $q->orWhere('id_orden_servicio', (int) $num);
                }

                $q->orWhereHas('cliente', function ($c) use ($like) {
                    $c->where('nombre', 'like', $like)
                        ->orWhere('nombre_empresa', 'like', $like);
                });

                if (Schema::hasColumn('orden_servicio', 'servicio')) {
                    $q->orWhere('servicio', 'like', $like);
                }
                if (Schema::hasColumn('orden_servicio', 'descripcion')) {
                    $q->orWhere('descripcion', 'like', $like);
                }
                if (Schema::hasColumn('orden_servicio', 'descripcion_servicio')) {
                    $q->orWhere('descripcion_servicio', 'like', $like);
                }
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($o) {
                $cliente = $o->cliente->nombre
                    ?? $o->cliente->nombre_empresa
                    ?? '—';

                $tipo  = $o->tipo_orden ?? '';
                $folio = $o->folio;

                return [
                    'id'    => $o->getKey(),
                    'label' => "{$folio} — {$cliente}" . ($tipo ? " — {$tipo}" : ''),
                ];
            });

        return response()->json($items);
    }

    public function crearDesdeCotizacion($id)
    {
        $cotizacion = Cotizacion::with(['cliente', 'productos', 'servicio'])->findOrFail($id);

        $map = [
            'venta'    => 'compra',
            'hibrido'  => 'servicio_simple',
            'servicio' => 'servicio_simple',
        ];
        $tipoOrdenSugerido = $map[$cotizacion->tipo_solicitud] ?? 'servicio_simple';

        $productosPrefill = ($cotizacion->productos ?? collect())->map(function ($d) use ($cotizacion) {
            $codigo = (int) ($d->codigo_producto ?? 0);

            $item = [
                'codigo_producto' => $codigo ?: null,
                'descripcion'     => $d->descripcion_item ?? ($d->nombre_producto ?? ''),
                'nombre_producto' => $d->nombre_producto ?? null,
                'cantidad'        => (int) ($d->cantidad ?? 1),
                'precio'          => (float) ($d->precio_unitario ?? 0),
                'moneda'          => $cotizacion->moneda ?? 'MXN',
            ];

            if ($codigo > 0) {
                $stock     = $this->svc->calculateAvailableForProduct($codigo);
                $hasSerial = $this->svc->productHasSerial($codigo);

                $item['stock_disponible'] = $stock;
                $item['stock']            = $stock;
                $item['disponible']       = $stock;
                $item['stock_max']        = $stock;
                $item['faltante']         = 0;
                $item['sin_stock']        = $stock <= 0;
                $item['has_serial']       = $hasSerial;
            } else {
                $item['stock_disponible'] = null;
                $item['stock']            = null;
                $item['disponible']       = null;
                $item['stock_max']        = null;
                $item['faltante']         = 0;
                $item['sin_stock']        = false;
                $item['has_serial']       = false;
            }

            return $item;
        })->values()->toArray();

        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        $data['firmaFromCotizacion']        = true;
        $data['cotizacion']                 = $cotizacion;
        $data['productosPrefill']           = $productosPrefill;
        $data['tipoOrdenSugerido']          = $tipoOrdenSugerido;
        $data['descripcionServicioPrefill'] = optional($cotizacion->servicio)->descripcion;

        return view('vistas-gerente.orden-servicio.create', $data + ['firma' => $firma]);
    }

    public function asignarVista($id)
    {
        $orden = OrdenServicio::with(['cliente', 'tecnicos', 'productos'])->findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return redirect()->route('seguimiento')->with('error', 'La orden está cerrada por acta firmada.');
        }

        $tecnicos    = User::where('puesto', 'tecnico')->orderBy('name')->get(['id', 'name']);
        $prioridades = ['Baja', 'Media', 'Alta', 'Urgente'];

        return view('vistas-gerente.orden-servicio.asignar-uno', compact('orden', 'tecnicos', 'prioridades'));
    }
}
