<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\OrdenServicio;

class AdminController extends Controller
{
    public function index()
    {
        // Contadores simples (no dependen del historial de inventario)
        $totalProductos    = Producto::count();
        $totalClientes     = Cliente::count();
        $totalCotizaciones = Cotizacion::count();
        $totalOrdenes      = OrdenServicio::count();

        return view('vistas-gerente.index', compact(
            'totalProductos',
            'totalClientes',
            'totalCotizaciones',
            'totalOrdenes'
        ));
    }
}
