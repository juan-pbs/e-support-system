<?php

namespace App\Http\Controllers\Tecnico;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class TecnicoController extends Controller
{
    // Vista principal del técnico
    public function index()
    {
        return view('tecnico.dashboard');
    }

    // Historial de servicios del técnico
    public function historial()
    {
        return view('tecnico.historial.index');
    }

    // Contratos asignados al técnico
    public function contratos()
    {
        return view('tecnico.contratos.index');
    }
}
