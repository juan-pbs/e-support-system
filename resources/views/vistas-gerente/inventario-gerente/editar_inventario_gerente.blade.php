@extends('layouts.sidebar-navigation')

@section('title', 'Editar entrada')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <x-boton-volver />
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 text-center flex-1">
            Editar entrada
        </h1>
        <div class="w-8"></div>
    </div>

    {{-- Alertas --}}
    @foreach (['success','error'] as $k)
        @if (session($k))
            <div class="mb-4 px-4 py-3 rounded-lg border
                {{ $k==='success' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300' }}">
                {{ session($k) }}
            </div>
        @endif
    @endforeach

    {{-- Errores --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Card --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">

        {{-- Info fija --}}
        <div class="mb-5 text-sm text-gray-700 bg-gray-50 border rounded-lg p-4">
            <div class="mb-1">
                <strong>Producto:</strong>
                {{ $entrada->producto->nombre ?? '—' }}
                <span class="text-gray-500">
                    ({{ $entrada->producto->numero_parte ?? '—' }})
                </span>
            </div>

            <div class="mb-1">
                <strong>Proveedor:</strong> {{ $entrada->proveedor->nombre ?? '—' }}
            </div>

            <div class="mb-1">
                <strong>Tipo de control:</strong> {{ $entrada->tipo_control ?? '—' }}
            </div>

            <div class="text-gray-500 mt-2">
                <strong>Fecha/Hora de entrada:</strong>
                {{ $entrada->fecha_entrada ?? '—' }} {{ $entrada->hora_entrada ?? '' }}
            </div>
        </div>

        {{-- Form --}}
        <form action="{{ route('inventario.actualizar', $entrada->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Costo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Costo <span class="text-red-600">*</span>
                    </label>
                    <input
                        type="number"
                        name="costo"
                        step="0.01"
                        min="0"
                        value="{{ old('costo', $entrada->costo) }}"
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                        required
                    >
                </div>

                {{-- Precio --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Precio (opcional)
                    </label>
                    <input
                        type="number"
                        name="precio"
                        step="0.01"
                        min="0"
                        value="{{ old('precio', $entrada->precio) }}"
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                </div>

                {{-- Fecha caducidad --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Fecha de caducidad (opcional)
                    </label>
                    <input
                        type="date"
                        name="fecha_caducidad"
                        value="{{ old('fecha_caducidad', $entrada->fecha_caducidad) }}"
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        Debe ser igual o posterior a la fecha de entrada.
                    </p>
                </div>
            </div>

            {{-- ✅ SERIE editable --}}
            @if(($entrada->tipo_control ?? '') === 'SERIE')
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Números de serie (uno por línea) <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        name="numeros_serie"
                        rows="7"
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-blue-200"
                        required
                    >{{ old('numeros_serie', isset($seriesEntrada) ? $seriesEntrada->implode("\n") : '') }}</textarea>

                    <p class="text-xs text-gray-500 mt-1">
                        Puedes agregar, quitar o modificar. No se permiten duplicados ni series ya usadas en otro producto/entrada.
                    </p>
                </div>
            @endif

            {{-- Acciones --}}
            <div class="flex justify-end gap-3 pt-3">
                <a href="{{ route('inventario') }}" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
