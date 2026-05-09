@extends('layouts.sidebar-navigation')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="{
        openConfirm:false, cTitle:'', cMessage:'', cAction:'', cMethod:'POST',
        abrirConfirm(t, m, a, meth){ this.cTitle=t; this.cMessage=m; this.cAction=a; this.cMethod=meth; this.openConfirm=true; },
        cerrarConfirm(){ this.openConfirm=false; },
        openDetail:false, detail:{},
        abrirDetalle(p){ this.detail=p; this.openDetail=true; },
        cerrarDetalle(){ this.openDetail=false; },
        selected: [],
        selectionMode: false,
        viewMode: 'cards',
        toggleSelectionMode(){ this.selectionMode = !this.selectionMode; this.selected = []; },
        toggleAll(ids){ this.selected = this.selected.length === ids.length ? [] : ids; },
        toggleProduct(id){
            this.selected = this.selected.includes(id)
                ? this.selected.filter(item => item !== id)
                : [...this.selected, id];
        },
     }">
    @php
        $isSystem = auth()->user() && method_exists(auth()->user(), 'isSystem') && auth()->user()->isSystem();
        $productIdsOnPage = $productos->pluck('codigo_producto')->map(fn($id) => (int) $id)->values();
    @endphp

    <div class="mb-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
        <x-boton-volver />
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 leading-tight break-words">Catálogo de productos</h1>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(()=>show=false, 5000)"
             class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-300 shadow">
            <strong>Éxito:</strong> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(()=>show=false, 8000)"
             class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300 shadow">
            <strong>Error:</strong> {{ session('error') }}
        </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" action="{{ route('catalogo.index') }}" class="mb-8">
        <div class="bg-white w-full rounded-xl border border-gray-200 shadow p-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select name="categoria" class="w-full border px-3 py-2 rounded-lg">
                        <option value="">Todas</option>
                        <optgroup label="Categorías base">
                            @foreach($categoriasPredefinidas as $cat)
                                <option value="{{ $cat }}" {{ request('categoria')==$cat?'selected':'' }}>
                                    {{ ucfirst($cat) }}
                                </option>
                            @endforeach
                        </optgroup>
                        @if($categoriasExtra->isNotEmpty())
                            <optgroup label="Categorías registradas">
                                @foreach($categoriasExtra as $cat)
                                    <option value="{{ $cat }}" {{ request('categoria')==$cat?'selected':'' }}>
                                        {{ $cat }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <div class="lg:col-span-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>

                    {{-- ✅ NO genera form interno; manda buscar + producto_id --}}
                    <x-barra-busqueda-producto
                        autocompleteUrl="{{ route('catalogo.autocomplete') }}"
                        placeholder="Nombre / número de parte…"
                        inputId="buscar-producto"
                        resultId="resultados-producto"
                        name="buscar"
                        value="{{ request('buscar') }}"
                        idValue="{{ request('producto_id') }}"
                    />
                </div>

                @if($isSystem)
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mostrar</label>
                        <select name="per_page" class="w-full border px-2 py-2 rounded-lg text-sm">
                            @foreach([12, 24, 48, 96] as $option)
                                <option value="{{ $option }}" @selected((int) request('per_page', 12) === $option)>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="{{ $isSystem ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                    <div class="grid grid-cols-1 gap-2">
                        <label class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="stock_bajo" value="1" {{ request('stock_bajo')?'checked':'' }}>
                            <span class="leading-tight">Stock bajo</span>
                        </label>
                        <label class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="inactivos" value="1" {{ request('inactivos')?'checked':'' }}>
                            <span class="leading-tight">Ver inactivos</span>
                        </label>
                        <label class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="papelera" value="1" {{ !empty($papelera)?'checked':'' }}>
                            <span class="leading-tight">Papelera</span>
                        </label>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Acciones principales --}}
    <div class="grid grid-cols-1 sm:flex sm:justify-end mb-4 gap-2">
        @if(!empty($papelera))
            <a href="{{ route('catalogo.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center gap-2">
                Volver al catálogo
            </a>
        @else
            <a href="{{ route('catalogo.index', ['papelera' => 1]) }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg flex items-center justify-center gap-2">
                Papelera
            </a>
        @endif
        <a href="{{ route('producto.crear') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2">
            <i class="fas fa-plus"></i> Añadir producto
        </a>
        <a href="{{ route('entrada') }}"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2">
            <i class="fas fa-sign-in-alt"></i> Nueva entrada
        </a>
    </div>

    @if($isSystem)
        <form id="bulk-product-form" method="POST" action="{{ route('catalogo.productos.bulk') }}"
              class="mb-4 rounded-xl border border-gray-200 bg-white p-3">
            @csrf
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            class="text-sm px-3 py-2 rounded-lg border"
                            :class="viewMode === 'cards' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:bg-gray-50'"
                            @click="viewMode = 'cards'">
                        Tarjetas
                    </button>
                    <button type="button"
                            class="text-sm px-3 py-2 rounded-lg border"
                            :class="viewMode === 'compact' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:bg-gray-50'"
                            @click="viewMode = 'compact'">
                        Tarjetas compactas
                    </button>
                    <button type="button"
                            class="text-sm px-3 py-2 rounded-lg border"
                            :class="viewMode === 'list' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:bg-gray-50'"
                            @click="viewMode = 'list'">
                        Lista
                    </button>
                    <button type="button"
                            class="text-sm px-3 py-2 rounded-lg border"
                            :class="selectionMode ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:bg-gray-50'"
                            @click="toggleSelectionMode()">
                        Seleccion multiple
                    </button>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button type="button"
                        x-show="selectionMode"
                        class="text-sm px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50"
                        @click="toggleAll(@js($productIdsOnPage))">
                    Seleccionar página
                </button>
                <span x-show="selectionMode" class="text-sm text-gray-600"><span x-text="selected.length"></span> seleccionados</span>
            </div>
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="productos[]" :value="id">
            </template>
            <div x-show="selectionMode" class="flex flex-wrap gap-2">
                @if(!empty($papelera))
                    <button name="action" value="restaurar" class="px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm">
                        Recuperar seleccionados
                    </button>
                @else
                    <button name="action" value="desactivar" class="px-3 py-2 rounded-lg bg-yellow-600 hover:bg-yellow-700 text-white text-sm">
                        Desactivar seleccionados
                    </button>
                    <button name="action" value="eliminar" class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm">
                        Enviar a papelera
                    </button>
                @endif
            </div>
            </div>
            <p x-show="selectionMode" class="mt-2 text-xs text-gray-500">
                Con seleccion multiple activa, cualquier clic sobre el producto lo selecciona.
            </p>
        </form>
    @endif

    {{-- Lista --}}
    <div x-show="viewMode === 'list'" style="display:none" class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <div class="min-w-[1040px]">
            <div class="grid grid-cols-[44px_72px_minmax(210px,1.15fr)_minmax(240px,1fr)_220px_110px_170px] items-center gap-3 bg-blue-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700">
                <div></div>
                <div>Img</div>
                <div>Detalle</div>
                <div>Categoria / proveedor</div>
                <div>Inventario</div>
                <div>Estado</div>
                <div class="text-right">Acciones</div>
            </div>

            @forelse($productos as $p)
                <div class="grid grid-cols-[44px_72px_minmax(210px,1.15fr)_minmax(240px,1fr)_220px_110px_170px] items-center gap-3 border-t border-gray-200 px-4 py-2 text-sm transition"
                     :class="[
                        selectionMode ? 'cursor-pointer select-none hover:bg-blue-50/60' : 'hover:bg-gray-50',
                        selected.includes({{ (int) $p->codigo_producto }}) ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : ''
                     ]"
                     @click="selectionMode ? toggleProduct({{ (int) $p->codigo_producto }}) : abrirDetalle({
                        nombre: @js($p->nombre),
                        numero_parte: @js($p->numero_parte),
                        categoria: @js($p->categoria),
                        clave_prodserv: @js($p->clave_prodserv),
                        unidad: @js($p->unidad),
                        proveedores: @js($p->proveedores_str),
                        stock_total: {{ (int)($p->stock_total ?? 0) }},
                        stock_fisico: {{ (int)($p->stock_fisico ?? $p->stock_total ?? 0) }},
                        stock_disponible: {{ (int)($p->stock_disponible ?? 0) }},
                        sin_disponible: {{ !empty($p->sin_disponible) ? 'true':'false' }},
                        stock_seguridad: {{ (int)($p->stock_seguridad ?? 0) }},
                        descripcion: @js($p->descripcion),
                        activo: {{ $p->activo ? 'true':'false' }},
                        imagen: @js($p->imagen ? asset($p->imagen) : asset('images/imagen.png')),
                     })">
                    <div>
                        @if($isSystem)
                            <label x-show="selectionMode" class="inline-flex rounded bg-white px-2 py-1 shadow-sm" @click.stop>
                                <input type="checkbox" class="rounded border-gray-300"
                                       :value="{{ (int) $p->codigo_producto }}"
                                       x-model.number="selected">
                            </label>
                        @endif
                    </div>

                    <img src="{{ $p->imagen ? asset($p->imagen) : asset('images/imagen.png') }}"
                         alt="Imagen {{ $p->nombre }}"
                         class="h-12 w-12 rounded object-cover bg-gray-100">

                    <div class="min-w-0">
                        <div class="truncate font-semibold text-gray-900">{{ $p->nombre }}</div>
                        <div class="mt-0.5 text-xs text-gray-600">
                            NP: {{ $p->numero_parte ?: '---' }} · {{ strtoupper($p->unidad ?? '---') }}
                        </div>
                    </div>

                    <div class="min-w-0 text-xs text-gray-700">
                        <div class="truncate font-medium">{{ $p->categoria ?? '---' }}</div>
                        <div class="mt-0.5 truncate text-gray-500">{{ $p->proveedores_str ?? '---' }}</div>
                    </div>

                    <div class="grid grid-cols-3 gap-1 text-center text-xs">
                        <div class="rounded bg-gray-50 px-2 py-1">
                            <div class="font-semibold">{{ (int)($p->stock_disponible ?? 0) }}</div>
                            <div class="text-[10px] text-gray-500">Disp.</div>
                        </div>
                        <div class="rounded bg-gray-50 px-2 py-1">
                            <div class="font-semibold">{{ (int)($p->stock_fisico ?? $p->stock_total ?? 0) }}</div>
                            <div class="text-[10px] text-gray-500">Fis.</div>
                        </div>
                        <div class="rounded bg-gray-50 px-2 py-1">
                            <div class="font-semibold">{{ $p->stock_seguridad ?? 0 }}</div>
                            <div class="text-[10px] text-gray-500">Min.</div>
                        </div>
                    </div>

                    <div>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $p->activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                            {{ $p->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                        @if(!empty($p->sin_disponible))
                            <div class="mt-1 text-[11px] font-semibold text-red-600">No disponible</div>
                        @endif
                    </div>

                    <div class="flex flex-wrap justify-end gap-1" x-show="!selectionMode" @click.stop>
                        @if(empty($papelera))
                            <a href="{{ route('producto.editar', $p->codigo_producto) }}"
                               class="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700"
                               @click.stop>
                                Editar
                            </a>
                            <a href="{{ route('inventario.entrada', $p->codigo_producto) }}"
                               class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700"
                               @click.stop>
                                Entrada
                            </a>
                        @endif

                        @if(!empty($papelera))
                            <button class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700"
                                    @click.stop="abrirConfirm('Recuperar producto','Se restaurara este producto.',
                                            '{{ route('producto.restaurar', $p->codigo_producto) }}','PUT')">
                                Recuperar
                            </button>
                        @elseif($p->activo)
                            <button class="rounded bg-yellow-600 px-2 py-1 text-xs text-white hover:bg-yellow-700"
                                    @click.stop="abrirConfirm('Desactivar producto','Se desactivara este producto.',
                                            '{{ route('producto.desactivar', $p->codigo_producto) }}','PUT')">
                                Desactivar
                            </button>
                        @else
                            <button class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700"
                                    @click.stop="abrirConfirm('Activar producto','Se activara este producto.',
                                            '{{ route('producto.activar', $p->codigo_producto) }}','PUT')">
                                Activar
                            </button>
                            <button class="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700"
                                    @click.stop="abrirConfirm('Enviar a papelera','Podras recuperar este producto durante 20 dias.',
                                            '{{ route('producto.eliminar', $p->codigo_producto) }}','DELETE')">
                                Eliminar
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-gray-500">Sin productos.</div>
            @endforelse
        </div>
    </div>

    {{-- Grid --}}
    <div x-show="viewMode !== 'list'" :class="viewMode === 'list'
            ? 'space-y-1.5'
            : (viewMode === 'compact'
                ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3'
                : 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6')">
        @forelse($productos as $p)
            <div class="bg-white rounded-xl overflow-hidden border border-gray-200 h-full flex flex-col"
                 :class="[
                    viewMode === 'compact' ? 'rounded-lg' : '',
                    viewMode === 'list' ? 'min-h-0 flex-row items-center rounded-lg' : '',
                    selectionMode ? 'cursor-pointer select-none hover:border-blue-300 hover:bg-blue-50/40' : '',
                    selected.includes({{ (int) $p->codigo_producto }}) ? 'border-blue-500 ring-2 ring-blue-100 bg-blue-50' : ''
                 ]"
                 @click="selectionMode ? toggleProduct({{ (int) $p->codigo_producto }}) : abrirDetalle({
                    nombre: @js($p->nombre),
                    numero_parte: @js($p->numero_parte),
                    categoria: @js($p->categoria),
                    clave_prodserv: @js($p->clave_prodserv),
                    unidad: @js($p->unidad),
                    proveedores: @js($p->proveedores_str),
                    stock_total: {{ (int)($p->stock_total ?? 0) }},
                    stock_fisico: {{ (int)($p->stock_fisico ?? $p->stock_total ?? 0) }},
                    stock_disponible: {{ (int)($p->stock_disponible ?? 0) }},
                    sin_disponible: {{ !empty($p->sin_disponible) ? 'true':'false' }},
                    stock_seguridad: {{ (int)($p->stock_seguridad ?? 0) }},
                    descripcion: @js($p->descripcion),
                    activo: {{ $p->activo ? 'true':'false' }},
                    imagen: @js($p->imagen ? asset($p->imagen) : asset('images/imagen.png')),
                 })">

                <div class="relative">
                    @if($isSystem)
                        <label x-show="selectionMode" class="absolute right-2 top-2 z-10 rounded bg-white/90 px-2 py-1 shadow" @click.stop>
                            <input type="checkbox" class="rounded border-gray-300"
                                   :value="{{ (int) $p->codigo_producto }}"
                                   x-model.number="selected">
                        </label>
                    @endif
                    <img src="{{ $p->imagen ? asset($p->imagen) : asset('images/imagen.png') }}"
                         alt="Imagen {{ $p->nombre }}" class="w-full object-cover bg-gray-100"
                         :class="viewMode === 'compact' ? 'h-24' : (viewMode === 'list' ? 'h-16 w-20 shrink-0' : 'h-44')">
                    <span class="absolute top-2 left-2 text-xs px-2 py-1 rounded
                        {{ $p->activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}"
                        :class="viewMode === 'list' ? 'top-1 left-1 px-1.5 py-0.5 text-[10px]' : ''">
                        {{ $p->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <div class="p-4 flex-1 flex flex-col gap-2"
                     :class="viewMode === 'compact' ? 'p-2 gap-1' : (viewMode === 'list' ? 'p-2 gap-1 min-w-0' : 'p-4 gap-2')">
                    <div class="flex items-start justify-between gap-2" :class="viewMode === 'list' ? 'items-center' : ''">
                        <h3 class="text base font-semibold line-clamp-2" :class="viewMode === 'compact' ? 'text-xs' : (viewMode === 'list' ? 'text-sm line-clamp-1' : 'text-base')">{{ $p->nombre }}</h3>
                        @if($p->numero_parte)
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded shrink-0" :class="viewMode === 'list' ? 'px-1.5 py-0.5 text-[11px]' : ''">
                                {{ $p->numero_parte }}
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-gray-600 grid grid-cols-2 gap-x-3 gap-y-1" :class="viewMode === 'compact' ? 'gap-x-1' : (viewMode === 'list' ? 'grid-cols-3 gap-x-2 gap-y-0 text-[11px]' : '')">
                        <div><span class="text-gray-500">Cat.:</span> {{ $p->categoria ?? '—' }}</div>
                        <div><span class="text-gray-500">U.:</span> {{ strtoupper($p->unidad ?? '—') }}</div>
                        <div class="col-span-2" :class="viewMode === 'list' ? 'col-span-1 truncate' : 'col-span-2'">
                            <span class="text-gray-500">Proveedores:</span>
                            <span>{{ $p->proveedores_str ?? '—' }}</span>
                        </div>
                    </div>

                    @if(!empty($p->sin_disponible))
                        <div class="mt-1 inline-flex items-center self-start rounded-full bg-red-100 text-red-700 px-2 py-1 text-[11px] font-semibold">
                            No disponible
                        </div>
                    @endif

                    <div class="grid grid-cols-3 gap-2 text-center text-xs mt-2" :class="viewMode === 'compact' ? 'gap-1 mt-1' : (viewMode === 'list' ? 'gap-1 mt-0 max-w-md' : '')">
                        <div class="bg-gray-50 rounded-lg p-2" :class="viewMode === 'compact' ? 'p-1' : (viewMode === 'list' ? 'p-1 rounded-md' : '')">
                            <div class="font-semibold">{{ (int)($p->stock_disponible ?? 0) }}</div>
                            <div class="text-gray-500">Disponible</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2" :class="viewMode === 'compact' ? 'p-1' : (viewMode === 'list' ? 'p-1 rounded-md' : '')">
                            <div class="font-semibold">{{ (int)($p->stock_fisico ?? $p->stock_total ?? 0) }}</div>
                            <div class="text-gray-500">Físico</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2" :class="viewMode === 'compact' ? 'p-1' : (viewMode === 'list' ? 'p-1 rounded-md' : '')">
                            <div class="font-semibold">{{ $p->stock_seguridad ?? 0 }}</div>
                            <div class="text-gray-500">Mínimo</div>
                        </div>
                    </div>

                    <div class="mt-auto pt-3 flex flex-wrap items-center justify-between gap-2"
                         x-show="!selectionMode"
                         :class="viewMode === 'compact' ? 'pt-1 gap-1' : (viewMode === 'list' ? 'pt-1 gap-1' : '')"
                         @click.stop>
                        <div class="flex flex-wrap gap-2">
                            @if(empty($papelera))
                            <a href="{{ route('producto.editar', $p->codigo_producto) }}"
                               class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg"
                               :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                               @click.stop>
                                Editar
                            </a>
                            <a href="{{ route('inventario.entrada', $p->codigo_producto) }}"
                               class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg"
                               :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                               title="Registrar entrada de inventario"
                               @click.stop>
                                Agregar inventario
                            </a>
                            @endif
                        </div>

                        @if(!empty($papelera))
                            <button
                                class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg"
                                :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                                @click.stop="abrirConfirm('Recuperar producto','Se restaurará «{{ $p->nombre }}».',
                                        '{{ route('producto.restaurar', $p->codigo_producto) }}','PUT')">
                                Recuperar
                            </button>
                        @elseif($p->activo)
                            <button
                                class="text-sm bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded-lg"
                                :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                                @click.stop="abrirConfirm('Desactivar producto','Se desactivará «{{ $p->nombre }}».',
                                        '{{ route('producto.desactivar', $p->codigo_producto) }}','PUT')">
                                Desactivar
                            </button>
                        @else
                            <div class="flex gap-2">
                                <button
                                    class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg"
                                    :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                                    @click.stop="abrirConfirm('Activar producto','Se activará «{{ $p->nombre }}».',
                                            '{{ route('producto.activar', $p->codigo_producto) }}','PUT')">
                                    Activar
                                </button>
                                <button
                                    class="text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg"
                                    :class="viewMode === 'list' ? 'text-xs px-2 py-1 rounded-md' : ''"
                                    @click.stop="abrirConfirm('Enviar a papelera','Podrás recuperar este producto durante 20 días.',
                                            '{{ route('producto.eliminar', $p->codigo_producto) }}','DELETE')">
                                    Eliminar
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-500">Sin productos.</div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $productos->withQueryString()->links() }}
    </div>

    {{-- Modal confirmación --}}
    <div x-show="openConfirm" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-6" @click.away="cerrarConfirm()">
            <h3 class="text-lg font-semibold mb-2" x-text="cTitle"></h3>
            <p class="text-sm text-gray-600 mb-4" x-text="cMessage"></p>
            <form :action="cAction" method="POST" class="space-y-3">
                @csrf
                <template x-if="cMethod !== 'POST'">
                    <input type="hidden" name="_method" :value="cMethod">
                </template>
                <div class="flex justify-end gap-2 mt-2">
                    <button type="button" class="px-4 py-2 rounded-lg border" @click="cerrarConfirm()">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal detalle --}}
    <div x-show="openDetail" style="display:none" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-xl p-6 overflow-y-auto max-h-[90vh]" @click.away="cerrarDetalle()">
            <div class="flex items-start gap-4">
                <img :src="detail.imagen" alt="Imagen" class="w-40 h-40 object-cover bg-gray-100 rounded-lg">
                <div class="flex-1">
                    <h3 class="text-xl font-semibold" x-text="detail.nombre"></h3>
                    <div class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                        <div><span class="text-gray-500">Número de parte:</span> <span x-text="detail.numero_parte || '—'"></span></div>
                        <div><span class="text-gray-500">Categoría:</span> <span x-text="detail.categoria || '—'"></span></div>
                        <div><span class="text-gray-500">Unidad:</span> <span x-text="(detail.unidad || '—').toUpperCase()"></span></div>
                        <div class="col-span-2"><span class="text-gray-500">Proveedores:</span> <span x-text="detail.proveedores || '—'"></span></div>
                        <div><span class="text-gray-500">Disponible / Físico:</span> <span x-text="(detail.stock_disponible ?? 0) + ' / ' + (detail.stock_fisico ?? detail.stock_total ?? 0)"></span></div>
                        <div><span class="text-gray-500">Mínimo:</span> <span x-text="detail.stock_seguridad ?? 0"></span></div>
                        <div><span class="text-gray-500">Estado:</span>
                            <span class="px-2 py-0.5 rounded text-xs"
                                  :class="detail.activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'"
                                  x-text="detail.activo ? 'Activo' : 'Inactivo'"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-700 whitespace-pre-line" x-text="detail.descripcion || 'Sin descripción.'"></p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button class="px-4 py-2 rounded-lg border" @click="cerrarDetalle()">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
