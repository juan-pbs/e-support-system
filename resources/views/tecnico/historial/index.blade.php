@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Historial de servicios')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800">Historial de servicios</h1>
        <p class="mt-2 text-sm text-slate-600">
            Esta vista quedó preparada dentro de la nueva estructura para concentrar aquí el historial del técnico.
        </p>
        <p class="mt-4 text-sm text-slate-500">
            Cuando se conecte la fuente de datos correspondiente, este módulo podrá mostrar servicios cerrados, fechas y evidencias.
        </p>
    </div>
</div>
@endsection
