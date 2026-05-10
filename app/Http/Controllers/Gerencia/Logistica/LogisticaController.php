<?php

namespace App\Http\Controllers\Gerencia\Logistica;

use App\Http\Controllers\Controller;
use App\Models\JornadaLogistica;
use App\Models\MovimientoLogistico;
use App\Models\User;
use App\Services\Logistica\LogisticaService;
use Illuminate\Http\Request;

class LogisticaController extends Controller
{
    public function __construct(private LogisticaService $logistica) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $movimientoId = $request->input('movimiento_id');
        $estado = trim((string) $request->input('estado', ''));
        $tipo = trim((string) $request->input('tipo', ''));
        $tecnicoId = $request->input('tecnico_id');

        $movimientos = MovimientoLogistico::query()
            ->with([
                'jornada',
                'cliente',
                'direccionCliente',
                'proveedor',
                'tecnico',
                'ordenServicio',
                'detalles',
                'evidencias',
            ])
            ->when($movimientoId, fn ($query) => $query->whereKey((int) $movimientoId))
            ->when(! $movimientoId && $q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $num = preg_replace('/\D+/', '', $q);

                $query->where(function ($sub) use ($like, $num) {
                    if ($num !== '') {
                        $sub->orWhere('id', (int) $num)
                            ->orWhere('orden_servicio_id', (int) $num)
                            ->orWhere('origen_id', (int) $num);
                    }

                    $sub->orWhere('contacto', 'like', $like)
                        ->orWhere('telefono', 'like', $like)
                        ->orWhere('alias_direccion', 'like', $like)
                        ->orWhere('direccion_formateada', 'like', $like)
                        ->orWhere('referencia', 'like', $like)
                        ->orWhere('observaciones', 'like', $like)
                        ->orWhereHas('jornada', fn ($j) => $j->where('folio', 'like', $like)->orWhere('nombre', 'like', $like))
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', $like)->orWhere('nombre_empresa', 'like', $like)->orWhere('codigo_cliente', 'like', $like))
                        ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', $like)->orWhere('alias', 'like', $like)->orWhere('rfc', 'like', $like))
                        ->orWhereHas('tecnico', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('ordenServicio', fn ($o) => $o->where('servicio', 'like', $like)->orWhere('descripcion', 'like', $like)->orWhere('descripcion_servicio', 'like', $like));
                });
            })
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->when($tipo !== '', fn ($q) => $q->where('tipo', $tipo))
            ->when($tecnicoId, fn ($q) => $q->where('tecnico_id', (int) $tecnicoId))
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 WHEN estado = 'asignado' THEN 1 WHEN estado = 'en_ruta' THEN 2 WHEN estado = 'en_sitio' THEN 3 ELSE 4 END")
            ->latest('fecha_programada')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $jornadaActiva = $this->logistica->getJornadaAbiertaActual();
        $jornadas = JornadaLogistica::query()
            ->withCount('movimientos')
            ->latest('opened_at')
            ->latest('id')
            ->limit(10)
            ->get();

        $tecnicos = User::query()
            ->where('puesto', 'tecnico')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('gerencia.logistica.index', [
            'movimientos' => $movimientos,
            'jornadaActiva' => $jornadaActiva,
            'jornadas' => $jornadas,
            'tecnicos' => $tecnicos,
            'filtros' => [
                'q' => $q,
                'movimiento_id' => $movimientoId,
                'estado' => $estado,
                'tipo' => $tipo,
                'tecnico_id' => $tecnicoId,
            ],
        ]);
    }

    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->input('term', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $like = "%{$term}%";
        $num = preg_replace('/\D+/', '', $term);

        $rows = MovimientoLogistico::query()
            ->with(['jornada', 'cliente', 'proveedor', 'tecnico', 'ordenServicio'])
            ->where(function ($query) use ($like, $num) {
                if ($num !== '') {
                    $query->orWhere('id', (int) $num)
                        ->orWhere('orden_servicio_id', (int) $num)
                        ->orWhere('origen_id', (int) $num);
                }

                $query->orWhere('contacto', 'like', $like)
                    ->orWhere('alias_direccion', 'like', $like)
                    ->orWhere('direccion_formateada', 'like', $like)
                    ->orWhereHas('jornada', fn ($j) => $j->where('folio', 'like', $like)->orWhere('nombre', 'like', $like))
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', $like)->orWhere('nombre_empresa', 'like', $like)->orWhere('codigo_cliente', 'like', $like))
                    ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', $like)->orWhere('alias', 'like', $like))
                    ->orWhereHas('tecnico', fn ($t) => $t->where('name', 'like', $like));
            })
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json($rows->map(function (MovimientoLogistico $movimiento) {
            $folio = 'MOV-' . str_pad((string) $movimiento->id, 5, '0', STR_PAD_LEFT);
            $target = $movimiento->cliente?->nombre
                ?: $movimiento->proveedor?->nombre
                ?: $movimiento->contacto
                ?: 'Movimiento logistico';

            $meta = array_filter([
                $movimiento->tipo_label,
                $movimiento->estado_label,
                $movimiento->jornada?->folio,
                $movimiento->ordenServicio?->folio,
            ]);

            return [
                'id' => $movimiento->id,
                'label' => $folio . ' - ' . $target . (count($meta) ? ' (' . implode(' / ', $meta) . ')' : ''),
            ];
        })->values());
    }

    public function storeJornada(Request $request)
    {
        if ($this->logistica->getJornadaAbiertaActual()) {
            return redirect()
                ->route('logistica.index')
                ->with('error', 'Ya existe una jornada logística abierta. Ciérrala antes de abrir otra.');
        }

        $data = $request->validate([
            'nombre' => ['nullable', 'string', 'max:255'],
            'fecha' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->logistica->crearJornada($data, $request->user());

        return redirect()
            ->route('logistica.index')
            ->with('success', 'Jornada logística abierta correctamente.');
    }

    public function closeJornada(Request $request, JornadaLogistica $jornada)
    {
        $this->logistica->cerrarJornada($jornada, $request->user());

        return redirect()
            ->route('logistica.index')
            ->with('success', 'Jornada logística cerrada correctamente.');
    }

    public function updateMovimiento(Request $request, MovimientoLogistico $movimiento)
    {
        $data = $request->validate([
            'jornada_logistica_id' => ['nullable', 'integer', 'exists:jornadas_logisticas,id'],
            'tecnico_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_programada' => ['nullable', 'date'],
            'hora_programada' => ['nullable', 'date_format:H:i'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->logistica->actualizarAsignacionMovimiento($movimiento, $data);

        return redirect()
            ->route('logistica.index')
            ->with('success', 'Movimiento logístico actualizado correctamente.');
    }

    public function confirmarRecepcion(Request $request, MovimientoLogistico $movimiento)
    {
        $this->logistica->confirmarRecepcionRecoleccion($movimiento, $request->user());

        return redirect()
            ->route('logistica.index')
            ->with('success', 'La recolección ya fue confirmada como entrada de inventario.');
    }
}
