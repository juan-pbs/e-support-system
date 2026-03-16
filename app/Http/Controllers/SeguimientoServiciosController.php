<?php

namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use App\Models\SeguimientoServicio;
use App\Models\SeguimientoImagen;
use App\Models\DetalleOrdenProducto;
use App\Models\OrdenMaterialExtra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SeguimientoServiciosController extends Controller
{
    /**
     * Vista principal del reporte.
     */
    public function index()
    {
        return view('vistas-gerente.segimiento.seguimiento');
    }

    /**
     * Detecta el nombre real de la columna de total adicional (por compatibilidad).
     * Si no existe, regresa null.
     */
    private function totalAdicionalColumn(): ?string
    {
        $ordenTable = (new OrdenServicio())->getTable();

        if (Schema::hasColumn($ordenTable, 'total_adicional_mxn')) return 'total_adicional_mxn';

        // fallback por si en algún momento lo nombraste distinto:
        if (Schema::hasColumn($ordenTable, 'total_adicional')) return 'total_adicional';

        return null;
    }

    /**
     * ✅ Recalcula el total adicional (base MXN) desde orden_material_extra:
     * SOLO suma extras con precio_unitario NOT NULL.
     *
     * - SIEMPRE calcula y regresa el total aunque la columna no exista.
     * - Si la columna existe, la actualiza en orden_servicio.
     */
    private function syncTotalAdicionalMxn(int $ordenId): float
    {
        $extraTable = (new OrdenMaterialExtra())->getTable();

        if (!Schema::hasTable($extraTable)) {
            return 0.0;
        }

        $sum = (float) OrdenMaterialExtra::where('id_orden_servicio', $ordenId)
            ->whereNotNull('precio_unitario')
            ->selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0) as total')
            ->value('total');

        $sum = round($sum, 2);

        $col = $this->totalAdicionalColumn();
        if ($col) {
            OrdenServicio::where('id_orden_servicio', $ordenId)->update([
                $col => $sum,
            ]);
        }

        return $sum;
    }

    /**
     * Datos para la tabla + resumen (se consume vía JS).
     * GET /api/seguimiento-servicios
     */
    public function data(Request $request)
    {
        $estado     = $request->query('estado');
        $prioridad  = $request->query('prioridad');
        $monedaF    = $request->query('moneda');
        $tecnicoQ   = trim((string) $request->query('tecnico'));
        $desde      = $request->query('desde');
        $hasta      = $request->query('hasta');

        $hasSegTable   = Schema::hasTable('seguimiento_servicio');
        $hasExtraTable = Schema::hasTable((new OrdenMaterialExtra())->getTable());

        $with = ['cliente', 'tecnico', 'tecnicos', 'productos'];
        if ($hasSegTable)   $with[] = 'seguimientos';
        if ($hasExtraTable) $with[] = 'materialesExtras';

        $ordenTable    = (new OrdenServicio())->getTable();
        $hasFechaOrden = Schema::hasColumn($ordenTable, 'fecha_orden');
        $hasCreatedAt  = Schema::hasColumn($ordenTable, 'created_at');

        $colTotalAdic  = $this->totalAdicionalColumn(); // puede ser null

        $ordenesQuery = OrdenServicio::with($with);

        // Rango quincena
        if ($hasFechaOrden) {
            if ($desde) $ordenesQuery->whereDate('fecha_orden', '>=', $desde);
            if ($hasta) $ordenesQuery->whereDate('fecha_orden', '<=', $hasta);
        } elseif ($hasCreatedAt) {
            if ($desde) $ordenesQuery->whereDate('created_at', '>=', $desde);
            if ($hasta) $ordenesQuery->whereDate('created_at', '<=', $hasta);
        }

        $ordenesQuery->orderByDesc('id_orden_servicio');

        if ($prioridad && $prioridad !== 'all') {
            $ordenesQuery->where('prioridad', $prioridad);
        }

        if ($monedaF && $monedaF !== 'all') {
            $ordenesQuery->whereRaw('UPPER(moneda) = ?', [strtoupper($monedaF)]);
        }

        $ordenes = $ordenesQuery->get();

        $rows = $ordenes->map(function (OrdenServicio $o) use ($hasSegTable, $hasExtraTable, $colTotalAdic) {

            /* =========================
             * Comentario más reciente
             * ========================= */
            $coment = '';
            if ($hasSegTable && $o->relationLoaded('seguimientos')) {
                $seg = $o->seguimientos;

                $seg = Schema::hasColumn('seguimiento_servicio', 'created_at')
                    ? $seg->sortByDesc('created_at')->first()
                    : $seg->sortByDesc('id_seguimiento')->first();

                if ($seg) $coment = $seg->observaciones ?? $seg->comentarios ?? '';
            }

            /* =========================
             * Moneda y tipo de cambio
             * ========================= */
            $moneda = strtoupper(trim((string) ($o->moneda ?? 'MXN')));
            if ($moneda === '') $moneda = 'MXN';

            // Tipo de cambio (MXN por 1 USD)
            $tipoCambio = (float) ($o->tasa_cambio ?? 1.0);
            if ($tipoCambio <= 0) $tipoCambio = 1.0;

            /* ==========================================================
             * ✅ CÁLCULO IGUAL QUE EL PDF:
             *    Materiales(detalles) + Servicio(precio) = Subtotal gravable
             * ========================================================== */

            // 1) Materiales (detalles) en moneda de la orden
            $materialesOrden = 0.0;
            if ($o->relationLoaded('productos')) {
                $materialesOrden = (float) $o->productos->sum(function (DetalleOrdenProducto $d) {
                    $total = $d->total;
                    if ($total === null) $total = $d->subtotal;

                    // fallback si no existe total/subtotal (por compatibilidad)
                    if ($total === null) {
                        $cant = (float) ($d->cantidad ?? 0);
                        $pu   = (float) ($d->precio_unitario ?? $d->precio ?? 0);
                        $total = $cant * $pu;
                    }
                    return (float) $total;
                });
            }

            // 2) Servicio (precio) en moneda de la orden
            $servicioOrden = (float) ($o->precio ?? 0);

            // 3) Subtotal gravable (materiales + servicio)
            $subtotalGravable = $materialesOrden + $servicioOrden;

            // 4) Operativo e impuestos (ya vienen guardados en moneda de la orden)
            $costoOperativo = (float) ($o->costo_operativo ?? 0);
            $impuestos      = (float) ($o->impuestos ?? 0);

            /* =========================
             * Extras (base MXN)
             * ========================= */
            // 1) Intentamos usar campo guardado
            $additionalMxn = 0.0;
            if ($colTotalAdic) {
                $additionalMxn = (float) ($o->{$colTotalAdic} ?? 0);
            }

            // 2) Resumen y pendientes desde la relación
            $pendingCount  = 0;
            $pendingQty    = 0.0;
            $extrasResumen = '-';

            // 3) Si hay relación, recalculamos (y sincronizamos si está desfasado)
            if ($hasExtraTable && $o->relationLoaded('materialesExtras')) {
                $calc = 0.0;

                foreach ($o->materialesExtras as $e) {
                    $cant = (float) ($e->cantidad ?? 0);

                    if ($e->precio_unitario === null) {
                        $pendingCount++;
                        $pendingQty += $cant;
                        continue;
                    }

                    $calc += $cant * (float) $e->precio_unitario; // ✅ siempre MXN
                }

                $nombres = $o->materialesExtras->pluck('descripcion')->filter()->values();
                if ($nombres->isNotEmpty()) {
                    $extrasResumen = $nombres->take(3)->implode(', ');
                    if ($nombres->count() > 3) $extrasResumen .= '…';
                }

                $calc = round($calc, 2);

                // Mantener sincronizado
                if ($colTotalAdic) {
                    if (abs($calc - $additionalMxn) > 0.01) {
                        $additionalMxn = $calc;
                        OrdenServicio::where('id_orden_servicio', $o->id_orden_servicio)->update([
                            $colTotalAdic => $additionalMxn,
                        ]);
                    }
                } else {
                    $additionalMxn = $calc;
                }
            }

            // Total adicional en moneda de la orden
            $additional = ($moneda === 'USD') ? round($additionalMxn / $tipoCambio, 2) : round($additionalMxn, 2);

            /* ==========================================================
             * ✅ TOTAL FINAL (igual que PDF):
             *    (Materiales + Servicio) + Impuestos + Operativo + Extras
             * ========================================================== */
            $final = $subtotalGravable + $impuestos + $costoOperativo + $additional;

            /* =========================
             * Total final en MXN (para resumen global)
             * ========================= */
            $subtotalGravableMxn = ($moneda === 'USD') ? ($subtotalGravable * $tipoCambio) : $subtotalGravable;
            $costoOperativoMxn   = ($moneda === 'USD') ? ($costoOperativo * $tipoCambio)   : $costoOperativo;
            $impuestosMxn        = ($moneda === 'USD') ? ($impuestos * $tipoCambio)        : $impuestos;

            $finalEnMxn = $subtotalGravableMxn + $costoOperativoMxn + $impuestosMxn + $additionalMxn;

            /* =========================
             * Técnico y status
             * ========================= */
            $tec = trim((string) ($o->tecnicos_nombres ?? ''));
            if ($tec === '') $tec = optional($o->tecnico)->name ?? '—';

            $clienteNombre = trim((string) (
                optional($o->cliente)->nombre_empresa
                ?: optional($o->cliente)->nombre
                ?: ''
            ));
            if ($clienteNombre === '') $clienteNombre = '—';

            $estadoSeguimiento = $o->status_seguimiento ?? $this->statusSeguimientoFallback($o);

            return [
                'id'                  => $o->id_orden_servicio,
                'orderId'             => 'OS-' . $o->id_orden_servicio,
                'cliente'             => $clienteNombre,
                'client'              => $clienteNombre,
                'technician'          => $tec,
                'status'              => $estadoSeguimiento,
                'acta_estado'         => $o->acta_estado,
                'actaEstado'          => $o->acta_estado,
                'tipo_orden'          => $o->tipo_orden,
                'tipo'                => $o->tipo_orden,
                'prioridad'           => $o->prioridad,
                'priority'            => $o->prioridad,
                'comments'            => $coment,

                'unforeseeenMaterial' => $extrasResumen,
                'extrasPendingCount'  => (int) $pendingCount,
                'extrasPendingQty'    => round($pendingQty, 2),

                'additionalTotal'     => round($additional, 2),     // moneda orden
                'additionalTotalMxn'  => round($additionalMxn, 2),  // MXN base

                'currency'            => $moneda,
                'exchangeRate'        => $tipoCambio,
                'finalTotal'          => round($final, 2),
                'finalTotalMxn'       => round($finalEnMxn, 2),
            ];
        })->values();

        // filtros en memoria
        if ($estado && $estado !== 'all') {
            $rows = $rows->where('status', $estado)->values();
        }
        if ($prioridad && $prioridad !== 'all') {
            $rows = $rows->where('priority', $prioridad)->values();
        }
        if ($monedaF && $monedaF !== 'all') {
            $rows = $rows->where('currency', strtoupper($monedaF))->values();
        }
        if ($tecnicoQ !== '') {
            $needle = $tecnicoQ;
            $rows = $rows->filter(function ($r) use ($needle) {
                $name = (string) ($r['technician'] ?? '');
                return $name !== '' && stripos($name, $needle) !== false;
            })->values();
        }

        $summary = [
            'total'          => $rows->count(),
            'enProceso'      => $rows->where('status', 'en-proceso')->count(),
            'finalizados'    => $rows->where('status', 'finalizado')->count(),
            'totalFacturado' => round($rows->sum('finalTotalMxn'), 2),
            'monedaResumen'  => 'MXN',
        ];

        return response()->json([
            'rows'    => $rows,
            'summary' => $summary,
        ]);
    }

    // Compat: rutas antiguas apuntan a estos nombres.
    public function progress(Request $request, $ordenId)
    {
        return $this->seguimientosIndex($request, $ordenId);
    }

    // Compat: rutas antiguas apuntan a estos nombres.
    public function storeComment(Request $request, $ordenId)
    {
        return $this->seguimientosStore($request, $ordenId);
    }

    // Compat: algunas rutas envian {seguimiento}; no es necesario para guardar imagenes.
    public function storeImages(Request $request, $ordenId, $seguimiento = null)
    {
        return $this->imagenesStore($request, $ordenId);
    }

    private function statusSeguimientoFallback(OrdenServicio $o): string
    {
        $estado = strtolower((string) $o->estado);

        if (in_array($estado, ['cancelado', 'cancelada'], true)) {
            return 'cancelado';
        }

        if (in_array($estado, ['finalizado', 'finalizada', 'completada', 'completado'], true)) {
            return ((string) $o->acta_estado === 'firmada')
                ? 'finalizado'
                : 'finalizado-sin-firmar';
        }

        return 'en-proceso';
    }

    private function ordenBloqueadaParaEdicion(OrdenServicio $orden): bool
    {
        $actaFirmada = mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada';
        if ($actaFirmada) return true;

        $status = $orden->status_seguimiento ?? $this->statusSeguimientoFallback($orden);
        return in_array($status, ['finalizado', 'finalizado-sin-firmar'], true);
    }

    /* ===================== API: EXTRAS (Materiales no previstos) ===================== */

    public function extrasIndex($ordenId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        // ✅ SIEMPRE recalcula y (si existe columna) sincroniza
        $totalMxn = $this->syncTotalAdicionalMxn((int)$orden->id_orden_servicio);

        $extras = OrdenMaterialExtra::where('id_orden_servicio', $ordenId)
            ->orderBy('id_material_extra', 'asc')
            ->get();

        $pendCount = 0;
        $pendQty   = 0.0;

        $out = $extras->map(function ($e) use (&$pendCount, &$pendQty) {
            $cant = (float) ($e->cantidad ?? 0);
            $pu   = $e->precio_unitario;
            $pend = ($pu === null);

            $sub = null;
            if (!$pend) {
                $sub = round($cant * (float) $pu, 2);
            } else {
                $pendCount++;
                $pendQty += $cant;
            }

            return [
                'id'              => (int) $e->id_material_extra,
                'descripcion'     => (string) ($e->descripcion ?? ''),
                'cantidad'        => $cant,
                'precio_unitario' => $pend ? null : (float) $pu,
                'subtotal'        => $sub,
                'pendiente'       => $pend,
            ];
        })->values();

        return response()->json([
            'moneda'             => 'MXN',
            'extras'             => $out,
            'totalAdicional'     => round($totalMxn, 2),
            'pendientes'         => (int) $pendCount,
            'pendientesCantidad' => round($pendQty, 2),
        ]);
    }

    public function extrasStore(Request $request, $ordenId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden modificar extras en servicios finalizados o con acta firmada.'
            ], 422);
        }

        $data = $request->validate([
            'descripcion'     => ['required', 'string', 'max:255'],
            'cantidad'        => ['required', 'numeric', 'min:0.01'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        $puRaw = $request->input('precio_unitario');
        $pu = ($puRaw === '' || $puRaw === null) ? null : (float) $puRaw;

        DB::transaction(function () use ($orden, $data, $pu) {
            $extra = new OrdenMaterialExtra();
            $extra->id_orden_servicio = $orden->id_orden_servicio;
            $extra->descripcion       = $data['descripcion'];
            $extra->cantidad          = (float) $data['cantidad'];
            $extra->precio_unitario   = $pu;

            if (Schema::hasColumn($extra->getTable(), 'subtotal')) {
                $extra->subtotal = is_null($pu) ? null : ((float)$data['cantidad'] * $pu);
            }

            $extra->save();
        });

        $totalMxn = $this->syncTotalAdicionalMxn((int)$orden->id_orden_servicio);

        return response()->json(['ok' => true, 'totalAdicional' => $totalMxn], 201);
    }

    public function extrasUpdate(Request $request, $ordenId, $extraId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden modificar extras en servicios finalizados o con acta firmada.'
            ], 422);
        }

        $data = $request->validate([
            'descripcion'     => ['required', 'string', 'max:255'],
            'cantidad'        => ['required', 'numeric', 'min:0.01'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        $extra = OrdenMaterialExtra::where('id_orden_servicio', $ordenId)
            ->where('id_material_extra', $extraId)
            ->firstOrFail();

        $puRaw = $request->input('precio_unitario');
        $pu = ($puRaw === '' || $puRaw === null) ? null : (float) $puRaw;

        DB::transaction(function () use ($extra, $data, $pu) {
            $extra->descripcion     = $data['descripcion'];
            $extra->cantidad        = (float) $data['cantidad'];
            $extra->precio_unitario = $pu;

            if (Schema::hasColumn($extra->getTable(), 'subtotal')) {
                $extra->subtotal = is_null($pu) ? null : ((float)$data['cantidad'] * $pu);
            }

            $extra->save();
        });

        $totalMxn = $this->syncTotalAdicionalMxn((int)$orden->id_orden_servicio);

        return response()->json(['ok' => true, 'totalAdicional' => $totalMxn]);
    }

    public function extrasDestroy($ordenId, $extraId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden modificar extras en servicios finalizados o con acta firmada.'
            ], 422);
        }

        $extra = OrdenMaterialExtra::where('id_orden_servicio', $ordenId)
            ->where('id_material_extra', $extraId)
            ->firstOrFail();

        DB::transaction(function () use ($extra) {
            $extra->delete();
        });

        $totalMxn = $this->syncTotalAdicionalMxn((int)$orden->id_orden_servicio);

        return response()->json(['ok' => true, 'totalAdicional' => $totalMxn]);
    }

    /* ===================== API: AVANCES (seguimientos + imágenes) ===================== */

    public function seguimientosIndex(Request $request, $ordenId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        $q = SeguimientoServicio::where('id_orden_servicio', $orden->id_orden_servicio);

        if (Schema::hasColumn('seguimiento_servicio', 'created_at')) $q->orderByDesc('created_at');
        else $q->orderByDesc('id_seguimiento');

        $seguimientos = $q->get()->map(function (SeguimientoServicio $s) {
            $fecha = $s->created_at ?? null;
            return [
                'id'          => $s->id_seguimiento,
                'fecha'       => $fecha,
                'fecha_fmt'   => $fecha ? $fecha->format('Y-m-d H:i') : null,
                'descripcion' => $s->observaciones ?? $s->comentarios ?? '',
            ];
        })->values();

        $imagenes = [];
        if (Schema::hasTable('seguimiento_imagenes')) {
            $imgQuery = SeguimientoImagen::where('id_orden_servicio', $orden->id_orden_servicio);
            $imgTable = (new SeguimientoImagen())->getTable();

            if (Schema::hasColumn($imgTable, 'created_at')) $imgQuery->orderByDesc('created_at');
            else $imgQuery->orderByDesc('id_imagen');

            $imagenes = $imgQuery->get()->map(function (SeguimientoImagen $img) {
                $fecha = $img->created_at ?? null;
                return [
                    'id'        => $img->id_imagen,
                    'ruta'      => $img->ruta,
                    'url'       => $img->url,
                    'titulo'    => $img->titulo ?? null,
                    'orden'     => $img->orden,
                    'fecha_fmt' => $fecha ? $fecha->format('Y-m-d H:i') : null,
                ];
            })->values();
        }

        return response()->json([
            'orden'        => ['id' => $orden->id_orden_servicio],
            'seguimientos' => $seguimientos,
            'imagenes'     => $imagenes,
        ]);
    }

    public function seguimientosStore(Request $request, $ordenId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden agregar comentarios en servicios finalizados o con acta firmada.'
            ], 422);
        }

        $data = $request->validate([
            'descripcion' => 'required|string|max:2000',
        ]);

        $texto = $data['descripcion'];

        $seguimiento = SeguimientoServicio::create([
            'id_orden_servicio' => $orden->id_orden_servicio,
            'observaciones'     => $texto,
            'comentarios'       => $texto,
            'imagen'            => '',
        ]);

        return response()->json(['ok' => true, 'id' => $seguimiento->id_seguimiento], 201);
    }

    public function imagenesStore(Request $request, $ordenId)
    {
        $orden = OrdenServicio::findOrFail($ordenId);

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden agregar imágenes en servicios finalizados o con acta firmada.'
            ], 422);
        }

        $request->validate([
            'imagenes'   => 'required|array',
            'imagenes.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $created = [];

        foreach ($request->file('imagenes') as $i => $file) {
            $path = $file->store('seguimientos', 'public');

            $img = SeguimientoImagen::create([
                'id_orden_servicio' => $orden->id_orden_servicio,
                'ruta'              => $path,
                'orden'             => $i,
            ]);

            $created[] = ['id' => $img->id_imagen, 'url' => $img->url];
        }

        return response()->json(['ok' => true, 'count' => count($created), 'imagenes' => $created], 201);
    }
}

