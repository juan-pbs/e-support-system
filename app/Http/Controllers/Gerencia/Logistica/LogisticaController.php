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
                'estado' => $estado,
                'tipo' => $tipo,
                'tecnico_id' => $tecnicoId,
            ],
        ]);
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
