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
     }">

    <div class="relative mb-10 text-center mx-a">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Catálogo de productos</h1>

        <div class="flex items-center justify-between mb-6">
            <x-boton-volver />
        </div>
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
    <form method="GET" action="{{ route('catalogo.index') }}" class="flex justify-center mb-8">
        <div class="bg-white w-full md:w-5/6 rounded-xl border border-gray-200 shadow p-4">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="w-full md:w-1/4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select name="categoria" class="w-full border px-3 py-2 rounded-lg">
                        <option value="">Todas</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat }}" {{ request('categoria')==$cat?'selected':'' }}>
                                {{ ucfirst($cat) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full md:w-2/4">
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

                <div class="w-full md:w-1/4 flex items-end gap-2">
                    <label class="inline-flex items-center text-sm gap-2">
                        <input type="checkbox" name="stock_bajo" value="1" {{ request('stock_bajo')?'checked':'' }}>
                        <span>Stock bajo</span>
                    </label>
                    <label class="inline-flex items-center text-sm gap-2">
                        <input type="checkbox" name="inactivos" value="1" {{ request('inactivos')?'checked':'' }}>
                        <span>Ver inactivos</span>
                    </label>
                    <button class="ml-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Aplicar</button>
                </div>
            </div>
        </div>
    </form>

    {{-- Acciones principales --}}
    <div class="flex justify-end mb-4 gap-2">
        <a href="{{ route('producto.crear') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fas fa-plus"></i> Añadir producto
        </a>
        <a href="{{ route('entrada') }}"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fas fa-sign-in-alt"></i> Nueva entrada
        </a>

    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($productos as $p)
            <div class="bg-white rounded-xl overflow-hidden border border-gray-200 h-full flex flex-col"
                 @click="abrirDetalle({
                    nombre: @js($p->nombre),
                    numero_parte: @js($p->numero_parte),
                    categoria: @js($p->categoria),
                    clave_prodserv: @js($p->clave_prodserv),
                    unidad: @js($p->unidad),
                    proveedores: @js($p->proveedores_str),
                    stock_total: {{ (int)($p->stock_total ?? 0) }},
                    stock_seguridad: {{ (int)($p->stock_seguridad ?? 0) }},
                    descripcion: @js($p->descripcion),
                    activo: {{ $p->activo ? 'true':'false' }},
                    imagen: @js($p->imagen ? asset($p->imagen) : asset('images/imagen.png')),
                 })">

                <div class="relative">
                    <img src="{{ $p->imagen ? asset($p->imagen) : asset('images/imagen.png') }}"
                         alt="Imagen {{ $p->nombre }}" class="w-full h-44 object-cover bg-gray-100">
                    <span class="absolute top-2 left-2 text-xs px-2 py-1 rounded
                        {{ $p->activo ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                        {{ $p->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <div class="p-4 flex-1 flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text base font-semibold line-clamp-2">{{ $p->nombre }}</h3>
                        @if($p->numero_parte)
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                                {{ $p->numero_parte }}
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-gray-600 grid grid-cols-2 gap-x-3 gap-y-1">
                        <div><span class="text-gray-500">Cat.:</span> {{ $p->categoria ?? '—' }}</div>
                        <div><span class="text-gray-500">U.:</span> {{ strtoupper($p->unidad ?? '—') }}</div>
                        <div class="col-span-2">
                            <span class="text-gray-500">Proveedores:</span>
                            <span>{{ $p->proveedores_str ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-center text-xs mt-2">
                        <div class="bg-gray-50 rounded-lg p-2">
                            <div class="font-semibold">{{ $p->stock_total ?? 0 }}</div>
                            <div class="text-gray-500">Stock</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2">
                            <div class="font-semibold">{{ $p->stock_seguridad ?? 0 }}</div>
                            <div class="text-gray-500">Mínimo</div>
                        </div>
                    </div>

                    <div class="mt-auto pt-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('producto.editar', $p->codigo_producto) }}"
                               class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg"
                               @click.stop>
                                Editar
                            </a>
                            <a href="{{ route('inventario.entrada', $p->codigo_producto) }}"
                               class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg"
                               title="Registrar entrada de inventario"
                               @click.stop>
                                Agregar inventario
                            </a>
                        </div>

                        @if($p->activo)
                            <button
                                class="text-sm bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded-lg"
                                @click.stop="abrirConfirm('Desactivar producto','Se desactivará «{{ $p->nombre }}».',
                                        '{{ route('producto.desactivar', $p->codigo_producto) }}','PUT')">
                                Desactivar
                            </button>
                        @else
                            <div class="flex gap-2">
                                <button
                                    class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg"
                                    @click.stop="abrirConfirm('Activar producto','Se activará «{{ $p->nombre }}».',
                                            '{{ route('producto.activar', $p->codigo_producto) }}','PUT')">
                                    Activar
                                </button>
                                <button
                                    class="text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg"
                                    @click.stop="abrirConfirm('Eliminar producto','Esta acción no se puede deshacer.',
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
                        <div><span class="text-gray-500">Stock / Mínimo:</span> <span x-text="(detail.stock_total ?? 0) + ' / ' + (detail.stock_seguridad ?? 0)"></span></div>
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
