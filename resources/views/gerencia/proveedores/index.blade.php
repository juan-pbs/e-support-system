@extends('layouts.sidebar-navigation')

@section('title', 'Proveedores (Emisores)')

@section('content')
<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="proveedoresUI()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <x-boton-volver />
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 flex-1 text-center md:text-left">
            Proveedores (Emisores)
        </h1>
        <div class="w-8 md:hidden"></div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div id="alert-success" class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-300 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div id="alert-error" class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300 shadow-sm">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Buscador + botón --}}
    <div class="bg-white shadow-xl border border-gray-200 rounded-xl p-4 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="flex-1">
                <x-barra-busqueda-live
                    :action="route('proveedores.index')"
                    autocompleteUrl="{{ route('proveedores.autocomplete') }}"
                    placeholder="Buscar emisor por nombre, RFC, correo, teléfono o alias..."
                    inputId="buscar-proveedor"
                    resultId="resultados-proveedor"
                    name="buscar"
                />
            </div>

            <div class="lg:w-auto">
                <a href="{{ route('proveedores.nuevo') }}"
                   class="w-full lg:w-auto justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2">
                    <i class="fas fa-user-plus"></i>
                    Nuevo emisor
                </a>
            </div>
        </div>
    </div>

    {{-- ====== MÓVIL/TABLET: TARJETAS (hasta <lg) ====== --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($proveedores as $p)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-gray-900 truncate">
                            {{ $p->nombre }}
                        </p>

                        <div class="mt-1 text-sm text-gray-600 space-y-1">
                            <p class="break-all">
                                <span class="text-gray-500">RFC:</span>
                                <span class="font-medium text-gray-800">{{ $p->rfc ?? '—' }}</span>
                            </p>

                            <p class="break-all">
                                <span class="text-gray-500">Alias:</span>
                                <span class="font-medium text-gray-800">{{ $p->alias ?? '—' }}</span>
                            </p>

                            <p class="break-all">
                                <span class="text-gray-500">Correo:</span>
                                <span class="font-medium text-gray-800">{{ $p->correo ?? '—' }}</span>
                            </p>

                            <p>
                                <span class="text-gray-500">Tel:</span>
                                <span class="font-medium text-gray-800">{{ $p->telefono ?? '—' }}</span>
                            </p>

                            <p class="break-all">
                                <span class="text-gray-500">Contacto:</span>
                                <span class="font-medium text-gray-800">{{ $p->contacto ?? '—' }}</span>
                            </p>

                            <p class="break-words">
                                <span class="text-gray-500">Dirección:</span>
                                <span class="font-medium text-gray-800">{{ $p->direccion ?? '—' }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ route('proveedores.editar', $p->clave_proveedor) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2">
                        <i class="fas fa-edit"></i>
                        <span>Editar</span>
                    </a>

                    <button type="button"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            @click="abrirEliminar({{ $p->clave_proveedor }})">
                        <i class="fas fa-trash"></i>
                        <span>Eliminar</span>
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500">
                No hay emisores registrados.
            </div>
        @endforelse
    </div>

    {{-- ====== ESCRITORIO: TABLA (lg y arriba) ====== --}}
    <div class="hidden lg:block bg-white shadow-xl border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Nombre (Emisor)</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">RFC</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Alias</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Correo</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Teléfono</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Contacto</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Dirección</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Acciones</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($proveedores as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">{{ $p->nombre }}</td>
                            <td class="px-6 py-3">{{ $p->rfc ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $p->alias ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $p->correo ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $p->telefono ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $p->contacto ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $p->direccion ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <a href="{{ route('proveedores.editar', $p->clave_proveedor) }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <button type="button"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg"
                                            title="Eliminar"
                                            @click="abrirEliminar({{ $p->clave_proveedor }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                No hay emisores registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $proveedores->links() }}
        </div>
    </div>

    {{-- Paginación móvil --}}
    <div class="mt-4 lg:hidden">
        {{ $proveedores->links() }}
    </div>

    {{-- Modal confirmación --}}
    <div x-cloak x-show="showModal"
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         style="display:none">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-5 sm:p-6" @click.away="cerrar()">
            <h2 class="text-lg font-semibold text-red-600 mb-2">Confirmar eliminación</h2>
            <p class="text-sm text-gray-700 mb-4">¿Eliminar este emisor?</p>

            <form method="POST" :action="`/proveedores/${proveedorId}`">
                @csrf
                @method('DELETE')

                <div class="flex flex-col sm:flex-row justify-end gap-2">
                    <button type="button"
                            @click="cerrar()"
                            class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                        Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function proveedoresUI(){
    return {
        showModal:false,
        proveedorId:null,
        init(){},
        abrirEliminar(id){
            this.proveedorId = id;
            this.showModal = true;
        },
        cerrar(){
            this.showModal = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const ok = document.getElementById('alert-success');
    const err = document.getElementById('alert-error');
    if (ok)  setTimeout(() => ok.remove(), 5000);
    if (err) setTimeout(() => err.remove(), 5000);
});
</script>
@endpush
