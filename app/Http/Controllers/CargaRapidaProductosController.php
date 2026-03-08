<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CargaRapidaProductosController extends Controller
{
    public function index()
    {
        return redirect()
            ->route('inventario.carga_rapida.index')
            ->with('success', 'La carga híbrida fue reemplazada por la nueva interfaz de carga rápida de inventario.');
    }

    public function procesar(Request $request)
    {
        return redirect()
            ->route('inventario.carga_rapida.index')
            ->with('error', 'La carga híbrida anterior ya no está disponible. Usa la nueva interfaz de inventario.');
    }
}
