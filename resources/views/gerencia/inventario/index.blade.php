@extends('layouts.sidebar-navigation')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="{
        compactView: false,
        multiSelect: false,
        selected: [],
        toggleEntry(id) {
            if (!this.multiSelect) return;
            this.selected = this.selected.includes(id)
                ? this.selected.filter(item => item !== id)
                : [...this.selected, id];
        },
        toggleAll(ids) {
            this.selected = this.selected.length === ids.length ? [] : ids;
        },
        resetSelectionMode() {
            this.multiSelect = !this.multiSelect;
            this.selected = [];
        },
     }">
    @php
        $isSystem = auth()->user() && method_exists(auth()->user(), 'isSystem') && auth()->user()->isSystem();
        $entradaIdsOnPage = $entradas->pluck('id')->map(fn($id) => (int) $id)->values();
    @endphp

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

        </div>
    </div>

    @if($isSystem)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            class="px-3 py-2 rounded-lg border text-sm"
                            :class="compactView ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                            @click="compactView = !compactView">
                        Vista lista compacta
                    </button>
                    <button type="button"
                            class="px-3 py-2 rounded-lg border text-sm"
                            :class="multiSelect ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                            @click="resetSelectionMode()">
                        Seleccion multiple
                    </button>
                    <button type="button"
                            x-show="multiSelect"
                            class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50"
                            @click="toggleAll(@js($entradaIdsOnPage))">
                        Seleccionar pagina
                    </button>
                </div>

                <form method="POST" action="{{ route('inventario.eliminar_masivo') }}"
                      x-show="multiSelect"
                      class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="entradas[]" :value="id">
                    </template>
                    <span class="text-sm text-gray-600"><span x-text="selected.length"></span> seleccionados</span>
                    <button class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                        Eliminar inventario seleccionado
                    </button>
                </form>
            </div>
            <p x-show="multiSelect" class="mt-2 text-xs text-gray-500">
                Con seleccion multiple activa, cualquier clic sobre una tarjeta o fila selecciona la entrada.
            </p>
        </div>
    @endif

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

            <div class="bg-white border rounded-2xl p-4 shadow-sm"
                 :class="[
                    compactView ? 'p-3 rounded-xl' : '',
                    multiSelect ? 'cursor-pointer select-none hover:border-blue-300 hover:bg-blue-50/40' : '',
                    selected.includes({{ (int) $e->id }}) ? 'border-blue-500 ring-2 ring-blue-100 bg-blue-50' : ''
                 ]"
                 @click="toggleEntry({{ (int) $e->id }})">
                {{-- Encabezado --}}
                <div class="flex items-start justify-between gap-3">
                    @if($isSystem)
                        <label x-show="multiSelect"
                               class="shrink-0 pt-0.5"
                               @click.stop>
                            <input type="checkbox"
                                   class="rounded border-gray-300"
                                   :value="{{ (int) $e->id }}"
                                   x-model.number="selected">
                        </label>
                    @endif

                    <div class="min-w-0 flex-1">
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
                <div class="mt-4 space-y-2 text-sm" :class="compactView ? 'mt-2 text-xs space-y-1' : ''">
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
                <div class="mt-4" :class="compactView ? 'mt-2' : ''" @click.stop>
                    @if($canModify || $isSystem)
                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ route('inventario.editar', $e->id) }}"
                               class="text-center px-3 py-2 rounded-lg border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100">
                                Editar
                            </a>

                            <form action="{{ route('inventario.eliminar', $e->id) }}"
                                  method="POST"
                                  >
                                @csrf
                                @method('DELETE')
                                <button class="w-full px-3 py-2 rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100">
                                    Eliminar inventario
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
            <table class="min-w-[980px] w-full text-sm" :class="compactView ? 'text-xs' : 'text-sm'">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        @if($isSystem)
                            <th x-show="multiSelect" class="text-center px-2 py-2 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300"
                                       :checked="selected.length === {{ $entradaIdsOnPage->count() }} && selected.length > 0"
                                       @click.stop="toggleAll(@js($entradaIdsOnPage))">
                            </th>
                        @endif
                        <th class="text-left px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Fecha</th>
                        <th class="text-left px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Producto</th>
                        <th class="text-left px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Proveedor</th>
                        <th class="text-left px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Tipo</th>
                        <th class="text-right px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Cant</th>
                        <th class="text-right px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Costo</th>
                        <th class="text-right px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Precio</th>
                        <th class="text-center px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">Acciones</th>
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

                        <tr class="border-b"
                            :class="[
                                compactView ? 'text-xs' : '',
                                multiSelect ? 'cursor-pointer select-none hover:bg-blue-50/60' : '',
                                selected.includes({{ (int) $e->id }}) ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : ''
                            ]"
                            @click="toggleEntry({{ (int) $e->id }})">
                            @if($isSystem)
                                <td x-show="multiSelect" class="px-2 py-2 text-center" @click.stop>
                                    <input type="checkbox"
                                           class="rounded border-gray-300"
                                           :value="{{ (int) $e->id }}"
                                           x-model.number="selected">
                                </td>
                            @endif

                            <td class="px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">
                                {{ \Carbon\Carbon::parse($e->fecha_entrada)->format('d/m/Y') }}
                                @if(!empty($e->hora_entrada))
                                    <div class="text-xs text-gray-500">{{ $e->hora_entrada }}</div>
                                @endif
                            </td>

                            <td class="px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">
                                {{ $e->producto->nombre ?? '—' }}
                                <div class="text-xs text-gray-500">{{ $e->producto->numero_parte ?? '' }}</div>
                            </td>

                            <td class="px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">
                                {{ $e->proveedor->nombre ?? '—' }}
                            </td>

                            <td class="px-3 py-2" :class="compactView ? 'px-2 py-1' : ''">
                                {{ $e->tipo_control }}
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap" :class="compactView ? 'px-2 py-1' : ''">
                                @if($e->tipo_control==='PAQUETES')
                                    {{ $e->paquetes_restantes }} pqt × {{ $e->piezas_por_paquete }} = {{ $e->paquetes_restantes * $e->piezas_por_paquete }}
                                @elseif($e->tipo_control==='SERIE')
                                    1
                                @else
                                    {{ $e->piezas_sueltas }}
                                @endif
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap" :class="compactView ? 'px-2 py-1' : ''">
                                ${{ number_format($e->costo, 2) }}
                            </td>

                            <td class="px-3 py-2 text-right whitespace-nowrap" :class="compactView ? 'px-2 py-1' : ''">
                                ${{ number_format($e->precio, 2) }}
                            </td>

                            <td class="px-3 py-2 text-center whitespace-nowrap" :class="compactView ? 'px-2 py-1' : ''" @click.stop>
                                @if($canModify || $isSystem)
                                    <a href="{{ route('inventario.editar', $e->id) }}" class="text-blue-600 hover:underline">
                                        Editar
                                    </a>

                                    <form action="{{ route('inventario.eliminar', $e->id) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline ml-2">Eliminar inventario</button>
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
                            <td colspan="{{ $isSystem ? 9 : 8 }}" class="px-3 py-6 text-center text-gray-500">
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
