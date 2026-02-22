<?php
// app/Http/Controllers/OrdenMaterialExtraController.php
namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use App\Models\OrdenMaterialExtra;
use Illuminate\Http\Request;

class OrdenMaterialExtraController extends Controller
{
    public function index(OrdenServicio $orden)
    {
        $extras = $orden->materialesExtras()->get()->map(fn($e) => [
            'id' => $e->id_material_extra,
            'descripcion' => $e->descripcion,
            'cantidad' => $e->cantidad,
            'precio_unitario' => $e->precio_unitario,
            'subtotal' => $e->subtotal,
        ]);

        return response()->json([
            'extras' => $extras,
            'totalAdicional' => round($extras->sum('subtotal'), 2),
        ]);
    }

    public function store(Request $request, OrdenServicio $orden)
    {
        $data = $request->validate([
            'descripcion'     => ['required','string','max:255'],
            'cantidad'        => ['nullable','numeric','min:0'],
            'precio_unitario' => ['nullable','numeric','min:0'],
        ]);
        $data['cantidad'] = $data['cantidad'] ?? 1;
        $data['precio_unitario'] = $data['precio_unitario'] ?? 0;

        $extra = $orden->materialesExtras()->create($data);

        return response()->json([
            'ok' => true,
            'extra' => [
                'id' => $extra->id_material_extra,
                'descripcion' => $extra->descripcion,
                'cantidad' => $extra->cantidad,
                'precio_unitario' => $extra->precio_unitario,
                'subtotal' => $extra->subtotal,
            ]
        ], 201);
    }

    public function update(Request $request, OrdenServicio $orden, OrdenMaterialExtra $extra)
    {
        abort_unless($extra->id_orden_servicio === $orden->id_orden_servicio, 404);

        $data = $request->validate([
            'descripcion'     => ['required','string','max:255'],
            'cantidad'        => ['required','numeric','min:0'],
            'precio_unitario' => ['required','numeric','min:0'],
        ]);

        $extra->update($data);

        return response()->json([
            'ok' => true,
            'extra' => [
                'id' => $extra->id_material_extra,
                'descripcion' => $extra->descripcion,
                'cantidad' => $extra->cantidad,
                'precio_unitario' => $extra->precio_unitario,
                'subtotal' => $extra->subtotal,
            ]
        ]);
    }

    public function destroy(OrdenServicio $orden, OrdenMaterialExtra $extra)
    {
        abort_unless($extra->id_orden_servicio === $orden->id_orden_servicio, 404);
        $extra->delete();
        return response()->json(['ok' => true]);
    }
}
