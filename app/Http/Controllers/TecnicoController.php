<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TecnicoController extends Controller
{
    // Vista principal del técnico
    public function index()
    {
        return view('vistas-tecnico.inicio_tecnico');
    }

    // Historial de servicios del técnico
    public function historial()
    {
        return view('vistas-tecnico.historial_servicios_tecnico');
    }

    // Contratos asignados al técnico
    public function contratos()
    {
        return view('vistas-tecnico.contratos_tecnico');
    }
}
