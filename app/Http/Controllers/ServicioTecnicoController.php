<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

use App\Models\OrdenServicio;
use App\Models\SeguimientoServicio;
use App\Models\SeguimientoImagen;
use App\Models\OrdenMaterialExtra;

class ServicioTecnicoController extends Controller
{
    protected function ordenTieneActaFirmada(OrdenServicio $orden): bool
    {
        return mb_strtolower((string) ($orden->acta_estado ?? '')) === 'firmada';
    }

    protected function statusSeguimientoFallback(OrdenServicio $orden): string
    {
        $estado = strtolower((string) $orden->estado);

        if (in_array($estado, ['cancelado', 'cancelada'], true)) {
            return 'cancelado';
        }

        if (in_array($estado, ['finalizado', 'finalizada', 'completada', 'completado'], true)) {
            return mb_strtolower((string) ($orden->acta_estado ?? '')) === 'firmada'
                ? 'finalizado'
                : 'finalizado-sin-firmar';
        }

        return 'en-proceso';
    }

    protected function ordenBloqueadaParaEdicion(OrdenServicio $orden): bool
    {
        if ($this->ordenTieneActaFirmada($orden)) {
            return true;
        }

        $status = $orden->status_seguimiento ?? $this->statusSeguimientoFallback($orden);

        return in_array($status, ['finalizado', 'finalizado-sin-firmar'], true);
    }

    protected function redirectOrdenBloqueada($idOS, OrdenServicio $orden, string $mensajeFinalizada, ?string $mensajeActa = null)
    {
        $mensaje = $this->ordenTieneActaFirmada($orden)
            ? ($mensajeActa ?? $mensajeFinalizada)
            : $mensajeFinalizada;

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('error', $mensaje);
    }

    protected function pkOrdenServicio(): string
    {
        static $pk = null;
        if ($pk !== null) return $pk;

        $tabla = (new OrdenServicio())->getTable();

        if (Schema::hasColumn($tabla, 'id_orden_servicio')) {
            $pk = 'id_orden_servicio';
        } else {
            $pk = Schema::hasColumn($tabla, 'id') ? 'id' : 'id_orden_servicio';
        }

        return $pk;
    }

    protected function osLabel($orden): string
    {
        $pk = $this->pkOrdenServicio();
        $id = $orden->{$pk} ?? null;

        return $id ? ('OS-' . $id) : '—';
    }

    public function dashboard()
    {
        $user  = Auth::user();
        $pk    = $this->pkOrdenServicio();
        $orden = new OrdenServicio();
        $tabla = $orden->getTable();

        $hasFechaOrden = Schema::hasColumn($tabla, 'fecha_orden');
        $hasCreatedAt  = Schema::hasColumn($tabla, 'created_at');

        $colFecha = $hasFechaOrden
            ? 'fecha_orden'
            : ($hasCreatedAt ? 'created_at' : null);

        $base = OrdenServicio::query()
            ->with(['cliente'])
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            });

        $hoy       = Carbon::today();
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes    = $hoy->copy()->endOfMonth();

        $estadosFinalizados = [
            'finalizado', 'Finalizado',
            'finalizada', 'Finalizada',
            'completada', 'Completada',
            'completado', 'Completado',
        ];

        $asignadosMes = (clone $base)
            ->when($colFecha, function ($q) use ($colFecha, $inicioMes, $finMes) {
                $q->whereBetween($colFecha, [$inicioMes, $finMes]);
            })
            ->count();

        $completadosMes = (clone $base)
            ->whereIn('estado', $estadosFinalizados)
            ->when($colFecha, function ($q) use ($colFecha, $inicioMes, $finMes) {
                $q->whereBetween($colFecha, [$inicioMes, $finMes]);
            })
            ->count();

        $estadosCancelados = ['cancelado', 'Cancelado', 'cancelada', 'Cancelada'];

        $ordenesAsignadas = (clone $base)
            ->whereNotIn('estado', array_merge($estadosFinalizados, $estadosCancelados))
            ->when($colFecha, function ($q) use ($colFecha) {
                $q->orderBy($colFecha, 'asc');
            }, function ($q) use ($pk) {
                $q->orderBy($pk, 'asc');
            })
            ->get();

        foreach ($ordenesAsignadas as $o) {
            $o->os_label = $this->osLabel($o);
        }

        return view('vistas-tecnico.inicio_tecnico', [
            'asignadosMes'   => $asignadosMes,
            'completadosMes' => $completadosMes,
            'ordenes'        => $ordenesAsignadas,
            'colFecha'       => $colFecha,
        ]);
    }

    public function autocomplete(Request $request)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        $term = trim((string) $request->input('term', ''));

        if ($term === '' || mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $termNum = preg_replace('/\D+/', '', $term);
        $termNum = $termNum !== '' ? $termNum : null;

        $query = OrdenServicio::query()
            ->with('cliente')
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            })
            ->where(function ($q) use ($term, $termNum, $pk) {
                $q->where($pk, 'like', "%{$term}%");

                if ($termNum !== null) {
                    $q->orWhere($pk, 'like', "%{$termNum}%");
                }

                $q->orWhere('servicio', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%")
                  ->orWhereHas('cliente', function ($c) use ($term) {
                      $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('nombre_empresa', 'like', "%{$term}%");
                  });
            })
            ->orderByDesc($pk)
            ->limit(12)
            ->get();

        $out = $query->map(function ($os) use ($pk) {
            $id = $os->{$pk};

            $cliente = $os->cliente->nombre
                ?? ($os->cliente->nombre_empresa ?? 'Sin cliente');

            $serv = $os->servicio
                ?: ($os->descripcion ? mb_strimwidth($os->descripcion, 0, 35, '…') : '—');

            $label = 'OS-' . $id . " — {$cliente} — {$serv}";

            return [
                'id'    => $id,
                'label' => $label,
            ];
        })->values();

        return response()->json($out);
    }

    public function index(Request $request)
    {
        $user  = Auth::user();
        $pk    = $this->pkOrdenServicio();
        $tabla = (new OrdenServicio())->getTable();

        $hasActaFirmadaEn = Schema::hasColumn($tabla, 'acta_firmada_en');
        $hasFechaCierre   = Schema::hasColumn($tabla, 'fecha_cierre');
        $hasFechaOrden    = Schema::hasColumn($tabla, 'fecha_orden');
        $hasCreatedAt     = Schema::hasColumn($tabla, 'created_at');

        $buscar  = trim((string) $request->input('buscar', ''));
        $ordenId = trim((string) $request->input('orden_id', ''));
        $estado  = trim((string) $request->input('estado', ''));
        $desde   = trim((string) $request->input('desde', ''));

        $base = OrdenServicio::query()
            ->with('cliente')
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            });

        if ($ordenId !== '') {
            $base->where($pk, $ordenId);
        } else {
            if ($buscar !== '') {
                $buscarNum = preg_replace('/\D+/', '', $buscar);
                $buscarNum = $buscarNum !== '' ? $buscarNum : null;

                $base->where(function ($q) use ($buscar, $buscarNum, $pk) {
                    $q->where($pk, 'like', "%{$buscar}%");

                    if ($buscarNum !== null) {
                        $q->orWhere($pk, 'like', "%{$buscarNum}%");
                    }

                    $q->orWhere('servicio', 'like', "%{$buscar}%")
                      ->orWhere('descripcion', 'like', "%{$buscar}%")
                      ->orWhereHas('cliente', function ($c) use ($buscar) {
                          $c->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('nombre_empresa', 'like', "%{$buscar}%");
                      });
                });
            }
        }

        if ($estado !== '') {
            $estadoLower = mb_strtolower($estado);
            $map = [
                'pendiente' => ['pendiente', 'Pendiente'],
                'completada' => [
                    'completado', 'Completado',
                    'completada', 'Completada',
                    'finalizado', 'Finalizado',
                    'finalizada', 'Finalizada',
                ],
                'cancelada' => ['cancelado', 'Cancelado', 'cancelada', 'Cancelada'],
            ];

            if (isset($map[$estadoLower])) {
                $base->whereIn('estado', $map[$estadoLower]);
            } else {
                $base->where('estado', $estado);
            }
        }

        if ($desde !== '') {
            try {
                $desdeDate = Carbon::parse($desde)->startOfDay();
                $base->where(function ($q) use ($desdeDate, $hasActaFirmadaEn, $hasFechaCierre, $hasFechaOrden, $hasCreatedAt) {
                    if ($hasActaFirmadaEn) $q->orWhere('acta_firmada_en', '>=', $desdeDate);
                    if ($hasFechaCierre)   $q->orWhere('fecha_cierre', '>=', $desdeDate);
                    if ($hasFechaOrden)    $q->orWhereDate('fecha_orden', '>=', $desdeDate->toDateString());
                    if ($hasCreatedAt)     $q->orWhere('created_at', '>=', $desdeDate);
                });
            } catch (\Throwable $e) {}
        }

        $orderExpr = 'updated_at';
        if ($hasActaFirmadaEn || $hasFechaCierre) {
            $parts = [];
            if ($hasActaFirmadaEn) $parts[] = 'acta_firmada_en';
            if ($hasFechaCierre)   $parts[] = 'fecha_cierre';
            $parts[] = 'updated_at';
            if ($hasCreatedAt) $parts[] = 'created_at';

            $orderExpr = 'COALESCE(' . implode(',', $parts) . ')';
        }

        $servicios = $base
            ->orderByDesc(DB::raw($orderExpr))
            ->paginate(20)
            ->withQueryString();

        foreach ($servicios as $orden) {
            $orden->os_label = $this->osLabel($orden);

            $estadoRaw   = (string) ($orden->estado ?? '');
            $estadoLower = mb_strtolower($estadoRaw);

            if ($estadoLower === '') {
                $estadoNorm = 'desconocido';
            } elseif (str_contains($estadoLower, 'pend')) {
                $estadoNorm = 'pendiente';
            } elseif (str_contains($estadoLower, 'cancel')) {
                $estadoNorm = 'cancelada';
            } elseif (str_contains($estadoLower, 'final') || str_contains($estadoLower, 'complet')) {
                $estadoNorm = 'completada';
            } else {
                $estadoNorm = $estadoRaw;
            }
            $orden->estado_normalizado = $estadoNorm;

            $actaEstado = (string) ($orden->acta_estado ?? '');
            if (mb_strtolower($actaEstado) === 'firmada') {
                $orden->acta_label = 'Firmada';
            } elseif (mb_strtolower($actaEstado) === 'borrador') {
                $orden->acta_label = 'Borrador';
            } else {
                $orden->acta_label = 'Sin acta';
            }

            if ($hasActaFirmadaEn && !empty($orden->acta_firmada_en)) {
                $orden->fecha_hora_servicio = Carbon::parse($orden->acta_firmada_en);
            } elseif ($hasFechaCierre && !empty($orden->fecha_cierre)) {
                $orden->fecha_hora_servicio = Carbon::parse($orden->fecha_cierre);
            } elseif (!empty($orden->updated_at)) {
                $orden->fecha_hora_servicio = Carbon::parse($orden->updated_at);
            } elseif (!empty($orden->created_at)) {
                $orden->fecha_hora_servicio = Carbon::parse($orden->created_at);
            } else {
                $orden->fecha_hora_servicio = null;
            }
        }

        return view('vistas-tecnico.servicios_tecnico', [
            'servicios' => $servicios,
            'filtros'   => [
                'buscar'    => $buscar,
                'orden_id'  => $ordenId,
                'estado'    => $estado,
                'desde'     => $desde,
            ],
        ]);
    }

    public function detalles($id = null)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        $base = OrdenServicio::query()
            ->with(['cliente', 'tecnicos'])
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            });

        if ($id !== null) {
            $orden = (clone $base)->where($pk, $id)->firstOrFail();
        } else {
            $orden = (clone $base)->orderByDesc($pk)->first();

            if (! $orden) {
                return redirect()
                    ->route('tecnico.inicio')
                    ->with('info', 'Por el momento no tienes órdenes de servicio asignadas.');
            }
        }

        $tabla = $orden->getTable();

        $colFechaProgramada = Schema::hasColumn($tabla, 'fecha_programada') ? 'fecha_programada' : null;

        $colFechaOrden = null;
        if (Schema::hasColumn($tabla, 'fecha_orden')) {
            $colFechaOrden = 'fecha_orden';
        } elseif (Schema::hasColumn($tabla, 'created_at')) {
            $colFechaOrden = 'created_at';
        }

        $idOrden = $orden->{$pk};

        $seguimientos = SeguimientoServicio::where('id_orden_servicio', $idOrden)
            ->orderByDesc('created_at')
            ->get();

        $imagenes = SeguimientoImagen::where('id_orden_servicio', $idOrden)
            ->orderByDesc('created_at')
            ->get();

        $extras = OrdenMaterialExtra::where('id_orden_servicio', $idOrden)
            ->orderBy('id_material_extra', 'asc')
            ->get();

        // Moneda para materiales no previstos
        $orderCurrency = strtoupper(trim((string) ($orden->moneda ?? 'MXN')));
        if ($orderCurrency === '') $orderCurrency = 'MXN';

        $orderExchangeRate = (float) ($orden->tasa_cambio ?? $orden->tipo_cambio ?? 1.0);
        if ($orderExchangeRate <= 0) $orderExchangeRate = 1.0;

        $extrasBaseCurrency = 'MXN';
        $mnpUsesConversion  = ($orderCurrency !== $extrasBaseCurrency && $orderExchangeRate > 1.0001);

        $extrasTotalBase          = 0.0; // solo con precio
        $extrasTotalDisplay       = 0.0; // solo con precio
        $extrasCantidadTotal      = 0.0; // siempre suma cantidades
        $extrasPendientesPrecio   = 0;   // cuenta pendientes

        foreach ($extras as $extra) {
            $cantBase = (float) ($extra->cantidad ?? 0);
            $extrasCantidadTotal += $cantBase;

            // NULL = pendiente
            $puRaw = $extra->precio_unitario;
            $puBase = is_null($puRaw) ? null : (float) $puRaw;

            $subBase = null;
            if (!is_null($puBase)) {
                $subBase = $extra->subtotal ?? ($cantBase * $puBase);
            } else {
                $extrasPendientesPrecio++;
            }

            // Conversión solo visual (si procede)
            if ($mnpUsesConversion && !is_null($puBase)) {
                // MXN -> USD (si tu tasa es MXN por 1 USD)
                $puDisplay  = $puBase / $orderExchangeRate;
                $subDisplay = $subBase / $orderExchangeRate;
            } else {
                $puDisplay  = $puBase;
                $subDisplay = $subBase;
            }

            $extra->mnp_cantidad           = $cantBase;
            $extra->mnp_pu_base            = $puBase;
            $extra->mnp_sub_base           = $subBase;
            $extra->mnp_pu_display         = $puDisplay;
            $extra->mnp_sub_display        = $subDisplay;
            $extra->mnp_pendiente_precio   = is_null($puBase);

            // ✅ totals solo si ya hay precio
            if (!is_null($subBase)) {
                $extrasTotalBase    += $subBase;
                $extrasTotalDisplay += $subDisplay;
            }
        }

        return view('vistas-tecnico.detalles_servicio_tecnico', [
            'orden'                 => $orden,
            'colFechaProgramada'    => $colFechaProgramada,
            'colFechaOrden'         => $colFechaOrden,
            'seguimientos'          => $seguimientos,
            'imagenes'              => $imagenes,
            'extras'                => $extras,

            'orderCurrency'         => $orderCurrency,
            'orderExchangeRate'     => $orderExchangeRate,
            'extrasBaseCurrency'    => $extrasBaseCurrency,
            'extrasTotalBase'       => $extrasTotalBase,
            'extrasTotalDisplay'    => $extrasTotalDisplay,
            'mnpUsesConversion'     => $mnpUsesConversion,

            'extrasCantidadTotal'   => $extrasCantidadTotal,
            'extrasPendientesPrecio'=> $extrasPendientesPrecio,
        ]);
    }

    public function proyecto()
    {
        return view('vistas-tecnico.servicio_proyecto_tecnico');
    }

    public function detallesProyecto($id)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        $base = OrdenServicio::query()
            ->with(['cliente', 'tecnicos', 'seguimientos', 'imagenes', 'materialesExtras'])
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            });

        $orden = (clone $base)->where($pk, $id)->firstOrFail();

        $tabla = $orden->getTable();

        $colFechaProgramada = Schema::hasColumn($tabla, 'fecha_programada')
            ? 'fecha_programada'
            : null;

        $colFechaOrden = null;
        if (Schema::hasColumn($tabla, 'fecha_orden')) {
            $colFechaOrden = 'fecha_orden';
        } elseif (Schema::hasColumn($tabla, 'created_at')) {
            $colFechaOrden = 'created_at';
        }

        return view('vistas-tecnico.detalles_servicio_proyecto_tecnico', [
            'orden'              => $orden,
            'colFechaProgramada' => $colFechaProgramada,
            'colFechaOrden'      => $colFechaOrden,
        ]);
    }

    public function acta($id = null)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        if ($id) {
            return redirect()->route('tecnico.ordenes.acta.vista', ['id' => $id]);
        }

        $orden = OrdenServicio::query()
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            })
            ->orderByDesc($pk)
            ->first();

        if (! $orden) {
            return redirect()
                ->route('tecnico.inicio')
                ->with('info', 'Por el momento no tienes órdenes de servicio asignadas.');
        }

        return redirect()->route('tecnico.ordenes.acta.vista', [
            'id' => $orden->{$pk},
        ]);
    }

    public function completarProyecto(Request $request, $id)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        $request->validate([
            'observaciones_internas' => ['nullable', 'string', 'max:2000'],
        ]);

        $orden = OrdenServicio::query()
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            })
            ->where($pk, $id)
            ->firstOrFail();

        $tabla = $orden->getTable();

        if (Schema::hasColumn($tabla, 'observaciones_internas')) {
            $orden->observaciones_internas = $request->input('observaciones_internas');
        }

        if (Schema::hasColumn($tabla, 'estado')) {
            $orden->estado = 'Finalizado';
        }

        if (Schema::hasColumn($tabla, 'fecha_cierre')) {
            $orden->fecha_cierre = Carbon::now();
        }

        $orden->save();

        return redirect()
            ->route('tecnico.detalles', $orden->{$pk})
            ->with('success', 'Servicio marcado como completado.');
    }

    /* ===================== Helpers / Formularios ===================== */

    protected function findOrdenAsignadaOrFail($id)
    {
        $user = Auth::user();
        $pk   = $this->pkOrdenServicio();

        return OrdenServicio::query()
            ->where(function ($q) use ($user) {
                $q->where('id_tecnico', $user->id)
                  ->orWhereHas('tecnicos', function ($t) use ($user) {
                      $t->where('users.id', $user->id);
                  });
            })
            ->where($pk, $id)
            ->firstOrFail();
    }

    public function storeSeguimiento(Request $request, $ordenId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden registrar mas seguimientos ni imagenes.'
            );
        }

        if ($this->ordenBloqueadaParaEdicion($orden)) {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'Esta orden ya cuenta con un acta firmada. No es posible registrar más seguimientos ni imágenes.');
        }

        $request->validate([
            'comentario' => ['nullable', 'string', 'max:2000'],
            'imagenes.*' => ['nullable', 'image', 'max:4096'],
        ]);

        $comentario    = trim((string) $request->input('comentario', ''));
        $tieneImagenes = $request->hasFile('imagenes');

        if ($comentario === '' && ! $tieneImagenes) {
            return back()
                ->withInput()
                ->withErrors(['comentario' => 'Escribe un comentario o adjunta al menos una imagen.']);
        }

        DB::transaction(function () use ($comentario, $tieneImagenes, $request, $idOS) {
            if ($comentario !== '') {
                SeguimientoServicio::create([
                    'observaciones'     => $comentario,
                    'comentarios'       => $comentario,
                    'imagen'            => '',
                    'id_orden_servicio' => $idOS,
                ]);
            }

            if ($tieneImagenes) {
                foreach ($request->file('imagenes') as $file) {
                    if (! $file) continue;

                    $path = $file->store('seguimiento_imagenes', 'public');

                    SeguimientoImagen::create([
                        'id_orden_servicio' => $idOS,
                        'ruta'              => $path,
                    ]);
                }
            }
        });

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Seguimiento actualizado correctamente.');
    }

    /**
     * TÉCNICO: crea material no previsto SIN precio (pendiente).
     */
    public function storeExtra(Request $request, $ordenId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden registrar mas materiales no previstos.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'Esta orden ya cuenta con un acta firmada. No es posible registrar más materiales no previstos.');
        }

        $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'cantidad'    => ['required', 'numeric', 'min:0.01'],
        ]);

        $desc = $request->input('descripcion', $request->input('concepto')); // fallback

        $extra = new OrdenMaterialExtra();
        $extra->id_orden_servicio = $idOS;
        $extra->descripcion       = $desc;
        $extra->cantidad          = $request->input('cantidad');

        // Precio pendiente (lo asigna gerente)
        $extra->precio_unitario   = null;

        if (Schema::hasColumn($extra->getTable(), 'subtotal')) {
            $extra->subtotal = null;
        }

        $extra->save();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Material no previsto agregado. El precio será asignado por el gerente.');
    }


    /**
     * TÉCNICO: edita SOLO concepto/cantidad. Precio lo pone gerente.
     */
    public function updateExtra(Request $request, $ordenId, $extraId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden modificar materiales no previstos.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'El acta ya está firmada. No se pueden modificar materiales no previstos.');
        }

        $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'cantidad'    => ['required', 'numeric', 'min:0.01'],
        ]);

        $desc = $request->input('descripcion', $request->input('concepto')); // fallback

        $extra = OrdenMaterialExtra::where('id_orden_servicio', $idOS)
            ->where('id_material_extra', $extraId)
            ->firstOrFail();

        $extra->descripcion = $desc;
        $extra->cantidad    = $request->input('cantidad');
        // NO tocar precio_unitario (gerente)
        $extra->save();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Material no previsto actualizado. El precio lo asigna el gerente.');
    }


    public function destroyExtra($ordenId, $extraId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden eliminar materiales no previstos.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'El acta ya está firmada. No se pueden eliminar materiales no previstos.');
        }

        $extra = OrdenMaterialExtra::where('id_orden_servicio', $idOS)
            ->where('id_material_extra', $extraId)
            ->firstOrFail();

        $extra->delete();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Material no previsto eliminado correctamente.');
    }

    public function updateSeguimiento(Request $request, $ordenId, $seguimientoId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden modificar seguimientos.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'El acta ya está firmada. No se pueden modificar seguimientos.');
        }

        $request->validate([
            'comentario' => ['required', 'string', 'max:2000'],
        ]);

        $seg = SeguimientoServicio::where('id_orden_servicio', $idOS)
            ->where('id_seguimiento', $seguimientoId)
            ->firstOrFail();

        $comentario = trim((string) $request->input('comentario', ''));

        $seg->comentarios   = $comentario;
        $seg->observaciones = $comentario;
        $seg->save();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Comentario de seguimiento actualizado correctamente.');
    }

    public function destroySeguimiento($ordenId, $seguimientoId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden eliminar seguimientos.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'El acta ya está firmada. No se pueden eliminar seguimientos.');
        }

        $seg = SeguimientoServicio::where('id_orden_servicio', $idOS)
            ->where('id_seguimiento', $seguimientoId)
            ->firstOrFail();

        $seg->delete();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Comentario de seguimiento eliminado correctamente.');
    }

    public function destroyImagen($ordenId, $imagenId)
    {
        $orden = $this->findOrdenAsignadaOrFail($ordenId);
        $pk    = $this->pkOrdenServicio();
        $idOS  = $orden->{$pk};

        if (! $this->ordenTieneActaFirmada($orden) && $this->ordenBloqueadaParaEdicion($orden)) {
            return $this->redirectOrdenBloqueada(
                $idOS,
                $orden,
                'La orden ya esta finalizada y no se pueden eliminar imagenes.'
            );
        }

        if (mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada') {
            return redirect()
                ->route('tecnico.detalles', $idOS)
                ->with('error', 'El acta ya está firmada. No se pueden eliminar imágenes.');
        }

        $img = SeguimientoImagen::where('id_orden_servicio', $idOS)
            ->where('id_imagen', $imagenId)
            ->firstOrFail();

        if ($img->ruta && Storage::disk('public')->exists($img->ruta)) {
            Storage::disk('public')->delete($img->ruta);
        }

        $img->delete();

        return redirect()
            ->route('tecnico.detalles', $idOS)
            ->with('success', 'Imagen eliminada correctamente.');
    }
}
