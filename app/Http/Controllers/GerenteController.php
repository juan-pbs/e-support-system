<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\OrdenServicio;

class GerenteController extends Controller
{
    public function index()
    {
        // Total productos
        $totalProductos = Producto::count();

        // Total clientes
        $totalClientes = Cliente::count();

        /**
         * Cotizaciones NO procesadas:
         *  - Son las cotizaciones que NO tienen orden de servicio ligada.
         *  - Requiere la relación ordenServicio() en el modelo Cotizacion.
         */
        $totalCotizaciones = Cotizacion::whereDoesntHave('ordenServicio')->count();

        /**
         * Órdenes de servicio SIN acta de conformidad firmada:
         *  - acta_estado = 'firmada'  -> NO se cuentan
         *  - acta_estado NULL o distinto de 'firmada' -> SÍ se cuentan
         */
        $totalOrdenes = OrdenServicio::where(function ($q) {
            $q->whereNull('acta_estado')
              ->orWhere('acta_estado', '!=', 'firmada');
        })->count();

        return view('vistas-gerente.index', compact(
            'totalProductos',
            'totalClientes',
            'totalCotizaciones',
            'totalOrdenes'
        ));
    }
}
