@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Proyecto técnico')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-slate-800">Proyecto técnico</h1>
        <p class="mt-2 text-sm text-slate-600">
            La vista del proyecto quedó separada dentro de `tecnico/servicios` para mantener juntas todas las pantallas operativas del técnico.
        </p>
        <p class="mt-4 text-sm text-slate-500">
            Desde aquí se puede extender el flujo con cronogramas, avances y responsables por etapa.
        </p>
    </div>
</div>
@endsection
