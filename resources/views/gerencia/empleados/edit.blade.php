@extends('layouts.sidebar-navigation')

@section('title', 'Editar empleado')

@section('content')
@php
    $actual      = auth()->user();
    $esGerente   = $actual && $actual->puesto === 'gerente'; // gerente ve todos
    $esAdmin     = $actual && $actual->puesto === 'admin';   // admin restringido
    $isSelf      = $actual && $actual->id === $empleado->id;
    $targetRole  = strtolower($empleado->puesto ?? '');
@endphp

<div class="relative mb-10">
    <h2 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Editar empleado</h2>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto" x-data="editarEmpleadoSecurity('{{ $empleado->puesto }}', {{ $isSelf ? 'true' : 'false' }})" x-init="init()">
    @if (session('success'))
        <div id="success-message" class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded border border-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div id="error-message" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded border border-red-400">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div id="error-message" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded border border-red-400">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form x-ref="form" action="{{ route('empleados.update', $empleado->id) }}" method="POST" class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5" autocomplete="off"
          @submit.prevent="handleSubmit">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre completo</label>
                <input type="text" name="name" value="{{ old('name', $empleado->name) }}" required class="w-full border rounded-lg px-4 py-3">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Correo</label>
                <input type="email" name="email" value="{{ old('email', $empleado->email) }}" required class="w-full border rounded-lg px-4 py-3">
                @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Puesto</label>
                @php $p = old('puesto', $empleado->puesto); @endphp

                {{-- GERENTE: ve todos los roles --}}
                @if($esGerente)
                    <select name="puesto" class="w-full border rounded-lg px-4 py-3" required
                            x-ref="puesto" @change="onRoleChange($event)">
                        <option value="gerente" {{ $p=='gerente'?'selected':'' }}>Gerente</option>
                        <option value="admin"   {{ $p=='admin'?'selected':'' }}>Administrador</option>
                        <option value="tecnico" {{ $p=='tecnico'?'selected':'' }}>Técnico</option>
                    </select>
                @else
                    {{-- ADMIN: si el objetivo NO es técnico, bloquear cambio (solo lectura y preservar valor).
                               Si es técnico, solo permitir técnico. --}}
                    @if($targetRole !== 'tecnico')
                        <input type="hidden" name="puesto" value="{{ $p }}">
                        <div class="px-3 py-2 rounded-lg
                            @if($targetRole==='gerente') bg-indigo-50 text-indigo-800 @elseif($targetRole==='admin') bg-purple-50 text-purple-800 @else bg-gray-50 text-gray-800 @endif
                        ">
                            {{ ucfirst($p) }} (solo modificable por un Gerente)
                        </div>
                    @else
                        <select name="puesto" class="w-full border rounded-lg px-4 py-3" required
                                x-ref="puesto" @change="onRoleChange($event)">
                            <option value="tecnico" selected>Técnico</option>
                        </select>
                    @endif
                @endif

                @error('puesto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacto (opcional)</label>
                <input type="tel" name="contacto" value="{{ old('contacto', $empleado->contacto) }}" pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full border rounded-lg px-4 py-3">
                @error('contacto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nueva contraseña (opcional)</label>
                <input type="password" name="password" minlength="6" class="w-full border rounded-lg px-4 py-3" placeholder="Déjalo vacío para no cambiar" x-ref="newpwd"
                       @input="onPwdChange($event)" autocomplete="new-password">
                @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('empleados.index') }}" class="inline-block bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg mr-2">Cancelar</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Actualizar empleado</button>
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
function editarEmpleadoSecurity(rolOriginal, isSelf){
    const rank = { tecnico:1, admin:2, gerente:3 };
    return {
        open:false,
        motivo:'',
        needAuth:false,
        bajaSelf:false,
        changePwdOther:false,
        init(){},
        onRoleChange(e){
            const nuevo = (e.target.value || '').toLowerCase();
            const baja = rank[nuevo] < (rank[rolOriginal?.toLowerCase()] || 0);
            this.bajaSelf = !!(isSelf && baja);
        },
        onPwdChange(e){
            const val = (e.target.value || '').trim();
            this.changePwdOther = (!isSelf) && val.length > 0;
        },
        handleSubmit(){
            this.needAuth = this.bajaSelf || this.changePwdOther;
            if (this.needAuth){
                this.motivo = this.bajaSelf
                    ? 'Estás bajando tu propio rol. Confirma con tu contraseña.'
                    : 'Vas a cambiar la contraseña de otro usuario. Confirma con tu contraseña.';
                this.open = true;
                this.$nextTick(()=> this.$refs.authpwd?.focus());
            } else {
                this.$refs.form.submit();
            }
        },
        cerrar(){ this.open = false; },
        confirmar(){ this.$refs.form.submit(); }
    }
}
setTimeout(() => {
  document.getElementById('success-message')?.remove();
  document.getElementById('error-message')?.remove();
}, 5000);
</script>
@endpush
