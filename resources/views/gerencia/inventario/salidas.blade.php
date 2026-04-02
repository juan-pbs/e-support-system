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
@endphp

<style>[x-cloak]{display:none !important}</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4" x-data="salidasUI()">

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

        <button @click="showCreateModal = true"
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
                    <select name="id_cliente" required
                            class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled selected>— Selecciona un cliente —</option>
                        @foreach($clientesLista as $cli)
                            <option value="{{ $cli->clave_cliente }}">
                                {{ $cli->nombre }} @if($cli->nombre_empresa) — {{ $cli->nombre_empresa }} @endif
                            </option>
                        @endforeach
                    </select>
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
                               class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Producto --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                    <select name="codigo_producto" required
                            x-model="productoSeleccionado" @change="cargarSeries()"
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
                               class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-500 mt-1">Si seleccionas series, la cantidad se fijará automáticamente.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio unitario (opcional)</label>
                        <input type="number" step="0.01" min="0" name="precio_unitario"
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
function salidasUI() {
  return {
    showModal: false,
    showCreateModal: false,
    selected: {},
    productoSeleccionado: '',
    seriesDisponibles: [],
    seriesSeleccionadas: [],
    cargandoSeries: false,
    monedaSeleccionada: 'MXN',

    open(item) { this.selected = item; this.showModal = true; },

    openFromTarget(e) {
      try {
        const raw = e.currentTarget.dataset.payload || '{}';
        const data = JSON.parse(raw);
        this.open(data);
      } catch (err) {
        console.error('No se pudo abrir el modal:', err);
      }
    },

    async cargarSeries() {
      this.seriesDisponibles = [];
      this.seriesSeleccionadas = [];
      if (!this.productoSeleccionado) return;

      this.cargandoSeries = true;
      try {
        const qs = new URLSearchParams({ codigo_producto: this.productoSeleccionado });
        const res = await fetch('{{ route('inventario.salidas.series') }}?' + qs.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        this.seriesDisponibles = Array.isArray(data.series) ? data.series : [];
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
      if (qty) { qty.value = this.seriesSeleccionadas.length; qty.readOnly = true; }
    },

    limpiarSelecciones() {
      this.seriesSeleccionadas = [];
      this.$nextTick(() => {
        document.querySelectorAll("input[name='series[]']").forEach(cb => cb.checked = false);
      });
      const qty = document.getElementById('cantidadSalida');
      if (qty) { qty.readOnly = false; }
    },
  }
}
</script>
@endpush
