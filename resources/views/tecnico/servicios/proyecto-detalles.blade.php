@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Detalle del proyecto')

@section('content')
@php
    $folio = $orden->folio ?? ('ORD-' . str_pad((string) ($orden->id_orden_servicio ?? $orden->getKey()), 5, '0', STR_PAD_LEFT));
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Detalle del proyecto</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Orden {{ $folio }} lista para desglosar etapas, fechas y seguimiento técnico.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                Cliente: {{ optional($orden->cliente)->nombre ?? optional($orden->cliente)->nombre_empresa ?? 'Sin cliente' }}
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="font-semibold text-slate-700">Fecha programada</div>
                <div class="mt-1 text-slate-600">
                    {{ $colFechaProgramada ? data_get($orden, $colFechaProgramada) : 'No definida' }}
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="font-semibold text-slate-700">Fecha base de la orden</div>
                <div class="mt-1 text-slate-600">
                    {{ $colFechaOrden ? data_get($orden, $colFechaOrden) : 'No definida' }}
                </div>
            </div>
        </div>

        <p class="mt-6 text-sm text-slate-500">
            Esta pantalla se agregó para que el módulo técnico ya tenga su ubicación definitiva dentro de la estructura nueva, aunque todavía no existiera una vista concreta en el proyecto original.
        </p>
    </div>
</div>
@endsection
