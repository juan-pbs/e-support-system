@extends('layouts.sidebar-navigation')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    {{-- Encabezado responsive --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-center gap-3">
            <x-boton-volver />
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800">
                Entradas de Inventario
            </h1>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
            <a href="{{ route('entrada') }}"
               class="w-full sm:w-auto text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Nueva entrada
            </a>
            <a href="{{ route('cargaRapidaProd.index') }}"
               class="w-full sm:w-auto text-center bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg">
                Carga rápida
            </a>
        </div>
    </div>

    {{-- Alertas --}}
    @foreach (['success','error'] as $k)
        @if (session($k))
            <div class="mb-4 px-4 py-3 rounded-lg {{ $k==='success' ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300' }}">
                {{ session($k) }}
            </div>
        @endif
    @endforeach

    {{-- Filtros responsive --}}
    <form method="GET" class="mb-4" action="{{ route('inventario') }}">
        <div class="bg-white rounded-xl border p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">

            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>

                <x-barra-busqueda-autocomplete
                    autocompleteUrl="{{ route('entrada.autocomplete') }}"
                    placeholder="Producto / No. parte..."
                    inputId="buscar-entradas"
                    resultId="resultados-entradas"
                    name="buscar"
                    idName="codigo_producto"
                    :value="request('buscar')"
                    :idValue="request('codigo_producto')"
                />

                <p class="text-[11px] text-gray-500 mt-1">
                    Si seleccionas un producto del listado, se filtra exacto.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de control</label>
                <select name="tipo_control" class="border px-3 py-2 rounded-lg w-full">
                    <option value="">Todos</option>
                    @foreach(['PIEZAS','PAQUETES','SERIE'] as $t)
                        <option value="{{ $t }}" @selected(request('tipo_control')===$t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 lg:justify-end">
                <button class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Aplicar
                </button>

                <a href="{{ route('inventario') }}"
                   class="w-full sm:w-auto text-center bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg">
                    Limpiar
                </a>
            </div>

        </div>
    </form>

    {{-- ========================= --}}
    {{-- VISTA MÓVIL: TARJETAS --}}
    {{-- ========================= --}}
    <div class="md:hidden space-y-3">
        @forelse($entradas as $e)
            @php
                $fechaHora = $e->created_at
                    ? \Carbon\Carbon::parse($e->created_at)
                    : \Carbon\Carbon::parse(($e->fecha_entrada ?? now()->toDateString()) . ' ' . ($e->hora_entrada ?? '00:00:00'));

                $canModify = $fechaHora->copy()->addHours(24)->isFuture();

                if ($e->tipo_control === 'PAQUETES') {
                    $cantidadTxt = ($e->paquetes_restantes ?? 0).' pqt × '.($e->piezas_por_paquete ?? 0)
                        .' = '.(($e->paquetes_restantes ?? 0) * ($e->piezas_por_paquete ?? 0));
                } elseif ($e->tipo_control === 'SERIE') {
                    $cantidadTxt = '1';
                } else {
                    $cantidadTxt = (string) ($e->piezas_sueltas ?? 0);
                }
            @endphp

            <div class="bg-white border rounded-2xl p-4 shadow-sm">
                {{-- Encabezado --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 break-words">
                            {{ $e->producto->nombre ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-500 break-words mt-1">
                            {{ $e->producto->numero_parte ?? '' }}
                        </div>
                        <div class="text-xs text-gray-500 break-words">
                            Proveedor: <span class="text-gray-700">{{ $e->proveedor->nombre ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-sm font-semibold text-gray-900">
                            {{ \Carbon\Carbon::parse($e->fecha_entrada)->format('d/m/Y') }}
                        </div>
                        @if(!empty($e->hora_entrada))
                            <div class="text-xs text-gray-500">{{ $e->hora_entrada }}</div>
                        @endif
                    </div>
                </div>

                {{-- Datos --}}
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Tipo</span>
                        <span class="font-medium text-gray-900">{{ $e->tipo_control }}</span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Cantidad</span>
                        <span class="font-medium text-gray-900 break-words text-right">{{ $cantidadTxt }}</span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Costo</span>
                        <span class="font-medium text-gray-900">${{ number_format($e->costo, 2) }}</span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Precio</span>
                        <span class="font-medium text-gray-900">${{ number_format($e->precio, 2) }}</span>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="mt-4">
                    @if($canModify)
                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ route('inventario.editar', $e->id) }}"
                               class="text-center px-3 py-2 rounded-lg border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100">
                                Editar
                            </a>

                            <form action="{{ route('inventario.eliminar', $e->id) }}"
                                  method="POST"
                                  onsubmit="return confirm('¿Eliminar esta entrada?');">
                                @csrf
                                @method('DELETE')
                                <button class="w-full px-3 py-2 rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="text-center text-sm text-gray-400 bg-gray-50 border rounded-lg px-3 py-2"
                             title="Solo se puede modificar dentro de las primeras 24 horas">
                            Bloqueado (24h)
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-6 text-center text-gray-500">
                Sin entradas.
            </div>
        @endforelse
    </div>

    {{-- ========================= --}}
    {{-- VISTA DESKTOP: TABLA --}}
    {{-- ========================= --}}
    <div class="hidden md:block bg-white border rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-3 py-2">Fecha</th>
                        <th class="text-left px-3 py-2">Producto</th>
                        <th class="text-left px-3 py-2">Proveedor</th>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-right px-3 py-2">Cant</th>
                        <th class="text-right px-3 py-2">Costo</th>
                        <th class="text-right px-3 py-2">Precio</th>
                        <th class="text-center px-3 py-2">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($entradas as $e)
                        @php
                            $fechaHora = $e->created_at
                                ? \Carbon\Carbon::parse($e->created_at)
                                : \Carbon\Carbon::parse(($e->fecha_entrada ?? now()->toDateString()) . ' ' . ($e->hora_entrada ?? '00:00:00'));

                            $canModify = $fechaHora->copy()->addHours(24)->isFuture();
                        @endphp

                        <tr class="border-b">
                            <td class="px-3 py-2">
                                {{ \Carbon\Carbon::parse($e->fecha_entrada)->format('d/m/Y') }}
                                @if(!empty($e->hora_entrada))
                                    <div class="text-xs text-gray-500">{{ $e->hora_entrada }}</div>
                                @endif
                            </td>

                            <td class="px-3 py-2">
                                {{ $e->producto->nombre ?? '—' }}
                                <div class="text-xs text-gray-500">{{ $e->producto->numero_parte ?? '' }}</div>
                            </td>

                            <td class="px-3 py-2">
                                {{ $e->proveedor->nombre ?? '—' }}
                            </td>

                            <td class="px-3 py-2">
                                {{ $e->tipo_control }}
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if($e->tipo_control==='PAQUETES')
                                    {{ $e->paquetes_restantes }} pqt × {{ $e->piezas_por_paquete }} = {{ $e->paquetes_restantes * $e->piezas_por_paquete }}
                                @elseif($e->tipo_control==='SERIE')
                                    1
                                @else
                                    {{ $e->piezas_sueltas }}
                                @endif
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                ${{ number_format($e->costo, 2) }}
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                ${{ number_format($e->precio, 2) }}
                            </td>

                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                @if($canModify)
                                    <a href="{{ route('inventario.editar', $e->id) }}" class="text-blue-600 hover:underline">
                                        Editar
                                    </a>

                                    <form action="{{ route('inventario.eliminar', $e->id) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('¿Eliminar esta entrada?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline ml-2">Eliminar</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 cursor-not-allowed" title="Solo se puede modificar dentro de las primeras 24 horas">
                                        Editar
                                    </span>
                                    <span class="text-gray-400 cursor-not-allowed ml-2" title="Solo se puede modificar dentro de las primeras 24 horas">
                                        Eliminar
                                    </span>

                                    <div class="text-[11px] text-gray-400 mt-1">
                                        Bloqueado (24h)
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                                Sin entradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $entradas->links() }}
    </div>
</div>
@endsection
