@extends('layouts.sidebar-navigation')

@section('title', 'Registrar nuevo empleado')

@section('content')
@php
    $actual = auth()->user();
    $esGerente = $actual && $actual->puesto === 'gerente'; // gerente ve todos los roles
    $esAdmin   = $actual && $actual->puesto === 'admin';   // admin solo puede crear técnicos
@endphp

<div class="relative mb-10">
    <h1 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Registrar nuevo empleado</h1>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto" x-data="crearEmpleadoSecurity()" x-init="init()">
    @if (session('success'))
        <div id="mensaje-exito" class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded border border-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div id="mensaje-error" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded border border-red-400">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div id="mensaje-error" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded border border-red-400">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form x-ref="form" action="{{ route('empleados.store') }}" method="POST" autocomplete="off"
          class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5"
          @submit.prevent="abrirModal('Autorizar alta de empleado')">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre completo</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-4 py-3">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full border rounded-lg px-4 py-3">
                @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Puesto</label>

                @if($esGerente)
                    <select name="puesto" class="w-full border rounded-lg px-4 py-3" required>
                        <option value="" disabled {{ old('puesto') ? '' : 'selected' }}>Selecciona un rol…</option>
                        <option value="gerente" {{ old('puesto')=='gerente'?'selected':'' }}>Gerente</option>
                        <option value="admin"   {{ old('puesto')=='admin'?'selected':'' }}>Administrador</option>
                        <option value="tecnico" {{ old('puesto')=='tecnico'?'selected':'' }}>Técnico</option>
                    </select>
                @else
                    {{-- Admin: solo técnico --}}
                    <select name="puesto" class="w-full border rounded-lg px-4 py-3" required>
                        <option value="tecnico" selected>Técnico</option>
                    </select>
                @endif

                @error('puesto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacto (opcional)</label>
                <input type="tel" name="contacto" value="{{ old('contacto') }}" pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full border rounded-lg px-4 py-3">
                @error('contacto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                <input type="password" name="password" required minlength="6" class="w-full border rounded-lg px-4 py-3" autocomplete="new-password">
                @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('empleados.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">Registrar empleado</button>
        </div>

        <!-- Modal de confirmación -->
        <div x-show="open" style="display:none" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-6" @click.away="cerrar()">
                <h3 class="text-lg font-semibold mb-2">Confirmación de seguridad</h3>
                <p class="text-sm text-gray-600 mb-4" x-text="motivo"></p>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Tu contraseña</label>
                    <input type="password" name="auth_password" x-ref="authpwd"
                           class="w-full border rounded-lg px-4 py-3"
                           required minlength="6"
                           autocomplete="new-password" readonly
                           onfocus="this.removeAttribute('readonly');">
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" class="px-4 py-2 rounded-lg border" @click="cerrar()">Cancelar</button>
                    <button type="button" class="px-4 py-2 rounded-lg bg-blue-600 text-white" @click="confirmar()">Confirmar</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function crearEmpleadoSecurity(){
    return {
        open:false,
        motivo:'',
        init(){},
        abrirModal(m){ this.motivo = m; this.open = true; this.$nextTick(()=> this.$refs.authpwd?.focus()); },
        cerrar(){ this.open = false; },
        confirmar(){ this.$refs.form.submit(); }
    }
}
setTimeout(() => {
  document.getElementById('mensaje-exito')?.remove();
  document.getElementById('mensaje-error')?.remove();
}, 5000);
</script>
@endpush
