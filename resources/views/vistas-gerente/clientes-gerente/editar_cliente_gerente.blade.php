@extends('layouts.sidebar-navigation')

@section('content')
<div class="relative mb-10">
    <h2 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Editar cliente</h2>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto">
    @if (session('success'))
        <div id="alert-success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Éxito:</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
        <script>
            setTimeout(() => {
                const el = document.getElementById('alert-success');
                if (el) el.style.display = 'none';
            }, 5000);
        </script>
    @endif

    @if ($errors->any())
        <div id="alert-error" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Errores:</strong>
            <ul class="list-disc ml-6">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <script>
            setTimeout(() => {
                const el = document.getElementById('alert-error');
                if (el) el.style.display = 'none';
            }, 5000);
        </script>
    @endif

    <form action="{{ route('clientes.update', $cliente->clave_cliente) }}" method="POST"
          class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Código cliente -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Código cliente</label>
                <input type="text"
                    name="codigo_cliente"
                    value="{{ old('codigo_cliente', $cliente->codigo_cliente) }}"
                    required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('codigo_cliente')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre completo</label>
                <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('nombre')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Empresa -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre empresa</label>
                <input type="text" name="empresa" value="{{ old('empresa', $cliente->nombre_empresa) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('empresa')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Teléfono -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                <input type="tel" name="telefono" value="{{ old('telefono', $cliente->telefono) }}"
                       pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('telefono')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Correo electrónico</label>
                <input type="email" name="correo" value="{{ old('correo', $cliente->correo_electronico) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('correo')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contacto adicional -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacto adicional</label>
                <input type="tel" name="contacto_adicional" value="{{ old('contacto_adicional', $cliente->contacto_adicional) }}"
                       pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('contacto_adicional')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Ubicación -->
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Ubicación</label>
                <input type="text" id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $cliente->ubicacion) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('ubicacion')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Dirección fiscal -->
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Dirección fiscal</label>
                <input type="text" name="direccion_fiscal" value="{{ old('direccion_fiscal', $cliente->direccion_fiscal) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('direccion_fiscal')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Datos fiscales -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">RFC / Datos fiscales</label>
                <input type="text" name="datos_fiscales" value="{{ old('datos_fiscales', $cliente->datos_fiscales) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('datos_fiscales')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contacto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de contacto</label>
                <input type="text" name="contacto" value="{{ old('contacto', $cliente->contacto) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('contacto')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('clientes') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Actualizar</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function initAutocomplete() {
    const input = document.getElementById('ubicacion');
    if (input) {
        const autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['geocode'],
            componentRestrictions: { country: 'mx' }
        });
    }
}
</script>

<!-- Google Places API -->
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initAutocomplete" async defer></script>
@endpush
