@extends('layouts.sidebar-navigation')

@section('content')
<div class="relative mb-10">
    <h2 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Registrar emisor (Proveedor)</h2>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto">
    <form action="{{ route('proveedores.guardar') }}" method="POST" class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre (Emisor)</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required class="w-full border rounded-lg px-4 py-3">
                @error('nombre') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">RFC</label>
                <input type="text" name="rfc" value="{{ old('rfc') }}" required class="w-full border rounded-lg px-4 py-3">
                @error('rfc') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Alias</label>
                <input type="text" name="alias" value="{{ old('alias') }}" class="w-full border rounded-lg px-4 py-3">
                @error('alias') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Correo (opcional)</label>
                <input type="email" name="correo" value="{{ old('correo') }}" class="w-full border rounded-lg px-4 py-3">
                @error('correo') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                <input type="tel" name="telefono" value="{{ old('telefono') }}" pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required class="w-full border rounded-lg px-4 py-3">
                @error('telefono') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacto</label>
                <input type="text" name="contacto" value="{{ old('contacto') }}" class="w-full border rounded-lg px-4 py-3">
                @error('contacto') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                <input type="text" id="direccion" name="direccion" value="{{ old('direccion') }}" class="w-full border rounded-lg px-4 py-3">
                @error('direccion') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('proveedores.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Guardar</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function initAutocomplete() {
    const input = document.getElementById('direccion');
    if (input) {
        new google.maps.places.Autocomplete(input, {
            types: ['geocode'],
            componentRestrictions: { country: 'mx' }
        });
    }
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initAutocomplete" async defer></script>
@endpush
