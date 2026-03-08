<?php

namespace App\Http\Controllers;

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

    /* ==================== Crear ==================== */

    public function create()
    {
        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        $data['firmaFromCotizacion'] = false;
        $data['serialToken']         = $this->makeSerialToken('ord-create');

        return view('vistas-gerente.orden-servicio.create', $data + [
            'firma' => $firma,
        ]);
    }

    public function edit($id)
    {
        $orden = OrdenServicio::with(['cliente', 'tecnico', 'tecnicos'])->findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return redirect()
                ->route('ordenes.index')
                ->with('error', 'La orden está cerrada por acta firmada y no puede modificarse.');
        }

        $data  = $this->svc->commonFormData();
        $firma = $this->svc->getFirma();

        $detalles   = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->get();
        $seriesMap  = [];
        $detalleIds = $detalles->pluck('id_orden_producto')->filter();

        if ($detalleIds->isNotEmpty()) {
            $rows = DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detalleIds)
                ->get(['id_orden_producto', 'numero_serie']);

            $rows->groupBy('id_orden_producto')->each(function ($col, $k) use (&$seriesMap) {
                $seriesMap[(int) $k] = $col->pluck('numero_serie')->filter()->values()->toArray();
            });
        }

        $productosPrefill = $detalles->map(function ($d) use ($seriesMap) {
            $serials   = $seriesMap[(int) $d->id_orden_producto] ?? [];
            $codigo    = $d->codigo_producto ? (int) $d->codigo_producto : null;
            $hasSerial = !empty($serials);
            $stock     = null;

            if ($codigo) {
                try {
                    $stock = $this->svc->calculateAvailableForProduct($codigo);
                } catch (\Throwable $e) {
                    $stock = null;
                }
            }

            $qty = $hasSerial ? count($serials) : (float) ($d->cantidad ?? 0);

            return [
                'codigo_producto'  => $codigo ?: null,
                'nombre_producto'  => $d->nombre_producto ?? null,
                'descripcion'      => (string) ($d->descripcion ?? ''),
                'cantidad'         => $qty,
                'precio'           => (float) ($d->precio_unitario ?? 0),
                'ns_asignados'     => $serials,
                'stock_disponible' => $stock,
                'stock'            => $stock,
                'disponible'       => $stock,
                'stock_max'        => $stock,
                'faltante'         => 0,
                'sin_stock'        => false,
                'has_serial'       => $hasSerial,
            ];
        })->values()->toArray();

        $data['firmaFromCotizacion'] = false;
        $data['serialToken']         = $this->makeSerialToken('ord-edit-' . $orden->getKey());

        return view('vistas-gerente.orden-servicio.edit', $data + [
            'firma'            => $firma,
            'orden'            => $orden,
            'productosPrefill' => $productosPrefill,
        ]);
    }

    /* ==================== Guardar manual ==================== */

    public function store(Request $request)
    {
        $data  = $this->svc->validateOrden($request, false);
        $token = $this->resolveToken($data);

        $check = $this->svc->preflightStockCheck($data['productos'] ?? [], $token);
        $this->svc->failIfShortage($check);

        $ordenId      = null;
        $payloadCodes = $this->collectCodigosFromPayload($data['productos'] ?? []);

        try {
            DB::transaction(function () use ($data, $request, $token, &$ordenId) {
                $orden = new OrdenServicio();

                $this->svc->fillOrden($orden, $data);
                $orden->id_cotizacion  = null;
                $orden->autorizado_por = auth()->id();

                $this->svc->handleUploads($orden, $request);
                $orden->save();

                $this->syncTecnicosCompat($orden, $data);

                $productos = $data['productos'] ?? [];
                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productos, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden($orden, $productosConsumidos, $orden->moneda ?? 'MXN');
                }

                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int) $orden->getKey());
                }

                $adicional = (float) ($orden->total_adicional ?? 0);

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

                $this->applyCreditoIfNeeded($orden, $anticipoInfo);

                $ordenId = (int) $orden->getKey();
            });
        } catch (\Throwable $e) {
            if ($token) {
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {
                    // noop
                }
            }

            throw $e;
        }

        $this->refreshCodigos($payloadCodes);

        if (!$ordenId) {
            throw new HttpResponseException(response()->json([
                'message' => 'No se pudo generar la orden (ID nulo).',
            ], 500));
        }

        $this->svc->generarYGuardarPdfOrden($ordenId);

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

        return redirect()
            ->route('ordenes.index')
            ->with('success', 'Orden de servicio creada correctamente.');
    }

    /* ==================== Guardar desde cotización ==================== */

    public function guardarDesdeCotizacion(Request $request)
    {
        $data  = $this->svc->validateOrden($request, true);
        $token = $this->resolveToken($data);

        $cotizacion = Cotizacion::with(['productos', 'servicio'])->findOrFail($data['cotizacion_id']);

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

        $ordenId      = null;
        $payloadCodes = $this->collectCodigosFromPayload($productosBase);

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

                $this->syncTecnicosCompat($orden, $data);

                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productosBase, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden(
                        $orden,
                        $productosConsumidos,
                        $cotizacion->moneda ?? ($orden->moneda ?? 'MXN')
                    );
                }

                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int) $orden->getKey());
                }

                $adicional = (float) ($orden->total_adicional ?? 0);

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

                $this->applyCreditoIfNeeded($orden, $anticipoInfo);

                $cotizacion->estado_cotizacion = $data['estado_cotizacion'] ?? 'procesada';
                $cotizacion->process_count     = (int) ($cotizacion->process_count ?? 0) + 1;
                $cotizacion->last_processed_at = Carbon::now();

                if (property_exists($cotizacion, 'orden_servicio_id') || isset($cotizacion->orden_servicio_id)) {
                    $cotizacion->orden_servicio_id = $orden->getKey();
                }

                $cotizacion->save();

                $ordenId = (int) $orden->getKey();
            });
        } catch (\Throwable $e) {
            if ($token) {
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {
                    // noop
                }
            }

            throw $e;
        }

        $this->refreshCodigos($payloadCodes);

        if (!$ordenId) {
            throw new HttpResponseException(response()->json([
                'message' => 'No se pudo generar la orden (ID nulo).',
            ], 500));
        }

        $this->svc->generarYGuardarPdfOrden($ordenId);

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

        return redirect()
            ->route('ordenes.index')
            ->with('success', 'Orden de servicio creada desde cotización correctamente.');
    }

    /* ==================== Otros CRUD ==================== */

    public function show($id)
    {
        return redirect()->route('ordenes.index');
    }

    public function update(Request $request, $id)
    {
        $orden = OrdenServicio::with(['cliente', 'tecnicos'])->findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede modificarse.');
        }

        $data  = $this->svc->validateOrden($request, !empty($orden->id_cotizacion));
        $token = $this->resolveToken($data);

        $snapshotFinanciero = $this->snapshotOrden($orden);
        $oldCodes           = $this->collectCodigosFromOrder($orden);
        $newCodes           = $this->collectCodigosFromPayload($data['productos'] ?? []);

        try {
            DB::transaction(function () use ($orden, $request, $data, $token, $snapshotFinanciero) {
                $this->svc->fillOrden($orden, $data);
                $this->svc->handleUploads($orden, $request);
                $orden->save();

                $this->syncTecnicosCompat($orden, $data);

                $this->svc->deleteAssignedSeriesBySource('orden_servicio', (int) $orden->getKey());

                $this->deleteDetalleRows($orden);

                $productos = $data['productos'] ?? [];
                $check     = $this->svc->preflightStockCheck($productos, $token);
                $this->svc->failIfShortage($check);

                $productosConsumidos = $this->svc->consumeAndPrepareLineItems($productos, $token);

                if (!empty($productosConsumidos)) {
                    $this->svc->insertDetallesOrden($orden, $productosConsumidos, $orden->moneda ?? 'MXN');
                }

                if ($token) {
                    $this->svc->finalizeSeries($token, 'orden_servicio', (int) $orden->getKey());
                }

                $adicional = (float) ($orden->total_adicional ?? 0);

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

                $this->releaseCreditoFromSnapshot($snapshotFinanciero);
                $this->applyCreditoIfNeeded($orden, $anticipoInfo);
            });
        } catch (\Throwable $e) {
            if ($token) {
                try {
                    $this->svc->releaseSeries($token);
                } catch (\Throwable $t) {
                    // noop
                }
            }

            throw $e;
        }

        $this->refreshCodigos(array_merge($oldCodes, $newCodes));
        $this->svc->generarYGuardarPdfOrden((int) $orden->getKey());

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'           => true,
                'id'           => (int) $orden->getKey(),
                'pdf_url'      => route('ordenes.pdf', ['id' => $orden->getKey()]),
                'download_url' => route('ordenes.pdf', ['id' => $orden->getKey(), 'download' => 1]),
                'redirect'     => route('ordenes.index'),
                'message'      => 'Orden actualizada correctamente.',
            ]);
        }

        return redirect()
            ->route('ordenes.index')
            ->with('success', 'Orden de servicio actualizada correctamente.');
    }

    public function destroy($id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if ($orden->acta_estado === 'firmada') {
            return back()->with('error', 'La orden está cerrada por acta firmada y no puede eliminarse.');
        }

        $snapshotFinanciero = $this->snapshotOrden($orden);
        $oldCodes           = $this->collectCodigosFromOrder($orden);

        DB::transaction(function () use ($orden, $snapshotFinanciero) {
            $this->deleteDetalleRows($orden);

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

            $this->svc->deleteAssignedSeriesBySource('orden_servicio', (int) $orden->getKey());
            $this->releaseCreditoFromSnapshot($snapshotFinanciero);
            $this->svc->deleteArchivoPdfIfExists($orden);

            $orden->delete();
        });

        $this->refreshCodigos($oldCodes);

        return redirect()
            ->route('ordenes.index')
            ->with('success', 'Orden eliminada correctamente.');
    }

    /* ==================== PDF ==================== */

    public function pdf(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if (empty($orden->archivo_pdf) || !\Storage::disk('public')->exists($orden->archivo_pdf)) {
            $this->svc->generarYGuardarPdfOrden((int) $orden->getKey());
            $orden->refresh();
        }

        $download = $request->boolean('download');
        $filename = 'orden_servicio_' . $orden->getKey() . '.pdf';

        return $this->svc->responsePublicPdf($orden->archivo_pdf, $filename, $download);
    }

    /* ==================== Seguimiento simple ==================== */

    public function agregarSeguimiento(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        $request->validate([
            'observaciones' => ['required', 'string', 'max:5000'],
        ]);

        return back()->with('success', 'Seguimiento registrado (pendiente de unificar con el flujo principal de seguimiento).');
    }

    /* ==================== Helpers internos ==================== */

    protected function syncTecnicosCompat(OrdenServicio $orden, array $data): void
    {
        if (!empty($data['tecnicos_ids']) && is_array($data['tecnicos_ids'])) {
            $ids = collect($data['tecnicos_ids'])
                ->filter(fn($v) => !blank($v))
                ->map(fn($v) => (int) $v)
                ->unique()
                ->values()
                ->all();

            $orden->tecnicos()->sync($ids);
            $orden->id_tecnico = $ids[0] ?? null;
            $orden->save();

            return;
        }

        if (!empty($data['id_tecnico'])) {
            $id = (int) $data['id_tecnico'];
            $orden->tecnicos()->sync([$id]);
            $orden->id_tecnico = $id;
            $orden->save();

            return;
        }

        $orden->tecnicos()->sync([]);
        $orden->id_tecnico = null;
        $orden->save();
    }

    protected function deleteDetalleRows(OrdenServicio $orden): void
    {
        $detIds = DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
            ->pluck('id_orden_producto');

        if ($detIds->isNotEmpty()) {
            DetalleOrdenProductoSerie::whereIn('id_orden_producto', $detIds)->delete();
        }

        DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())->delete();
    }

    protected function resolveToken(array $data): string
    {
        $token = trim((string) ($data['serial_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        return $this->makeSerialToken('ord');
    }

    protected function makeSerialToken(string $prefix = 'ord'): string
    {
        return $prefix . '-' . Str::uuid()->toString();
    }

    protected function snapshotOrden(OrdenServicio $orden): array
    {
        $orden->refresh();

        return [
            'id_cliente'      => $orden->id_cliente,
            'tipo_pago'       => (string) $orden->tipo_pago,
            'moneda'          => strtoupper((string) ($orden->moneda ?? 'MXN')),
            'tasa_cambio'     => (float) ($orden->tasa_cambio ?? 1),
            'saldo_pendiente' => (float) ($orden->saldo_pendiente ?? 0),
        ];
    }

    protected function saldoToMxn(float $saldo, string $moneda, float $tc): float
    {
        $saldo = max($saldo, 0);

        if (strtoupper($moneda) === 'USD') {
            return $tc > 0 ? round($saldo * $tc, 2) : round($saldo, 2);
        }

        return round($saldo, 2);
    }

    protected function releaseCreditoFromSnapshot(array $snapshot): void
    {
        if (($snapshot['tipo_pago'] ?? null) !== 'credito_cliente') {
            return;
        }

        $clienteId = $snapshot['id_cliente'] ?? null;
        if (!$clienteId) {
            return;
        }

        $montoMxn = $this->saldoToMxn(
            (float) ($snapshot['saldo_pendiente'] ?? 0),
            (string) ($snapshot['moneda'] ?? 'MXN'),
            (float) ($snapshot['tasa_cambio'] ?? 1)
        );

        if ($montoMxn <= 0) {
            return;
        }

        $credito = CreditoCliente::where('clave_cliente', $clienteId)->lockForUpdate()->first();
        if (!$credito) {
            return;
        }

        $credito->monto_usado = max(round((float) $credito->monto_usado - $montoMxn, 2), 0);
        $credito->save();
    }

    protected function applyCreditoIfNeeded(OrdenServicio $orden, array $anticipoInfo): void
    {
        if ((string) $orden->tipo_pago !== 'credito_cliente') {
            return;
        }

        $importeParaCreditoMXN = (float) ($anticipoInfo['saldo_mxn'] ?? 0);

        if ($importeParaCreditoMXN <= 0) {
            return;
        }

        if (strtoupper((string) $orden->moneda) === 'USD' && (float) $orden->tasa_cambio <= 0) {
            throw new HttpResponseException(response()->json([
                'message' => 'Tipo de cambio inválido para usar crédito en USD.',
                'errors'  => [
                    'tasa_cambio' => ['Tipo de cambio inválido.'],
                ],
            ], 422));
        }

        $credito = CreditoCliente::where('clave_cliente', $orden->id_cliente)
            ->lockForUpdate()
            ->first();

        if (!$credito) {
            throw new HttpResponseException(response()->json([
                'message' => 'El cliente no tiene línea de crédito asignada.',
                'errors'  => [
                    'tipo_pago' => ['Cliente sin línea de crédito.'],
                ],
            ], 422));
        }

        $venc = $this->svc->checkCreditoVencido($credito);
        if (($venc['expired'] ?? false) === true) {
            throw new HttpResponseException(response()->json([
                'message' => 'El crédito del cliente está vencido. No es posible usarlo para esta orden.',
                'errors'  => [
                    'tipo_pago' => ['Crédito vencido.'],
                ],
            ], 422));
        }

        $disponible = max((float) $credito->monto_maximo - (float) $credito->monto_usado, 0);

        if ($importeParaCreditoMXN > $disponible) {
            throw new HttpResponseException(response()->json([
                'message' => 'Crédito insuficiente para cubrir el saldo pendiente de la orden.',
                'errors'  => [
                    'tipo_pago' => ['Crédito insuficiente.'],
                ],
            ], 422));
        }

        $credito->monto_usado = round((float) $credito->monto_usado + $importeParaCreditoMXN, 2);
        $credito->save();
    }

    protected function collectCodigosFromPayload(array $productos): array
    {
        return collect($productos)
            ->pluck('codigo_producto')
            ->filter(fn($v) => !blank($v))
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    protected function collectCodigosFromOrder(OrdenServicio $orden): array
    {
        return DetalleOrdenProducto::where('id_orden_servicio', $orden->getKey())
            ->pluck('codigo_producto')
            ->filter(fn($v) => !blank($v))
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    protected function refreshCodigos(array $codigos): void
    {
        $codigos = collect($codigos)
            ->filter(fn($v) => !blank($v))
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        foreach ($codigos as $codigo) {
            try {
                $this->svc->refreshProductStockTotals((int) $codigo);
            } catch (\Throwable $e) {
                // noop
            }
        }
    }
}