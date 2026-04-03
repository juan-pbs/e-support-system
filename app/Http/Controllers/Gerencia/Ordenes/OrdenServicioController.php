<?php

namespace App\Http\Controllers\Gerencia\Ordenes;

use App\Http\Controllers\Controller;
use App\Exports\Ordenes\OrdenesServicioExport;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CreditoCliente;
use App\Models\DetalleOrdenProducto;
use App\Models\DetalleOrdenProductoSerie;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Services\Ordenes\OrdenServicioService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class OrdenServicioController extends Controller
{
    public function __construct(private OrdenServicioService $svc) {}

    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $ordenId = $request->input('orden_id');
        $estado  = $request->input('estado');
        $tipo    = $request->input('tipo_orden');
        $facturado = $request->input('facturado');
        $tecnicoId = $request->input('tecnico_id');

        $ordenes = OrdenServicio::query()
            ->with(['cliente', 'tecnico', 'tecnicos'])
            ->when($estado, fn($w) => $w->where('estado', $estado))
            ->when($tipo, fn($w) => $w->where('tipo_orden', $tipo))
            ->when(
                Schema::hasColumn('orden_servicio', 'facturado') && in_array((string) $facturado, ['0', '1'], true),
                fn($w) => $w->where('facturado', (int) $facturado)
            )
            ->when($tecnicoId, fn($w) => $w->where(fn($tq) => $tq->whereHas('tecnicos', fn($tt) => $tt->where('users.id', (int) $tecnicoId))->orWhere('id_tecnico', (int) $tecnicoId)))
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

        $tecnicos = User::where('puesto', 'tecnico')->orderBy('name')->get(['id', 'name']);

        return view('gerencia.ordenes.index', compact('ordenes', 'tecnicos'));
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);

        $desde = Carbon::parse($data['desde'])->startOfDay();
        $hasta = Carbon::parse($data['hasta'])->endOfDay();

        $fechaCol = Schema::hasColumn('orden_servicio', 'fecha_orden')
            ? 'fecha_orden'
            : 'created_at';

        $ordenes = OrdenServicio::query()
            ->with(['cliente', 'tecnico', 'tecnicos', 'productos'])
            ->when($fechaCol === 'fecha_orden', function ($query) use ($desde, $hasta) {
                $query
                    ->whereDate('fecha_orden', '>=', $desde->toDateString())
                    ->whereDate('fecha_orden', '<=', $hasta->toDateString());
            })
            ->when($fechaCol !== 'fecha_orden', function ($query) use ($fechaCol, $desde, $hasta) {
                $query->whereBetween($fechaCol, [$desde, $hasta]);
            })
            ->orderBy($fechaCol)
            ->orderBy('id_orden_servicio')
            ->get();

        $filename = sprintf(
            'ordenes_servicio_%s_a_%s.xlsx',
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d')
        );

        return Excel::download(
            new OrdenesServicioExport($ordenes, $desde, $hasta),
            $filename
        );
    }

    public function updateFacturacion(Request $request, $id)
    {
        if (! Schema::hasColumn('orden_servicio', 'facturado')) {
            return back()->with('error', 'La columna de facturacion no esta disponible en esta instalacion.');
        }

        $data = $request->validate([
            'facturado' => ['required', 'boolean'],
        ]);

        $orden = OrdenServicio::findOrFail($id);
        $orden->facturado = (bool) $data['facturado'];
        $orden->save();

        return back()->with('success', 'Estado de facturacion actualizado correctamente.');
    }

    public function create()
    {
        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();
        $data['firmaFromCotizacion'] = false;

        return view('gerencia.ordenes.create', $data + ['firma' => $firma]);
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

        return view('gerencia.ordenes.edit', $data + [
            'firma'            => $firma,
            'orden'            => $orden,
            'productosPrefill' => $productosPrefill,
        ]);
    }

    public function store(Request $request)
    {
        $data  = $this->svc->validateOrden($request, false);
        $token = !empty($data['serial_token']) ? (string)$data['serial_token'] : null;

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

                $productos = $data['productos'] ?? [];
                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productos, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden($orden, $productosConsumidos, $orden->moneda ?? 'MXN');
                }

                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int)$orden->getKey());
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

                $orden->precio_escrito = $this->svc->resolvePrecioEscrito(
                    $data['precio_escrito'] ?? ($orden->precio_escrito ?? null),
                    (float) ($totales['total'] ?? 0),
                    (string) ($orden->moneda ?? 'MXN')
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
            if ($token) {
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {}
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

                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int)$orden->getKey());
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

                $orden->precio_escrito = $this->svc->resolvePrecioEscrito(
                    $data['precio_escrito'] ?? ($cotizacion->cantidad_escrita ?? ($orden->precio_escrito ?? null)),
                    (float) ($totales['total'] ?? 0),
                    (string) ($orden->moneda ?? 'MXN')
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
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {}
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

        $oldSnapshot = [
            'tipo_pago'       => (string) $orden->tipo_pago,
            'id_cliente'      => $orden->id_cliente,
            'moneda'          => strtoupper((string) ($orden->moneda ?? 'MXN')),
            'tasa_cambio'     => (float) ($orden->tasa_cambio ?? 1),
            'saldo_pendiente' => (float) ($orden->saldo_pendiente ?? 0),
        ];

        try {
            DB::transaction(function () use ($orden, $data, $request, $token, $oldSnapshot) {
                // ✅ 1. liberar seriales asignados por ESTA orden
                $this->svc->deleteAssignedSeriesBySource('orden_servicio', (int) $orden->getKey());

                // ✅ 2. borrar detalles anteriores
                $detIds = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
                    ->pluck('id_orden_producto');

                if ($detIds->isNotEmpty()) {
                    DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)->delete();
                }

                DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->delete();

                // ✅ 3. validar de nuevo con el token actual
                $check = $this->svc->preflightStockCheck($data['productos'] ?? [], $token);
                $this->svc->failIfShortage($check);

                // ✅ 4. actualizar cabecera
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

                // ✅ 5. rehacer líneas con el MISMO flujo que store()
                $productos = $data['productos'] ?? [];
                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productos, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden($orden, $productosConsumidos, $orden->moneda ?? 'MXN');
                }

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

                $orden->precio_escrito = $this->svc->resolvePrecioEscrito(
                    $data['precio_escrito'] ?? ($orden->precio_escrito ?? null),
                    (float) ($totales['total'] ?? 0),
                    (string) ($orden->moneda ?? 'MXN')
                );

                $anticipoInfo = $this->svc->applyAnticipoToOrden($orden, $data, $totales);
                $orden->save();

                // ✅ Revertir crédito anterior si aplicaba
                if (($oldSnapshot['tipo_pago'] ?? null) === 'credito_cliente') {
                    $saldoOldMxn = (float) ($oldSnapshot['saldo_pendiente'] ?? 0);
                    if (($oldSnapshot['moneda'] ?? 'MXN') === 'USD') {
                        $tc = (float) ($oldSnapshot['tasa_cambio'] ?? 1);
                        $saldoOldMxn = $tc > 0 ? round($saldoOldMxn * $tc, 2) : round($saldoOldMxn, 2);
                    }

                    if ($saldoOldMxn > 0) {
                        $creditoOld = CreditoCliente::where('clave_cliente', $oldSnapshot['id_cliente'])
                            ->lockForUpdate()
                            ->first();

                        if ($creditoOld) {
                            $creditoOld->monto_usado = max(round((float) $creditoOld->monto_usado - $saldoOldMxn, 2), 0);
                            $creditoOld->save();
                        }
                    }
                }

                // ✅ Aplicar crédito nuevo si corresponde
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
            });
        } catch (\Throwable $e) {
            if ($token) {
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {}
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

        return redirect()->route('ordenes.index')->with('success', 'Orden actualizada correctamente.');
    }

    public function destroy($id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede eliminarse.');
        }

        DB::transaction(function () use ($orden) {
            $oldTipoPago = (string) $orden->tipo_pago;
            $oldCliente  = $orden->id_cliente;
            $oldMoneda   = strtoupper((string) ($orden->moneda ?? 'MXN'));
            $oldTc       = (float) ($orden->tasa_cambio ?? 1);
            $oldSaldo    = (float) ($orden->saldo_pendiente ?? 0);

            $detIds = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
                ->pluck('id_orden_producto');

            if ($detIds->isNotEmpty()) {
                DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)->delete();
            }

            DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->delete();

            $this->svc->deleteAssignedSeriesBySource('orden_servicio', (int) $orden->getKey());

            if ($oldTipoPago === 'credito_cliente' && $oldSaldo > 0) {
                $saldoOldMxn = $oldSaldo;
                if ($oldMoneda === 'USD') {
                    $saldoOldMxn = $oldTc > 0 ? round($oldSaldo * $oldTc, 2) : round($oldSaldo, 2);
                }

                $credito = CreditoCliente::where('clave_cliente', $oldCliente)
                    ->lockForUpdate()
                    ->first();

                if ($credito) {
                    $credito->monto_usado = max(round((float) $credito->monto_usado - $saldoOldMxn, 2), 0);
                    $credito->save();
                }
            }

            if (!empty($orden->id_cotizacion)) {
                $cot = Cotizacion::find($orden->id_cotizacion);
                if ($cot && (property_exists($cot, 'orden_servicio_id') || isset($cot->orden_servicio_id))) {
                    $cot->orden_servicio_id = null;
                    $cot->save();
                }
            }

            if (!empty($orden->archivo_pdf) && \Storage::disk('public')->exists($orden->archivo_pdf)) {
                \Storage::disk('public')->delete($orden->archivo_pdf);
            }

            $orden->tecnicos()->sync([]);
            $orden->delete();
        });

        return redirect()->route('ordenes.index')->with('success', 'Orden eliminada correctamente.');
    }

    public function createDesdeCotizacion($id)
    {
        $cotizacion = Cotizacion::with(['cliente', 'productos', 'servicio'])->findOrFail($id);

        if ($cotizacion->vigencia && Carbon::parse($cotizacion->vigencia)->isPast()) {
            return redirect()->route('cotizaciones.vista')
                ->with('error', 'No se puede generar una orden desde una cotización vencida.');
        }

        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        $productosPrefill = collect($cotizacion->productos ?? [])->map(function ($p) {
            return [
                'codigo_producto' => $p->codigo_producto,
                'nombre_producto' => $p->nombre_producto,
                'descripcion'     => (string)($p->descripcion_item ?? ''),
                'cantidad'        => (float)($p->cantidad ?? 1),
                'precio'          => (float)($p->precio_unitario ?? 0),
                'ns_asignados'    => [],
                'has_serial'      => false,
            ];
        })->values()->toArray();

        $data['firmaFromCotizacion'] = false;

        return view('gerencia.ordenes.create', $data + [
            'firma'            => $firma,
            'cotizacion'       => $cotizacion,
            'productosPrefill' => $productosPrefill,
        ]);
    }

}
