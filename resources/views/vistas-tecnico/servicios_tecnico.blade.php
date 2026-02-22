@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Servicios asignados')

@section('content')
@php
    $acUrl = \Illuminate\Support\Facades\Route::has('tecnico.servicios.autocomplete')
        ? route('tecnico.servicios.autocomplete')
        : url('/tecnico/servicios/autocomplete');

    $buscar  = $filtros['buscar'] ?? request('buscar');
    $ordenId = $filtros['orden_id'] ?? request('orden_id');
@endphp

<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    {{-- Header responsive --}}
    <div class="flex items-center gap-3 mb-6">
        <x-boton-volver />
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 flex-1 text-center md:text-left">
            Servicios asignados
        </h1>
        <div class="w-8 md:hidden"></div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('tecnico.servicios') }}" class="mb-5" id="form-servicios">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                {{-- Buscar --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Buscar</label>

                    <div class="relative w-full">
                        <input
                            type="text"
                            id="buscar-servicios"
                            name="buscar"
                            placeholder="OS-#, cliente, servicio o descripción…"
                            autocomplete="off"
                            value="{{ $buscar ?? '' }}"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >

                        <input
                            type="hidden"
                            id="buscar-servicios-id"
                            name="orden_id"
                            value="{{ $ordenId ?? '' }}"
                        >

                        <ul
                            id="resultados-servicios"
                            class="absolute z-[9999] w-full bg-white border border-gray-200 rounded-lg mt-1 hidden shadow-lg text-sm max-h-56 overflow-y-auto"
                        ></ul>
                    </div>

                    <p class="text-[11px] text-gray-500 mt-1">
                        Si eliges una sugerencia, filtra exacto. Si escribes, busca por texto.
                    </p>
                </div>

                {{-- Estado --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
                    @php
                        $estadoSel = $filtros['estado'] ?? request('estado', '');
                    @endphp
                    <select name="estado"
                            class="w-full border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="pendiente"  {{ $estadoSel === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                        <option value="completada" {{ $estadoSel === 'completada' ? 'selected' : '' }}>Completada</option>
                        <option value="cancelada"  {{ $estadoSel === 'cancelada'  ? 'selected' : '' }}>Cancelada</option>
                    </select>
                </div>

                {{-- Fecha desde --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha desde</label>
                    <input type="date"
                           name="desde"
                           value="{{ $filtros['desde'] ?? request('desde') }}"
                           class="w-full border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Botones --}}
                <div class="md:col-span-4 flex flex-col md:flex-row gap-2">
                    <button type="submit"
                            class="w-full md:w-auto inline-flex items-center justify-center gap-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 shadow-sm">
                        <span>Aplicar filtros</span>
                    </button>

                    <a href="{{ route('tecnico.servicios') }}"
                       class="w-full md:w-auto inline-flex items-center justify-center rounded-lg border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 hover:bg-gray-50">
                        Limpiar
                    </a>
                </div>

            </div>
        </div>
    </form>

    {{-- Contenedor de resultados --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-gray-200 gap-2">
            <h2 class="text-base md:text-lg font-semibold text-gray-800">
                Servicios asignados
            </h2>

            @if($servicios instanceof \Illuminate\Pagination\LengthAwarePaginator && $servicios->total() > 0)
                <p class="text-xs text-gray-500">
                    Mostrando {{ $servicios->firstItem() }}–{{ $servicios->lastItem() }} de {{ $servicios->total() }} registros
                </p>
            @endif
        </div>

        {{-- ====== MÓVIL/TABLET: TARJETAS (hasta <lg) ====== --}}
        <div class="p-4 space-y-3 lg:hidden">
            @forelse($servicios as $orden)
                @php
                    $estadoNorm = strtolower($orden->estado_normalizado ?? ($orden->estado ?? ''));
                    $actaEstado = strtolower($orden->acta_estado ?? '');

                    $estadoClasses = match(true) {
                        str_contains($estadoNorm, 'pend')      => 'bg-gray-100 text-gray-800',
                        str_contains($estadoNorm, 'cancel')    => 'bg-red-100 text-red-700',
                        str_contains($estadoNorm, 'complet')   => 'bg-emerald-100 text-emerald-700',
                        default                                => 'bg-slate-100 text-slate-700',
                    };

                    $actaLabel = $orden->acta_label ?? 'Sin acta';
                    $actaClasses = match($actaEstado) {
                        'firmada'  => 'bg-emerald-100 text-emerald-700',
                        'borrador' => 'bg-amber-100 text-amber-800',
                        default    => 'bg-slate-100 text-slate-700',
                    };

                    $fechaHora = $orden->fecha_hora_servicio ?? null;
                    $ordenIdForRoute = $orden->id_orden_servicio ?? $orden->id;
                    $osLabel = $orden->os_label ?? ($ordenIdForRoute ? ('OS-' . $ordenIdForRoute) : '—');
                @endphp

                <div class="border border-gray-200 rounded-xl p-4 shadow-sm bg-white">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500">ID orden</p>
                            <p class="text-base font-semibold text-gray-900 truncate">{{ $osLabel }}</p>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $estadoClasses }}">
                                    {{ ucfirst($orden->estado_normalizado ?? ($orden->estado ?? '—')) }}
                                </span>

                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $actaClasses }}">
                                    {{ $actaLabel }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-3">
                        <div class="flex items-center justify-between text-sm gap-3">
                            <span class="text-gray-500">Fecha y hora</span>
                            <span class="font-medium text-gray-800 tabular-nums">
                                @if($fechaHora)
                                    {{ \Carbon\Carbon::parse($fechaHora)->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ route('tecnico.detalles', ['orden' => $ordenIdForRoute]) }}"
                           class="bg-white hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg border inline-flex items-center justify-center text-sm font-medium">
                            Detalles
                        </a>

                        <a href="{{ route('tecnico.ordenes.acta.vista', ['id' => $ordenIdForRoute]) }}"
                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center text-sm font-medium">
                            Acta
                        </a>
                    </div>
                </div>
            @empty
                <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500">
                    No hay servicios asignados con los filtros seleccionados.
                </div>
            @endforelse
        </div>

        {{-- ====== ESCRITORIO: TABLA (lg y arriba) ====== --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-gray-600">
                        <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">ID orden</th>
                        <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Estatus</th>
                        <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Acta</th>
                        <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Fecha y hora</th>
                        <th class="px-4 py-2 text-left font-semibold whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($servicios as $orden)
                        @php
                            $estadoNorm = strtolower($orden->estado_normalizado ?? ($orden->estado ?? ''));
                            $actaEstado = strtolower($orden->acta_estado ?? '');

                            $estadoClasses = match(true) {
                                str_contains($estadoNorm, 'pend')      => 'bg-gray-100 text-gray-800',
                                str_contains($estadoNorm, 'cancel')    => 'bg-red-100 text-red-700',
                                str_contains($estadoNorm, 'complet')   => 'bg-emerald-100 text-emerald-700',
                                default                                => 'bg-slate-100 text-slate-700',
                            };

                            $actaLabel = $orden->acta_label ?? 'Sin acta';
                            $actaClasses = match($actaEstado) {
                                'firmada'  => 'bg-emerald-100 text-emerald-700',
                                'borrador' => 'bg-amber-100 text-amber-800',
                                default    => 'bg-slate-100 text-slate-700',
                            };

                            $fechaHora = $orden->fecha_hora_servicio ?? null;
                            $ordenIdForRoute = $orden->id_orden_servicio ?? $orden->id;
                            $osLabel = $orden->os_label ?? ($ordenIdForRoute ? ('OS-' . $ordenIdForRoute) : '—');
                        @endphp

                        <tr class="border-t border-gray-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2 whitespace-nowrap font-semibold text-gray-800">
                                {{ $osLabel }}
                            </td>

                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $estadoClasses }}">
                                    {{ ucfirst($orden->estado_normalizado ?? ($orden->estado ?? '—')) }}
                                </span>
                            </td>

                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $actaClasses }}">
                                    {{ $actaLabel }}
                                </span>
                            </td>

                            <td class="px-4 py-2 whitespace-nowrap text-gray-800 tabular-nums">
                                @if($fechaHora)
                                    {{ \Carbon\Carbon::parse($fechaHora)->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('tecnico.detalles', ['orden' => $ordenIdForRoute]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg border text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Detalles
                                    </a>

                                    <a href="{{ route('tecnico.ordenes.acta.vista', ['id' => $ordenIdForRoute]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-xs font-medium text-white">
                                        Acta
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                No hay servicios asignados con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if($servicios instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="px-4 py-3 border-t border-gray-200 bg-slate-50">
                {{ $servicios->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const acUrl      = @json($acUrl);
    const input      = document.getElementById('buscar-servicios');
    const hiddenId   = document.getElementById('buscar-servicios-id');
    const resultados = document.getElementById('resultados-servicios');
    const form       = document.getElementById('form-servicios');

    let lastReq = 0;

    function hideResults() {
        if (!resultados) return;
        resultados.innerHTML = '';
        resultados.classList.add('hidden');
    }

    function showResults() {
        if (!resultados) return;
        resultados.classList.remove('hidden');
    }

    if (!input || !hiddenId || !resultados) return;

    input.addEventListener('input', () => {
        hiddenId.value = '';

        const termino = input.value.trim();
        hideResults();

        if (termino.length < 2) return;

        const reqId = ++lastReq;

        fetch(`${acUrl}?term=${encodeURIComponent(termino)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (reqId !== lastReq) return;

            if (Array.isArray(data) && data.length) {
                showResults();
                resultados.innerHTML = '';

                data.forEach(item => {
                    const label = item.label ?? item.text ?? item.nombre ?? '';
                    if (!label) return;

                    const li = document.createElement('li');
                    li.textContent = label;
                    li.className = 'px-4 py-2 cursor-pointer hover:bg-blue-100';

                    li.addEventListener('click', (ev) => {
                        ev.preventDefault();
                        ev.stopPropagation();

                        input.value = label;
                        hiddenId.value = item.id ?? '';

                        hideResults();

                        if (form) form.submit();
                    });

                    resultados.appendChild(li);
                });
            } else {
                hideResults();
            }
        })
        .catch(() => hideResults());
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !resultados.contains(e.target)) hideResults();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideResults();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && form) {
            e.preventDefault();
            form.submit();
        }
    });
});
</script>
@endpush
