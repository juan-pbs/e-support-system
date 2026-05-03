@extends('layouts.sidebar-navigation')

@section('title', 'Mantenimiento por secciones')

@section('content')
<div class="max-w-7xl mx-auto px-0 sm:px-6 lg:px-8 py-4">
    <div class="flex flex-col items-start sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex flex-col items-start sm:flex-row sm:items-center gap-3 min-w-0">
            <x-boton-volver />
            <div class="min-w-0">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">Mantenimiento por secciones</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Deshabilita modulos especificos sin apagar todo el sistema.
                </p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('sistema.mantenimiento.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
            Los usuarios con rol <strong>Sistema</strong> pueden seguir entrando a las secciones bloqueadas para revisar o reactivar el servicio.
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($sections as $section)
                <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $section->name }}</h2>
                            <p class="mt-1 text-xs text-gray-500 break-words">
                                Rutas: {{ implode(', ', (array) $section->paths) }}
                            </p>
                        </div>

                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="hidden" name="sections[{{ $section->key }}][enabled]" value="0">
                            <input type="checkbox"
                                   name="sections[{{ $section->key }}][enabled]"
                                   value="1"
                                   class="peer sr-only"
                                   @checked($section->enabled)>
                            <div class="h-7 w-12 rounded-full bg-gray-200 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>

                    <label class="mt-4 block">
                        <span class="text-sm font-medium text-gray-700">Mensaje para el usuario</span>
                        <textarea name="sections[{{ $section->key }}][message]"
                                  rows="2"
                                  class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old("sections.{$section->key}.message", $section->message) }}</textarea>
                    </label>

                    <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                        <span class="inline-flex rounded-full px-2.5 py-1 font-semibold {{ $section->enabled ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $section->enabled ? 'En mantenimiento' : 'Disponible' }}
                        </span>
                        <span class="text-gray-400">
                            Actualizado: {{ optional($section->updated_at)->format('d/m/Y H:i') ?: 'Sin cambios' }}
                        </span>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="sticky bottom-0 z-10 -mx-0 sm:-mx-6 lg:-mx-8 border-t border-gray-200 bg-white/95 px-0 sm:px-6 lg:px-8 py-3 backdrop-blur">
            <button type="submit"
                    class="w-full sm:w-auto rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Guardar configuracion
            </button>
        </div>
    </form>
</div>
@endsection
