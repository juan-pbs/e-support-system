<?php

namespace App\Http\Controllers\Tecnico;

use App\Http\Controllers\Controller;
use App\Models\MovimientoLogistico;
use App\Services\Logistica\LogisticaService;
use Illuminate\Http\Request;

class LogisticaTecnicoController extends Controller
{
    public function __construct(private LogisticaService $logistica) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $movimientos = MovimientoLogistico::query()
            ->with(['jornada', 'cliente', 'direccionCliente', 'proveedor', 'tecnico', 'detalles', 'evidencias'])
            ->whereIn('estado', ['pendiente', 'asignado', 'en_ruta', 'en_sitio', 'recogido', 'entregado'])
            ->where(function ($query) use ($user) {
                $query->where('tecnico_id', $user->id)
                    ->orWhere(function ($subquery) {
                        $subquery->whereNull('tecnico_id')
                            ->where('tipo', 'recoleccion')
                            ->where('origen_tipo', 'inventario_programado')
                            ->whereIn('estado', ['pendiente', 'asignado']);
                    });
            })
            ->orderByRaw("CASE WHEN tecnico_id IS NULL AND tipo = 'recoleccion' AND origen_tipo = 'inventario_programado' THEN 0 ELSE 1 END")
            ->latest('fecha_programada')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('tecnico.logistica.index', [
            'movimientos' => $movimientos,
        ]);
    }

    public function show(Request $request, MovimientoLogistico $movimiento)
    {
        abort_unless($this->logistica->puedeTecnicoVerMovimiento($movimiento, $request->user()), 403);

        $movimiento->load([
            'jornada',
            'cliente',
            'direccionCliente',
            'proveedor',
            'detalles.producto',
            'evidencias',
            'ordenServicio',
        ]);

        return view('tecnico.logistica.show', [
            'movimiento' => $movimiento,
        ]);
    }

    public function cambiarEstado(Request $request, MovimientoLogistico $movimiento)
    {
        abort_unless($this->logistica->puedeTecnicoVerMovimiento($movimiento, $request->user()), 403);

        $data = $request->validate([
            'estado' => ['required', 'in:en_ruta,en_sitio'],
            'current_lat' => ['nullable', 'numeric'],
            'current_lng' => ['nullable', 'numeric'],
        ]);

        $this->logistica->asegurarTecnicoResponsable($movimiento, $request->user());

        $this->logistica->cambiarEstadoTecnico(
            $movimiento,
            (string) $data['estado'],
            isset($data['current_lat']) ? (float) $data['current_lat'] : null,
            isset($data['current_lng']) ? (float) $data['current_lng'] : null
        );

        return redirect()
            ->route('tecnico.logistica.show', $movimiento)
            ->with('success', 'Estado logístico actualizado correctamente.');
    }

    public function completar(Request $request, MovimientoLogistico $movimiento)
    {
        abort_unless($this->logistica->puedeTecnicoVerMovimiento($movimiento, $request->user()), 403);

        $data = $request->validate([
            'comentario' => ['nullable', 'string', 'max:2000'],
            'current_lat' => ['required', 'numeric'],
            'current_lng' => ['required', 'numeric'],
            'evidencias' => ['nullable', 'array'],
            'evidencias.*' => ['image', 'max:6144'],
        ]);

        $this->logistica->asegurarTecnicoResponsable($movimiento, $request->user());

        $this->logistica->completarMovimientoTecnico(
            $movimiento,
            collect($request->file('evidencias', []))
                ->filter()
                ->values()
                ->all(),
            $data['comentario'] ?? null,
            (float) $data['current_lat'],
            (float) $data['current_lng'],
            $request->user()
        );

        return redirect()
            ->route('tecnico.logistica.show', $movimiento)
            ->with('success', 'Movimiento completado correctamente.');
    }
}
