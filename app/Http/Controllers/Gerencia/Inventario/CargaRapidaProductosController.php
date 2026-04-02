<?php

namespace App\Http\Controllers\Gerencia\Inventario;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class CargaRapidaProductosController extends Controller
{
    public function index()
    {
        return redirect()
            ->route('inventario.carga_rapida.index')
            ->with('success', 'La carga híbrida anterior fue reemplazada por la nueva carga rápida de inventario.');
    }

    public function procesar(Request $request)
    {
        return redirect()
            ->route('inventario.carga_rapida.index')
            ->with('error', 'La carga híbrida anterior ya no está disponible. Usa la nueva interfaz de inventario.');
    }
}
