@extends('layouts.sidebar-navigation')

@section('content')
@php
    // 1) Si vienes de otra vista y pasas ?redirect=...
    $redirectParam = request()->query('redirect');

    // 2) Prioridad: old (si validó mal) > $redirectTo (si tu controller lo manda) > query redirect > url()->previous()
    $backUrl = old('redirect_to', $redirectTo ?? $redirectParam ?? url()->previous());

    // 3) Evitar que el backUrl sea la misma página actual (por si referrer viene raro)
    $currentUrl = url()->full();
    if (!$backUrl || $backUrl === $currentUrl) {
        $backUrl = url()->previous();
    }
@endphp

<div class="relative mb-10">
    <h2 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Registrar nuevo cliente</h2>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto">
    <form action="{{ route('clientes.store') }}" method="POST" enctype="multipart/form-data"
        class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5">
        @csrf

        {{-- 🔥 Para regresar al origen al Guardar (si tu controller lo respeta) --}}
        <input type="hidden" name="redirect_to" value="{{ $backUrl }}">
        <!-- Código cliente -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Código cliente</label>
            <input type="text"
                name="codigo_cliente"
                value="{{ old('codigo_cliente') }}"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Ej. AARONNIEVE">
            @error('codigo_cliente')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Nombre -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre completo</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('nombre')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Empresa -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre empresa</label>
                <input type="text" name="empresa" value="{{ old('empresa') }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('empresa')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Teléfono -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Teléfono</label>
                <input type="tel" name="telefono" value="{{ old('telefono') }}"
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
                <input type="email" name="correo" value="{{ old('correo') }}" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('correo')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contacto adicional -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacto adicional</label>
                <input type="tel" name="contacto_adicional" value="{{ old('contacto_adicional') }}"
                    pattern="[0-9]{7,20}" title="Solo números (7 a 20 dígitos)"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('contacto_adicional')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Ubicación (Google Places) -->
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Ubicación</label>
                <input type="text" id="ubicacion" name="ubicacion" value="{{ old('ubicacion') }}"
                    placeholder="Ej. Santiago de Querétaro, Qro."
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('ubicacion')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Dirección fiscal -->
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Dirección fiscal</label>
                <input type="text" name="direccion_fiscal" value="{{ old('direccion_fiscal') }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('direccion_fiscal')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Datos fiscales -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">RFC / Datos fiscales</label>
                <input type="text" name="datos_fiscales" value="{{ old('datos_fiscales') }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('datos_fiscales')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contacto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de contacto</label>
                <input type="text" name="contacto" value="{{ old('contacto') }}"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('contacto')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            {{-- ✅ Cancelar: vuelve a la vista anterior REAL; si no hay historial útil, usa fallback $backUrl --}}
            <a href="{{ $backUrl }}"
               onclick="event.preventDefault(); goBackSafe(@json($backUrl));"
               class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                Cancelar
            </a>

            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // ✅ Back real con fallback
    function goBackSafe(fallbackUrl) {
        const ref = document.referrer || '';
        const sameOrigin = ref && ref.startsWith(window.location.origin);

        // Si hay historial y referrer útil, back es lo más fiel
        if (window.history.length > 1 && sameOrigin) {
            window.history.back();
            return;
        }

        // Si no hay historial confiable, usamos el fallback (redirect_to / redirect / previous)
        if (fallbackUrl) {
            window.location.href = fallbackUrl;
            return;
        }

        // Último recurso
        window.location.href = '/';
    }

    function initAutocomplete() {
        const input = document.getElementById('ubicacion');
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
