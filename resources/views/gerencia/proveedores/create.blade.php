@extends('layouts.sidebar-navigation')

@section('content')
<div class="relative mb-10">
    <h2 class="text-xl sm:text-2xl font-bold text-black-600 text-center">Registrar emisor (Proveedor)</h2>
    <x-boton-volver />
</div>

<div class="max-w-7xl mx-auto">
    <form action="{{ route('proveedores.guardar') }}" method="POST" class="bg-white border border-gray-200 shadow-xl rounded-xl p-6 space-y-5"
          x-data="proveedorDireccionManager(@js([
            'direccion_logistica' => old('direccion_logistica', old('direccion')),
            'direccion_logistica_place_id' => old('direccion_logistica_place_id'),
            'direccion_logistica_latitud' => old('direccion_logistica_latitud'),
            'direccion_logistica_longitud' => old('direccion_logistica_longitud'),
            'direccion_logistica_referencia' => old('direccion_logistica_referencia'),
            'direccion_logistica_verificada_en_mapa' => old('direccion_logistica_verificada_en_mapa'),
            'direccion_logistica_metodo' => old('direccion_logistica_metodo'),
          ]))"
          x-init="init()">
        @csrf
        <input type="hidden" name="direccion" x-model="direccion_formateada">
        <input type="hidden" name="direccion_logistica" x-model="direccion_formateada">
        <input type="hidden" name="direccion_logistica_place_id" x-model="place_id">
        <input type="hidden" name="direccion_logistica_latitud" x-model="latitud">
        <input type="hidden" name="direccion_logistica_longitud" x-model="longitud">
        <input type="hidden" name="direccion_logistica_verificada_en_mapa" :value="verificada_en_mapa ? 1 : 0">
        <input type="hidden" name="direccion_logistica_metodo" x-model="metodo_verificacion">

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

            <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dirección logística</label>
                        <p class="text-xs text-gray-500">La recolección programada usará esta dirección validada.</p>
                    </div>
                    <button type="button" @click="openPicker()"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Seleccionar en mapa
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Dirección seleccionada</label>
                        <input type="text" x-model="direccion_formateada"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Selecciona una dirección verificada en mapa"
                            readonly
                            @click="openPicker()">
                        <p class="mt-1 text-xs text-gray-500">La dirección se captura desde el mapa para guardar coordenadas reales del proveedor.</p>
                        @error('direccion_logistica') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        @error('direccion_logistica_verificada_en_mapa') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Referencia</label>
                        <textarea name="direccion_logistica_referencia" x-model="referencia" rows="2"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej. acceso por patio, bodega gris, oficina 2"></textarea>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                    <span class="rounded-full px-3 py-1 font-medium"
                        :class="verificada_en_mapa ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                        <span x-text="verificada_en_mapa ? 'Verificada en mapa' : 'Pendiente de validar'"></span>
                    </span>
                    <span class="text-gray-500" x-show="latitud && longitud"
                        x-text="`${Number(latitud).toFixed(6)}, ${Number(longitud).toFixed(6)}`"></span>
                </div>

                @error('direccion') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('proveedores.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">Cancelar</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Guardar</button>
        </div>
    </form>
</div>
@endsection

@include('partials.logistica.address-picker-modal')
@push('scripts')
<script>
function proveedorDireccionManager(initial) {
    return {
        direccion_formateada: '',
        place_id: '',
        latitud: '',
        longitud: '',
        referencia: '',
        verificada_en_mapa: false,
        metodo_verificacion: '',
        toBool(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        },
        init() {
            this.direccion_formateada = initial?.direccion_logistica || '';
            this.place_id = initial?.direccion_logistica_place_id || '';
            this.latitud = initial?.direccion_logistica_latitud || '';
            this.longitud = initial?.direccion_logistica_longitud || '';
            this.referencia = initial?.direccion_logistica_referencia || '';
            this.verificada_en_mapa = this.toBool(initial?.direccion_logistica_verificada_en_mapa);
            this.metodo_verificacion = initial?.direccion_logistica_metodo || '';
        },
        openPicker() {
            if (!window.LogisticaAddressPicker) return;
            window.LogisticaAddressPicker.open({
                direccion_formateada: this.direccion_formateada,
                place_id: this.place_id,
                latitud: this.latitud,
                longitud: this.longitud,
                referencia: this.referencia,
                verificada_en_mapa: this.verificada_en_mapa,
                metodo_verificacion: this.metodo_verificacion,
            }, (payload) => {
                this.direccion_formateada = payload.direccion_formateada || '';
                this.place_id = payload.place_id || '';
                this.latitud = payload.latitud || '';
                this.longitud = payload.longitud || '';
                this.referencia = payload.referencia || '';
                this.verificada_en_mapa = this.toBool(payload.verificada_en_mapa);
                this.metodo_verificacion = payload.metodo_verificacion || '';
            });
        },
    };
}
</script>
@endpush
