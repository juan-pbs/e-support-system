@extends('layouts.sidebar-navigation')

@section('title', 'Administrar Empleados')

@section('content')
@php
    $yo = auth()->user();
@endphp

<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="eliminarEmpleadoSecurity()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <x-boton-volver />
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 flex-1 text-center md:text-left">
            Administrar Empleados
        </h1>
        <div class="w-8 md:hidden"></div>
    </div>

    {{-- Alerts --}}
    @if (session('success'))
        <div id="success-message" class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded-lg border border-green-300 shadow-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div id="error-message" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded-lg border border-red-300 shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Buscador + botón --}}
    <div class="bg-white shadow-xl border border-gray-200 rounded-xl p-4 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="flex-1">
                <x-barra-busqueda-live
                    :action="route('empleados.index')"
                    autocompleteUrl="{{ route('empleados.autocomplete') }}"
                    placeholder="Buscar por nombre o correo…"
                    inputId="buscar-empleado"
                    resultId="resultados-empleado"
                    name="busqueda"
                />
            </div>

            <div class="lg:w-auto">
                <a href="{{ route('empleados.crear') }}"
                   class="w-full lg:w-auto justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2">
                    <i class="fas fa-user-plus"></i> Nuevo empleado
                </a>
            </div>
        </div>

        @if($yo && $yo->puesto === 'admin')
            <p class="text-xs text-gray-500 mt-3">
                Nota: como <strong>ADMIN</strong>, no verás usuarios con rol <strong>Gerente</strong> ni a tu propio usuario.
            </p>
        @endif
    </div>

    {{-- ====== MÓVIL/TABLET: TARJETAS (hasta <lg) ====== --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($empleados as $emp)
            @php
                $rol = strtolower($emp->puesto ?? '');
                $clase = match($rol) {
                    'gerente' => 'bg-indigo-100 text-indigo-800',
                    'admin'   => 'bg-purple-100 text-purple-800',
                    'tecnico' => 'bg-green-100 text-green-800',
                    default   => 'bg-gray-100 text-gray-800',
                };
            @endphp

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">ID: {{ $emp->id }}</p>
                        <p class="text-base font-semibold text-gray-900 truncate">{{ $emp->name }}</p>
                        <p class="text-sm text-gray-600 break-all">{{ $emp->email }}</p>
                    </div>

                    <span class="shrink-0 px-2 py-1 text-xs font-medium rounded {{ $clase }}">
                        {{ ucfirst($rol ?: '—') }}
                    </span>
                </div>

                <div class="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Contacto</span>
                        <span class="font-medium text-gray-800">{{ $emp->contacto ?? '—' }}</span>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('empleados.edit', $emp->id) }}"
                       class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2">
                        <i class="fas fa-edit"></i>
                        <span>Editar</span>
                    </a>

                    @if(!$yo || $yo->id !== $emp->id)
                        <button type="button"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                                @click="abrir({ id: {{ $emp->id }}, nombre: @js($emp->name), action: @js(route('empleados.destroy', $emp->id)) })">
                            <i class="fas fa-trash"></i>
                            <span>Eliminar</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500">
                No hay empleados registrados.
            </div>
        @endforelse
    </div>

    {{-- ====== ESCRITORIO: TABLA (lg y arriba) ====== --}}
    <div class="hidden lg:block bg-white shadow-xl border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">#</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Nombre</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Correo</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Puesto</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Contacto</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Acciones</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($empleados as $emp)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">{{ $emp->id }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $emp->name }}</td>
                            <td class="px-6 py-3">{{ $emp->email }}</td>
                            <td class="px-6 py-3">
                                @php
                                    $rol = strtolower($emp->puesto ?? '');
                                    $clase = match($rol) {
                                        'gerente' => 'bg-indigo-100 text-indigo-800',
                                        'admin'   => 'bg-purple-100 text-purple-800',
                                        'tecnico' => 'bg-green-100 text-green-800',
                                        default   => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <span class="px-2 py-1 text-xs font-medium rounded {{ $clase }}">
                                    {{ ucfirst($rol ?: '—') }}
                                </span>
                            </td>
                            <td class="px-6 py-3">{{ $emp->contacto ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <a href="{{ route('empleados.edit', $emp->id) }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    @if(!$yo || $yo->id !== $emp->id)
                                        <button type="button"
                                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg"
                                                title="Eliminar"
                                                @click="abrir({ id: {{ $emp->id }}, nombre: @js($emp->name), action: @js(route('empleados.destroy', $emp->id)) })">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-8 text-center text-gray-500" colspan="6">No hay empleados registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $empleados->links() }}
    </div>

    {{-- Modal eliminar --}}
    <div x-cloak x-show="open" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4" style="display:none">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-5 sm:p-6" @click.away="cerrar()">
            <h3 class="text-lg font-semibold mb-2 text-red-600">Confirmar eliminación</h3>
            <p class="text-sm text-gray-700 mb-4">
                Vas a eliminar al usuario <span class="font-semibold" x-text="nombre"></span>. Esta acción no se puede deshacer.
            </p>

            <form x-ref="form" :action="action" method="POST" class="space-y-3">
                @csrf
                @method('DELETE')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tu contraseña</label>
                    <input type="password" name="auth_password" x-ref="authpwd"
                           class="w-full border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-200"
                           required minlength="6"
                           autocomplete="new-password" readonly
                           onfocus="this.removeAttribute('readonly');">
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 mt-2">
                    <button type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg border" @click="cerrar()">Cancelar</button>
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-red-600 text-white">Eliminar</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function eliminarEmpleadoSecurity(){
    return {
        open:false,
        id:null,
        nombre:'',
        action:'',
        init(){},
        abrir({id, nombre, action}){
            this.id = id;
            this.nombre = nombre || '';
            this.action = action;
            this.open = true;
            this.$nextTick(() => this.$refs.authpwd?.focus());
        },
        cerrar(){ this.open = false; },
    }
}

setTimeout(() => {
  document.getElementById('success-message')?.remove();
  document.getElementById('error-message')?.remove();
}, 5000);
</script>
@endpush
