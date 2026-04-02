@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Panel Técnico')

@section('content')
<div class="flex-1 overflow-y-auto relative">

    {{-- TARJETAS RESUMEN SUPERIORES --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Servicios asignados este mes --}}
        <div class="bg-white rounded-xl p-6 text-center shadow-sm border border-slate-200">
            <div class="text-4xl font-bold text-blue-600">
                {{ $asignadosMes ?? 0 }}
            </div>
            <div class="text-sm font-semibold text-slate-800 mt-2">
                Servicios asignados este mes
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Contabiliza los servicios que te asignan en el mes actual.
            </p>
        </div>

        {{-- Servicios completados este mes --}}
        <div class="bg-white rounded-xl p-6 text-center shadow-sm border border-slate-200">
            <div class="text-4xl font-bold text-emerald-600">
                {{ $completadosMes ?? 0 }}
            </div>
            <div class="text-sm font-semibold text-slate-800 mt-2">
                Servicios completados este mes
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Se reinicia automáticamente cada inicio de mes.
            </p>
        </div>
    </div>

    {{-- LISTA DE ÓRDENES ASIGNADAS (PENDIENTES) --}}
    <div class="mt-8">
        <h2 class="text-lg font-bold text-slate-800 mb-4">
            Servicios asignados pendientes
        </h2>

        @php
            $coleccion = $ordenes ?? collect();
        @endphp

        @if($coleccion->isEmpty())
            <div class="bg-white rounded-xl p-6 border border-slate-200 text-sm text-slate-600">
                No tienes servicios pendientes asignados en este momento.
            </div>
        @else
            <div class="space-y-5">
                @foreach($coleccion as $orden)
                    @php
                        $prioridadRaw = $orden->prioridad ?? 'Media';
                        $prioridad    = strtolower($prioridadRaw);

                        $bgCard      = 'bg-slate-50';
                        $borderCard  = 'border-slate-200';
                        $dotColor    = 'bg-slate-400';
                        $badgeBg     = 'bg-slate-100';
                        $badgeText   = 'text-slate-700';

                        switch ($prioridad) {
                            case 'baja':
                                $bgCard     = 'bg-emerald-50';
                                $borderCard = 'border-emerald-200';
                                $dotColor   = 'bg-emerald-500';
                                $badgeBg    = 'bg-emerald-100';
                                $badgeText  = 'text-emerald-700';
                                break;

                            case 'media':
                                $bgCard     = 'bg-yellow-50';
                                $borderCard = 'border-yellow-200';
                                $dotColor   = 'bg-yellow-400';
                                $badgeBg    = 'bg-yellow-100';
                                $badgeText  = 'text-yellow-700';
                                break;

                            case 'alta':
                                $bgCard     = 'bg-orange-50';
                                $borderCard = 'border-orange-200';
                                $dotColor   = 'bg-orange-500';
                                $badgeBg    = 'bg-orange-100';
                                $badgeText  = 'text-orange-700';
                                break;

                            case 'urgente':
                                $bgCard     = 'bg-red-50';
                                $borderCard = 'border-red-200';
                                $dotColor   = 'bg-red-500';
                                $badgeBg    = 'bg-red-100';
                                $badgeText  = 'text-red-700';
                                break;
                        }

                        $colFechaAsign = $colFecha ?? 'fecha_orden';
                        $fechaAsignRaw = $orden->{$colFechaAsign} ?? $orden->created_at;
                        $fechaAsign    = $fechaAsignRaw
                            ? \Carbon\Carbon::parse($fechaAsignRaw)->format('d/m/Y')
                            : '—';

                        $estadoRaw = $orden->estado ?? 'Pendiente';
                        $estadoTxt = ucfirst(str_replace('_', ' ', strtolower($estadoRaw)));

                        $clienteNombre = optional($orden->cliente)->nombre
                            ?? $orden->nombre_cliente
                            ?? optional($orden->cliente)->nombre_empresa
                            ?? 'Sin cliente';

                        $oid = $orden->id_orden_servicio ?? $orden->id;
                    @endphp

                    <div class="service-card rounded-xl p-6 shadow-sm border {{ $borderCard }} {{ $bgCard }}">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">

                            {{-- Izquierda: encabezado y cliente --}}
                            <div class="space-y-2">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">
                                        ORDEN DE SERVICIO #OS-{{ $oid }}
                                    </h3>
                                    <div class="text-xs font-semibold text-slate-600 mt-1">
                                        {{ strtoupper($orden->tipo_orden ?? 'SERVICIO') }}
                                    </div>
                                </div>

                                <div class="flex items-center text-xs text-slate-600">
                                    <div class="w-3 h-3 rounded-full mr-2 {{ $dotColor }}"></div>
                                    <span class="font-medium">
                                        {{ $estadoTxt }}
                                    </span>
                                    <span class="mx-2 text-slate-400">•</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase {{ $badgeBg }} {{ $badgeText }}">
                                        Prioridad {{ $prioridadRaw }}
                                    </span>
                                </div>

                                <div class="text-sm text-slate-700 mt-2">
                                    <span class="font-semibold">Cliente:</span>
                                    <span class="ml-1">
                                        {{ $clienteNombre }}
                                    </span>
                                </div>
                            </div>

                            {{-- Derecha: fecha y acciones --}}
                            <div class="flex flex-col items-start md:items-end gap-2 text-sm">
                                <div class="text-right">
                                    <div class="text-xs font-semibold text-slate-600">
                                        Fecha asignación:
                                    </div>
                                    <div class="text-xs text-slate-700">
                                        {{ $fechaAsign }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 mt-2 md:mt-4">
                                    {{-- Ver detalles de la orden correcta --}}
                                    <a href="{{ route('tecnico.detalles', ['orden' => $oid]) }}"
                                       class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-xs px-4 py-2 rounded-full transition">
                                        <i class="fas fa-eye mr-1"></i>
                                        Ver detalles
                                    </a>

                                    {{-- Ir directo al acta de conformidad de esa orden --}}
                                    <a href="{{ route('tecnico.ordenes.acta.vista', $oid) }}"
                                       class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-4 py-2 rounded-full transition">
                                        <i class="fas fa-check mr-1"></i>
                                        Acta de conformidad
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Descripción --}}
                        <div class="mt-4 text-sm text-slate-700">
                            <div class="font-semibold">Descripción:</div>
                            <p class="mt-1">
                                {{ $orden->descripcion_servicio ?? $orden->descripcion ?? 'Sin descripción registrada.' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
