@extends('layouts.sidebar-navigation')

@section('title', 'Salidas de Inventario')

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;

    $acUrl = Route::has('entrada.autocomplete')
        ? route('entrada.autocomplete')
        : (Route::has('inventario.autocomplete')
            ? route('inventario.autocomplete')
            : url('/inventario/autocomplete'));

    $clientesSearchList = collect($clientesLista ?? [])->map(function ($cliente) {
        return [
            'clave_cliente' => (string) $cliente->clave_cliente,
            'label' => trim(($cliente->nombre ?? '') . (($cliente->nombre_empresa ?? '') ? ' - ' . $cliente->nombre_empresa : '')),
            'empresa' => (string) ($cliente->nombre_empresa ?? ''),
        ];
    })->values();

    $productosSearchList = collect($productosLista ?? [])->map(function ($producto) {
        $numeroParte = (string) ($producto->numero_parte ?? '');

        return [
            'codigo_producto' => (string) $producto->codigo_producto,
            'label' => trim(($producto->nombre ?? '') . ($numeroParte !== '' ? ' (NP: ' . $numeroParte . ')' : '')),
            'numero_parte' => $numeroParte,
            'series_disponibles' => (int) ($producto->series_disponibles ?? 0),
            'search' => Str::lower(trim(($producto->nombre ?? '') . ' ' . $numeroParte)),
        ];
    })->values();

    $clienteOldId = (string) old('id_cliente', '');
    $clienteOld = $clienteOldId !== '' ? $clientesSearchList->firstWhere('clave_cliente', $clienteOldId) : null;
    $productoOldId = (string) old('codigo_producto', '');
    $productoOld = $productoOldId !== '' ? $productosSearchList->firstWhere('codigo_producto', $productoOldId) : null;
    $reopenCreateModal = old('id_cliente') !== null
        || old('codigo_producto') !== null
        || old('cantidad') !== null
        || old('precio_unitario') !== null
        || old('tasa_cambio') !== null;
    $canEditSalidaQuantity = auth()->user() && method_exists(auth()->user(), 'hasAnyRole')
        && auth()->user()->hasAnyRole(['sistema', 'admin', 'gerente']);
@endphp

<style>[x-cloak]{display:none !important}</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="salidasUI({
        clientes: @js($clientesSearchList),
        productos: @js($productosSearchList),
        clienteId: @js($clienteOldId),
        clienteLabel: @js(data_get($clienteOld, 'label', '')),
        productoId: @js($productoOldId),
        productoLabel: @js(data_get($productoOld, 'label', '')),
        moneda: @js(old('moneda', 'MXN')),
        producto: @js(old('codigo_producto', '')),
        openCreate: @js($reopenCreateModal),
        series: @js(array_values(old('series', []))),
     })"
     x-init="init()">

    {{-- Alerts --}}
    @if (session('error'))
      <div x-data="{ show:true }" x-show="show" x-init="setTimeout(()=>show=false, 7000)"
           class="mb-4 bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg">
        <strong class="font-bold">¡Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
      </div>
    @endif

    @if (session('success'))
      <div x-data="{ show:true }" x-show="show" x-init="setTimeout(()=>show=false, 5000)"
           class="mb-4 bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-lg">
        <strong class="font-bold">¡Éxito!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
      </div>
    @endif

    {{-- Encabezado responsive --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-center gap-3">
            <x-boton-volver />
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800">
                Salidas de inventario
            </h1>
        </div>

        <button @click="showCreateModal = true; $nextTick(() => { filterClientes(); filterProductos(); })"
                class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg flex items-center justify-center gap-2">
            <i data-lucide="minus-square"></i> Registrar salida
        </button>
    </div>

    {{-- Filtros responsive --}}
    <form method="GET" action="{{ route('inventario.salidas') }}"
          class="bg-white p-4 rounded-xl border shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">

        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Producto / Cliente / OS / Cot</label>

            <x-barra-busqueda-autocomplete
                autocompleteUrl="{{ $acUrl }}"
                placeholder="Buscar producto (si eliges del listado, filtra exacto)..."
                inputId="buscar-salidas"
                resultId="resultados-salidas"
                name="buscar"
                idName="codigo_producto"
                :value="request('buscar')"
                :idValue="request('codigo_producto')"
            />

            <p class="text-[11px] text-gray-500 mt-1">
                Si eliges un producto del listado, filtra exacto por producto.
                Si escribes texto, busca por cliente/OS/cotización también.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de salida</label>
            <input type="date" name="fecha" value="{{ request('fecha') }}"
                   class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="flex flex-col sm:flex-row gap-2 lg:justify-end">
            <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Buscar
            </button>
            <a href="{{ route('inventario.salidas') }}"
               class="w-full sm:w-auto text-center bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg">
                Limpiar
            </a>
        </div>
    </form>

    {{-- ========================= --}}
    {{-- MÓVIL: TARJETAS --}}
    {{-- ========================= --}}
    <div class="md:hidden space-y-3">
        @forelse ($salidas as $item)
            @php
                $moneda = $item->moneda_detalle ?? $item->moneda_orden ?? 'MXN';

                $img = $item->imagen
                    ? (Str::startsWith($item->imagen, ['http://','https://']) ? $item->imagen : asset($item->imagen))
                    : asset('images/imagen.png');

                $series = [];
                if (!empty($item->series_concat)) {
                    $series = array_values(array_filter(array_map('trim', explode(',', $item->series_concat))));
                }

                $esManual = (is_null($item->id_orden_servicio) || ($item->tipo_orden === 'salida_manual'));

                $payload = [
                    'id_detalle'       => $item->id_detalle,
                    'codigo_producto'  => $item->codigo_producto,
                    'nombre_producto'  => $item->nombre_producto,
                    'unidad'           => $item->unidad,
                    'numero_parte'     => $item->numero_parte,
                    'descripcion'      => $item->descripcion_detalle,
                    'cantidad'         => $item->cantidad,
                    'precio_unitario'  => (float)$item->precio_unitario,
                    'total'            => (float)$item->total,
                    'moneda'           => $moneda,
                    'fecha_salida'     => \Illuminate\Support\Carbon::parse($item->fecha_salida)->format('Y-m-d H:i'),
                    'orden'            => $item->id_orden_servicio,
                    'cotizacion'       => $item->id_cotizacion,
                    'cliente'          => $item->cliente_nombre,
                    'empresa'          => $item->nombre_empresa,
                    'series'           => $series,
                    'imagen'           => $img,
                    'es_manual'        => $esManual,
                    'can_edit_quantity'=> $canEditSalidaQuantity && $esManual && empty($series),
                    'edit_url'         => route('inventario.salidas.update_cantidad', $item->id_detalle),
                ];
            @endphp

            <div class="bg-white border rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <img src="{{ $img }}" class="w-12 h-12 rounded-lg object-cover border" alt="Imagen">

                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 break-words">
                                {{ $item->nombre_producto ?? 'Producto' }}
                            </div>

                            <div class="text-xs text-gray-500 break-words mt-1">
                                @if($item->numero_parte) NP: {{ $item->numero_parte }} · @endif
                                {{ $item->unidad ?? 'piezas' }}
                            </div>

                            <div class="text-xs text-gray-500 break-words">
                                Cliente: <span class="text-gray-700">{{ $item->cliente_nombre ?: '—' }}</span>
                            </div>

                            @if($item->nombre_empresa)
                                <div class="text-xs text-gray-500 break-words">
                                    Empresa: <span class="text-gray-700">{{ $item->nombre_empresa }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-xs text-gray-500">ID: {{ $item->id_detalle }}</div>
                        <div class="text-sm font-semibold text-gray-900">
                            {{ \Illuminate\Support\Carbon::parse($item->fecha_salida)->format('Y-m-d') }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Cantidad</span>
                        <span class="font-medium text-gray-900">{{ number_format($item->cantidad, 2) }}</span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Precio unit.</span>
                        <span class="font-medium text-gray-900">
                            ${{ number_format($item->precio_unitario, 2) }} {{ $moneda }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Total</span>
                        <span class="font-semibold text-gray-900">
                            ${{ number_format($item->total, 2) }} {{ $moneda }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-3 items-center">
                        <span class="text-xs text-gray-500">Origen</span>
                        @if($esManual)
                            <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">Salida manual</span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs">OS-{{ $item->id_orden_servicio }}</span>
                        @endif
                    </div>

                    <div class="flex justify-between gap-3 items-center">
                        <span class="text-xs text-gray-500">Cot</span>
                        <span class="text-sm text-gray-800">
                            @if($item->id_cotizacion) SET-{{ $item->id_cotizacion }} @else — @endif
                        </span>
                    </div>
                </div>

                <div class="mt-4">
                    @if($payload['can_edit_quantity'])
                        <button
                            type="button"
                            @click="openEditFromTarget($event)"
                            data-payload='@json($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)'
                            class="mb-2 w-full bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg flex items-center justify-center gap-2"
                            title="Editar cantidad"
                        >
                            Editar cantidad
                        </button>
                    @endif
                    <button
                        type="button"
                        @click="openFromTarget($event)"
                        data-payload='@json($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)'
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg flex items-center justify-center gap-2"
                        title="Ver detalles"
                    >
                        <i data-lucide="eye"></i> Ver detalles
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-6 text-center text-gray-500">
                No se encontraron salidas.
            </div>
        @endforelse
    </div>

    {{-- ========================= --}}
    {{-- DESKTOP: TABLA --}}
    {{-- ========================= --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="min-w-[1100px] w-full bg-white shadow rounded-lg text-sm text-gray-700 border border-gray-200">
            <thead class="bg-blue-100 text-gray-800">
                <tr>
                    <th class="px-4 py-2">ID Detalle</th>
                    <th class="px-4 py-2">Imagen</th>
                    <th class="px-4 py-2">Producto</th>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Cantidad</th>
                    <th class="px-4 py-2">Precio Unit.</th>
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">OS / Origen</th>
                    <th class="px-4 py-2">Cot</th>
                    <th class="px-4 py-2">Fecha salida</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($salidas as $item)
                    @php
                        $moneda = $item->moneda_detalle ?? $item->moneda_orden ?? 'MXN';

                        $img = $item->imagen
                            ? (Str::startsWith($item->imagen, ['http://','https://']) ? $item->imagen : asset($item->imagen))
                            : asset('images/imagen.png');

                        $series = [];
                        if (!empty($item->series_concat)) {
                            $series = array_values(array_filter(array_map('trim', explode(',', $item->series_concat))));
                        }

                        $esManual = (is_null($item->id_orden_servicio) || ($item->tipo_orden === 'salida_manual'));

                        $payload = [
                            'id_detalle'       => $item->id_detalle,
                            'codigo_producto'  => $item->codigo_producto,
                            'nombre_producto'  => $item->nombre_producto,
                            'unidad'           => $item->unidad,
                            'numero_parte'     => $item->numero_parte,
                            'descripcion'      => $item->descripcion_detalle,
                            'cantidad'         => $item->cantidad,
                            'precio_unitario'  => (float)$item->precio_unitario,
                            'total'            => (float)$item->total,
                            'moneda'           => $moneda,
                            'fecha_salida'     => \Illuminate\Support\Carbon::parse($item->fecha_salida)->format('Y-m-d H:i'),
                            'orden'            => $item->id_orden_servicio,
                            'cotizacion'       => $item->id_cotizacion,
                            'cliente'          => $item->cliente_nombre,
                            'empresa'          => $item->nombre_empresa,
                    'series'           => $series,
                    'imagen'           => $img,
                    'es_manual'        => $esManual,
                    'can_edit_quantity'=> $canEditSalidaQuantity && $esManual && empty($series),
                    'edit_url'         => route('inventario.salidas.update_cantidad', $item->id_detalle),
                ];
                    @endphp

                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $item->id_detalle }}</td>
                        <td class="px-4 py-2">
                            <img src="{{ $img }}" class="w-12 h-12 object-cover rounded" alt="Imagen">
                        </td>

                        <td class="px-4 py-2">
                            <div class="font-medium">{{ $item->nombre_producto ?? 'Producto' }}</div>
                            <div class="text-xs text-gray-500">
                                @if($item->numero_parte) NP: {{ $item->numero_parte }} · @endif
                                {{ $item->unidad ?? 'piezas' }}
                            </div>
                        </td>

                        <td class="px-4 py-2">
                            <div>{{ $item->cliente_nombre ?: '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $item->nombre_empresa ?: '' }}</div>
                        </td>

                        <td class="px-4 py-2">{{ number_format($item->cantidad, 2) }}</td>
                        <td class="px-4 py-2">${{ number_format($item->precio_unitario, 2) }} {{ $moneda }}</td>
                        <td class="px-4 py-2">${{ number_format($item->total, 2) }} {{ $moneda }}</td>

                        <td class="px-4 py-2">
                            @if($esManual)
                                <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">Salida manual</span>
                            @else
                                OS-{{ $item->id_orden_servicio }}
                            @endif
                        </td>

                        <td class="px-4 py-2">
                            @if($item->id_cotizacion) SET-{{ $item->id_cotizacion }} @else — @endif
                        </td>

                        <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($item->fecha_salida)->format('Y-m-d') }}</td>

                        <td class="px-4 py-2">
                            @if($payload['can_edit_quantity'])
                                <button
                                    type="button"
                                    @click="openEditFromTarget($event)"
                                    data-payload='@json($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)'
                                    class="bg-amber-500 hover:bg-amber-600 text-white px-2 py-1 rounded mr-1"
                                    title="Editar cantidad"
                                >
                                    Editar
                                </button>
                            @endif
                            <button
                                type="button"
                                @click="openFromTarget($event)"
                                data-payload='@json($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)'
                                class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded"
                                title="Ver detalles"
                            >
                                <i data-lucide="eye"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center py-4 text-gray-500">No se encontraron salidas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($salidas instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="mt-6">
            {{ $salidas->links('pagination::tailwind') }}
        </div>
    @endif

    {{-- ========================= --}}
    {{-- MODAL: DETALLES --}}
    {{-- ========================= --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 bg-black/50 p-4 flex items-center justify-center">
        <div @click.outside="showModal = false"
             class="bg-white rounded-xl w-full max-w-xl max-h-[90vh] overflow-y-auto shadow-lg p-5">

            <h2 class="text-xl font-bold mb-4">Detalle de salida</h2>

            <div class="flex items-start gap-4 mb-4">
                <img :src="selected.imagen" class="w-16 h-16 object-cover rounded border" alt="Imagen">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold break-words" x-text="selected.nombre_producto || 'Producto'"></div>
                    <div class="text-xs text-gray-500 break-words">
                        <template x-if="selected.numero_parte">
                            <span>NP: <span x-text="selected.numero_parte"></span> · </span>
                        </template>
                        <span x-text="selected.unidad || 'piezas'"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><strong>Origen:</strong>
                    <span class="px-2 py-0.5 rounded"
                          :class="selected.es_manual ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                        <span x-text="selected.es_manual ? 'Salida manual' : ('OS-' + selected.orden)"></span>
                    </span>
                </div>

                <div><strong>Cotización:</strong>
                    <template x-if="selected.cotizacion"><span>SET-<span x-text="selected.cotizacion"></span></span></template>
                    <template x-if="!selected.cotizacion"><span>—</span></template>
                </div>

                <div><strong>Cliente:</strong> <span x-text="selected.cliente || '—'"></span></div>
                <div><strong>Empresa:</strong> <span x-text="selected.empresa || '—'"></span></div>

                <div><strong>Cantidad:</strong> <span x-text="Number(selected.cantidad).toFixed(2)"></span></div>
                <div><strong>Precio unitario:</strong> $<span x-text="Number(selected.precio_unitario).toFixed(2)"></span> <span x-text="selected.moneda"></span></div>

                <div><strong>Total:</strong> $<span x-text="Number(selected.total).toFixed(2)"></span> <span x-text="selected.moneda"></span></div>
                <div><strong>Fecha salida:</strong> <span x-text="selected.fecha_salida"></span></div>
            </div>

            <template x-if="selected.series && selected.series.length">
                <div class="mt-4">
                    <strong class="text-sm">Números de serie:</strong>
                    <ul class="list-disc list-inside text-sm text-gray-700 max-h-40 overflow-y-auto mt-1">
                        <template x-for="(ns, idx) in selected.series" :key="idx">
                            <li class="break-words" x-text="ns"></li>
                        </template>
                    </ul>
                </div>
            </template>

            <div class="mt-6 text-end">
                <button @click="showModal = false" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    {{-- ========================= --}}
    {{-- MODAL: EDITAR CANTIDAD --}}
    {{-- ========================= --}}
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 bg-black/50 p-4 flex items-center justify-center">
        <div @click.outside="showEditModal = false"
             class="bg-white rounded-xl w-full max-w-md shadow-lg p-5">
            <h2 class="text-xl font-bold mb-4">Editar cantidad de salida</h2>

            <div class="mb-4 text-sm text-gray-700">
                <div class="font-semibold" x-text="editSelected.nombre_producto || 'Producto'"></div>
                <div class="text-xs text-gray-500">
                    Cantidad actual: <span x-text="Number(editSelected.cantidad || 0).toFixed(2)"></span>
                </div>
            </div>

            <form :action="editAction" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nueva cantidad</label>
                    <input type="number"
                           name="cantidad"
                           min="1"
                           step="1"
                           x-model="editQuantity"
                           required
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">
                        Si reduces la cantidad, la diferencia vuelve al inventario. Si aumentas, se consume stock disponible.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button"
                            @click="showEditModal = false"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg">
                        Guardar cantidad
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================= --}}
    {{-- MODAL: REGISTRAR SALIDA --}}
    {{-- ========================= --}}
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 bg-black/50 p-4 flex items-center justify-center">
        <div @click.outside="showCreateModal = false"
             class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-lg p-5">

            <h2 class="text-xl font-bold mb-4">Registrar salida de inventario</h2>

            <form action="{{ route('inventario.salidas.store') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Cliente --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>

                    <div class="relative mb-2">
                        <input type="text"
                               x-model="clienteSearch"
                               placeholder="Buscar cliente por nombre o empresa..."
                               autocomplete="off"
                               @focus="showClienteList = true; filterClientes()"
                               @input="showClienteList = true; filterClientes()"
                               @keydown.escape="showClienteList = false"
                               class="w-full border rounded-lg px-3 py-2 pr-10 focus:ring-2 focus:ring-indigo-500">

                        <input type="hidden" name="id_cliente" x-model="idCliente">

                        <div x-show="showClienteList"
                             x-cloak
                             @click.outside="showClienteList = false"
                             class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            <template x-for="cliente in clientesFiltrados" :key="cliente.clave_cliente">
                                <button type="button"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                        @click="selectCliente(cliente)">
                                    <div class="text-sm font-medium text-gray-900" x-text="cliente.label"></div>
                                    <div class="text-xs text-gray-500"
                                         x-text="cliente.empresa ? ('Empresa: ' + cliente.empresa) : 'Empresa: -'"></div>
                                </button>
                            </template>

                            <div x-show="clientesFiltrados.length === 0"
                                 class="px-3 py-3 text-sm text-gray-500">
                                Sin resultados.
                            </div>
                        </div>

                        <button type="button"
                                x-show="clienteSearch"
                                x-cloak
                                @click="clearCliente()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            &times;
                        </button>
                    </div>

                    <p class="text-xs text-gray-500 mb-2">
                        Selecciona un cliente del listado para registrar la salida.
                    </p>
                    <select name="id_cliente_legacy" disabled
                            x-show="false"
                            class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled selected>— Selecciona un cliente —</option>
                        @foreach($clientesLista as $cli)
                            <option value="{{ $cli->clave_cliente }}">
                                {{ $cli->nombre }} @if($cli->nombre_empresa) — {{ $cli->nombre_empresa }} @endif
                            </option>
                        @endforeach
                    </select>

                    <p class="text-xs text-gray-500 mt-1">
                        No aparece?
                        <a href="{{ route('clientes.nuevo', ['redirect' => url()->full()]) }}" class="text-blue-600 underline">
                            Registralo aqui
                        </a>.
                    </p>
                </div>

                {{-- Moneda / Tasa --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                        <select name="moneda" x-model="monedaSeleccionada"
                                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" required>
                            <option value="MXN">MXN</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>

                    <div x-show="monedaSeleccionada === 'USD'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tasa de cambio (opcional)</label>
                        <input type="number" step="0.0001" min="0" name="tasa_cambio"
                               placeholder="Ej. 17.10"
                               value="{{ old('tasa_cambio') }}"
                               class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Producto --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                    <div class="relative mb-2">
                        <input type="text"
                               x-model="productoSearch"
                               placeholder="Buscar producto por nombre o numero de parte..."
                               autocomplete="off"
                               @focus="showProductoList = true; filterProductos()"
                               @input="onProductoInput()"
                               @keydown.escape="showProductoList = false"
                               class="w-full border rounded-lg px-3 py-2 pr-10 focus:ring-2 focus:ring-indigo-500">

                        <input type="hidden" name="codigo_producto" x-model="productoSeleccionado">

                        <div x-show="showProductoList"
                             x-cloak
                             @click.outside="showProductoList = false"
                             class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            <template x-for="producto in productosFiltrados" :key="producto.codigo_producto">
                                <button type="button"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                        @click="selectProducto(producto)">
                                    <div class="text-sm font-medium text-gray-900" x-text="producto.label"></div>
                                    <div class="text-xs text-gray-500"
                                         x-text="producto.series_disponibles > 0 ? ('NS: ' + producto.series_disponibles + ' disponibles') : 'Sin series disponibles'"></div>
                                </button>
                            </template>

                            <div x-show="productosFiltrados.length === 0"
                                 class="px-3 py-3 text-sm text-gray-500">
                                Sin resultados.
                            </div>
                        </div>

                        <button type="button"
                                x-show="productoSearch"
                                x-cloak
                                @click="clearProducto()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            &times;
                        </button>
                    </div>

                    <p class="text-xs text-gray-500 mb-2">
                        Busca como en cotizaciones y selecciona un producto del listado.
                    </p>
                    <select name="codigo_producto_legacy" disabled
                            x-show="false"
                            class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled selected>— Selecciona un producto —</option>
                        @foreach($productosLista as $prod)
                            <option value="{{ $prod->codigo_producto }}">
                                {{ $prod->nombre }}
                                @if($prod->numero_parte) (NP: {{ $prod->numero_parte }}) @endif
                                — @if($prod->series_disponibles > 0) NS: {{ $prod->series_disponibles }} disponibles @else sin series @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Cantidad / Precio --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                        <input id="cantidadSalida" type="number" step="0.01" min="0.01" name="cantidad"
                               placeholder="Ej. 1"
                               value="{{ old('cantidad') }}"
                               class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-500 mt-1">Si seleccionas series, la cantidad se fijará automáticamente.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio unitario (opcional)</label>
                        <input type="number" step="0.01" min="0" name="precio_unitario"
                               value="{{ old('precio_unitario') }}"
                               class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>

                {{-- Series --}}
                <div class="border rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-800">Números de serie disponibles</span>
                        <div class="flex gap-2">
                            <button type="button" @click="seleccionarTodo()"
                                    class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded border">
                                Seleccionar todo
                            </button>
                            <button type="button" @click="limpiarSelecciones()"
                                    class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded border">
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <template x-if="cargandoSeries">
                        <div class="text-sm text-gray-500">Cargando series...</div>
                    </template>

                    <template x-if="!cargandoSeries && seriesDisponibles.length === 0">
                        <div class="text-sm text-gray-500">Este producto no maneja números de serie o no hay series disponibles.</div>
                    </template>

                    <template x-if="!cargandoSeries && seriesDisponibles.length > 0">
                        <div class="max-h-48 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="ns in seriesDisponibles" :key="ns">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="series[]" :value="ns" @change="toggleSerie(ns, $event)" class="rounded border-gray-300">
                                    <span class="break-words" x-text="ns"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                    <template x-if="seriesSeleccionadas.length > 0">
                        <p class="text-xs text-gray-600 mt-2">
                            Seleccionadas: <span class="font-medium" x-text="seriesSeleccionadas.length"></span>
                        </p>
                    </template>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2">
                    <button type="button" @click="showCreateModal = false"
                            class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                        Guardar salida
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function salidasUI(config = {}) {
  return {
    showModal: false,
    showCreateModal: !!config.openCreate,
    showEditModal: false,
    selected: {},
    editSelected: {},
    editQuantity: 1,
    editAction: '',
    productoSeleccionado: config.productoId || config.producto || '',
    productosAll: Array.isArray(config.productos) ? config.productos : [],
    productosFiltrados: [],
    productoSearch: config.productoLabel || '',
    showProductoList: false,
    seriesDisponibles: [],
    seriesSeleccionadas: [],
    seriesIniciales: Array.isArray(config.series) ? config.series : [],
    cargandoSeries: false,
    monedaSeleccionada: config.moneda || 'MXN',
    clientesAll: Array.isArray(config.clientes) ? config.clientes : [],
    clientesFiltrados: [],
    clienteSearch: config.clienteLabel || '',
    idCliente: config.clienteId || '',
    showClienteList: false,

    init() {
      this.syncClienteSearchFromId();
      this.syncProductoSearchFromId();
      if (this.showCreateModal) {
        this.filterClientes();
        this.filterProductos();
        if (this.productoSeleccionado) {
          this.cargarSeries();
        }
      }
    },

    open(item) { this.selected = item; this.showModal = true; },

    openEdit(item) {
      if (!item || !item.can_edit_quantity) return;
      this.editSelected = item;
      this.editQuantity = Math.max(parseInt(item.cantidad || 1, 10), 1);
      this.editAction = item.edit_url || '';
      this.showEditModal = true;
    },

    openFromTarget(e) {
      try {
        const raw = e.currentTarget.dataset.payload || '{}';
        const data = JSON.parse(raw);
        this.open(data);
      } catch (err) {
        console.error('No se pudo abrir el modal:', err);
      }
    },

    openEditFromTarget(e) {
      try {
        const raw = e.currentTarget.dataset.payload || '{}';
        const data = JSON.parse(raw);
        this.openEdit(data);
      } catch (err) {
        console.error('No se pudo abrir el modal de edicion:', err);
      }
    },

    filterClientes() {
      const q = (this.clienteSearch || '').toLowerCase().trim();

      if (!q) {
        this.clientesFiltrados = this.clientesAll.slice(0, 30);
        return;
      }

      this.clientesFiltrados = this.clientesAll
        .filter(cliente => (cliente.label || '').toLowerCase().includes(q))
        .slice(0, 30);
    },

    selectCliente(cliente) {
      if (!cliente) return;
      this.idCliente = String(cliente.clave_cliente || '');
      this.clienteSearch = cliente.label || '';
      this.showClienteList = false;
    },

    clearCliente() {
      this.idCliente = '';
      this.clienteSearch = '';
      this.showClienteList = false;
      this.filterClientes();
    },

    syncClienteSearchFromId() {
      if (!this.idCliente) return;

      const found = this.clientesAll.find(cliente => String(cliente.clave_cliente) === String(this.idCliente));
      if (found && !this.clienteSearch) {
        this.clienteSearch = found.label || '';
      }
    },

    filterProductos() {
      const q = (this.productoSearch || '').toLowerCase().trim();

      if (!q) {
        this.productosFiltrados = this.productosAll.slice(0, 30);
        return;
      }

      this.productosFiltrados = this.productosAll
        .filter(producto => (producto.search || producto.label || '').toLowerCase().includes(q))
        .slice(0, 30);
    },

    onProductoInput() {
      this.productoSeleccionado = '';
      this.seriesIniciales = [];
      this.seriesDisponibles = [];
      this.seriesSeleccionadas = [];
      this.showProductoList = true;

      const qty = document.getElementById('cantidadSalida');
      if (qty) {
        qty.readOnly = false;
      }

      this.$nextTick(() => {
        document.querySelectorAll("input[name='series[]']").forEach(cb => cb.checked = false);
      });

      this.filterProductos();
    },

    selectProducto(producto) {
      if (!producto) return;
      this.seriesIniciales = [];
      this.productoSeleccionado = String(producto.codigo_producto || '');
      this.productoSearch = producto.label || '';
      this.showProductoList = false;
      this.cargarSeries();
    },

    clearProducto() {
      this.productoSeleccionado = '';
      this.productoSearch = '';
      this.showProductoList = false;
      this.seriesDisponibles = [];
      this.seriesSeleccionadas = [];
      this.seriesIniciales = [];

      const qty = document.getElementById('cantidadSalida');
      if (qty) {
        qty.readOnly = false;
      }

      this.$nextTick(() => {
        document.querySelectorAll("input[name='series[]']").forEach(cb => cb.checked = false);
      });

      this.filterProductos();
    },

    syncProductoSearchFromId() {
      if (!this.productoSeleccionado) return;

      const found = this.productosAll.find(producto => String(producto.codigo_producto) === String(this.productoSeleccionado));
      if (found && !this.productoSearch) {
        this.productoSearch = found.label || '';
      }
    },

    async cargarSeries() {
      this.seriesDisponibles = [];
      this.seriesSeleccionadas = [];
      const qty = document.getElementById('cantidadSalida');
      if (qty) {
        qty.readOnly = false;
      }
      if (!this.productoSeleccionado) return;

      this.cargandoSeries = true;
      try {
        const qs = new URLSearchParams({ codigo_producto: this.productoSeleccionado });
        const res = await fetch('{{ route('inventario.salidas.series') }}?' + qs.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        this.seriesDisponibles = Array.isArray(data.series) ? data.series : [];
        this.seriesSeleccionadas = this.seriesDisponibles.filter(ns => this.seriesIniciales.includes(ns));

        this.$nextTick(() => {
          document.querySelectorAll("input[name='series[]']").forEach(cb => {
            cb.checked = this.seriesSeleccionadas.includes(cb.value);
          });
        });

        if (qty && this.seriesSeleccionadas.length > 0) {
          qty.value = this.seriesSeleccionadas.length;
          qty.readOnly = true;
        }
        this.seriesIniciales = [];
      } catch (e) {
        this.seriesDisponibles = [];
      } finally {
        this.cargandoSeries = false;
      }
    },

    toggleSerie(ns, ev) {
      if (ev.target.checked) {
        if (!this.seriesSeleccionadas.includes(ns)) this.seriesSeleccionadas.push(ns);
      } else {
        this.seriesSeleccionadas = this.seriesSeleccionadas.filter(s => s !== ns);
      }

      const qty = document.getElementById('cantidadSalida');
      if (!qty) return;

      if (this.seriesSeleccionadas.length > 0) {
        qty.value = this.seriesSeleccionadas.length;
        qty.readOnly = true;
      } else {
        qty.readOnly = false;
      }
    },

    seleccionarTodo() {
      this.seriesSeleccionadas = [...this.seriesDisponibles];
      this.$nextTick(() => {
        document.querySelectorAll("input[name='series[]']").forEach(cb => cb.checked = true);
      });

      const qty = document.getElementById('cantidadSalida');
      if (qty) {
        qty.value = this.seriesSeleccionadas.length;
        qty.readOnly = true;
      }
    },

    limpiarSelecciones() {
      this.seriesSeleccionadas = [];
      this.$nextTick(() => {
        document.querySelectorAll("input[name='series[]']").forEach(cb => cb.checked = false);
      });

      const qty = document.getElementById('cantidadSalida');
      if (qty) {
        qty.readOnly = false;
      }
    },
  }
}
</script>
@endpush
