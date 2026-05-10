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

    $clienteDireccionesIniciales = collect(old('direcciones_logisticas', [
        ['alias' => 'Principal', 'predeterminada' => true],
    ]))->values()->all();
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

            <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4"
                 x-data="window.clienteDireccionesManager(window.clienteDireccionesIniciales || [])"
                 x-init="init()">
                <input type="hidden" name="ubicacion" x-model="ubicacionResumen">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Direcciones logísticas</label>
                        <p class="text-xs text-gray-500">Puedes registrar varias direcciones con alias para entregas o recolecciones.</p>
                    </div>
                    <button type="button" @click="addDireccion()"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Agregar dirección
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <template x-for="(direccion, index) in direcciones" :key="direccion.uid">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Alias</label>
                                    <input type="text"
                                        x-model="direccion.alias"
                                        :name="`direcciones_logisticas[${index}][alias]`"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                        placeholder="Ej. Oficina matriz">
                                </div>

                                <div class="flex items-end gap-2">
                                    <button type="button" @click="openPicker(index)"
                                        class="flex-1 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 hover:bg-blue-100">
                                        Seleccionar en mapa
                                    </button>
                                    <button type="button" @click="markPrimary(index)"
                                        class="rounded-lg px-4 py-3 text-sm font-medium"
                                        :class="direccion.predeterminada ? 'bg-emerald-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100'">
                                        Principal
                                    </button>
                                    <button type="button" @click="removeDireccion(index)"
                                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-100"
                                        x-show="direcciones.length > 1">
                                        Quitar
                                    </button>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Dirección</label>
                                    <input type="text"
                                        x-model="direccion.direccion_formateada"
                                        :name="`direcciones_logisticas[${index}][direccion_formateada]`"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                        placeholder="Selecciona la dirección en el mapa"
                                        readonly
                                        @click="openPicker(index)">
                                    <p class="mt-1 text-xs text-gray-500">La dirección se define desde el buscador o el mapa para guardar coordenadas válidas.</p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Referencia</label>
                                    <textarea
                                        x-model="direccion.referencia"
                                        :name="`direcciones_logisticas[${index}][referencia]`"
                                        rows="2"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                        placeholder="Ej. portón lateral, segunda planta, acceso junto a recepción"></textarea>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                                <span class="rounded-full px-3 py-1 font-medium"
                                    :class="direccion.verificada_en_mapa ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    <span x-text="direccion.verificada_en_mapa ? 'Verificada en mapa' : 'Pendiente de validar'"></span>
                                </span>
                                <span class="text-gray-500" x-show="direccion.latitud && direccion.longitud"
                                    x-text="`${Number(direccion.latitud).toFixed(6)}, ${Number(direccion.longitud).toFixed(6)}`"></span>
                            </div>

                            <input type="hidden" :name="`direcciones_logisticas[${index}][id]`" x-model="direccion.id">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][place_id]`" x-model="direccion.place_id">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][latitud]`" x-model="direccion.latitud">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][longitud]`" x-model="direccion.longitud">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][predeterminada]`" :value="direccion.predeterminada ? 1 : 0">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][verificada_en_mapa]`" :value="direccion.verificada_en_mapa ? 1 : 0">
                            <input type="hidden" :name="`direcciones_logisticas[${index}][metodo_verificacion]`" x-model="direccion.metodo_verificacion">
                        </div>
                    </template>
                </div>

                @error('direcciones_logisticas')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
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

@include('partials.logistica.address-picker-modal')
@endsection

@push('scripts')
<script>
    window.clienteDireccionesIniciales = @json($clienteDireccionesIniciales);

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

    function clienteDireccionesManager(initialDirecciones) {
        return {
            direcciones: [],
            ubicacionResumen: @js(old('ubicacion', '')),
            init() {
                const seed = Array.isArray(initialDirecciones) && initialDirecciones.length
                    ? initialDirecciones
                    : [{ alias: 'Principal', predeterminada: true }];

                this.direcciones = seed.map((item, idx) => this.makeDireccion(item, idx === 0));
                this.syncUbicacion();
            },
            toBool(value) {
                return value === true || value === 1 || value === '1' || value === 'true';
            },
            makeDireccion(item = {}, fallbackPrimary = false) {
                return {
                    uid: this.makeUid(),
                    id: item.id || '',
                    alias: item.alias || '',
                    direccion_formateada: item.direccion_formateada || '',
                    place_id: item.place_id || '',
                    latitud: item.latitud || '',
                    longitud: item.longitud || '',
                    referencia: item.referencia || '',
                    predeterminada: this.toBool(item.predeterminada) || fallbackPrimary,
                    verificada_en_mapa: this.toBool(item.verificada_en_mapa),
                    metodo_verificacion: item.metodo_verificacion || '',
                };
            },
            makeUid() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID();
                }

                return `dir-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            },
            addDireccion() {
                this.direcciones.push(this.makeDireccion({
                    alias: `Direccion ${this.direcciones.length + 1}`,
                    predeterminada: false,
                }, false));
            },
            removeDireccion(index) {
                this.direcciones.splice(index, 1);
                if (!this.direcciones.some(d => d.predeterminada) && this.direcciones[0]) {
                    this.direcciones[0].predeterminada = true;
                }
                this.syncUbicacion();
            },
            markPrimary(index) {
                this.direcciones.forEach((direccion, idx) => {
                    direccion.predeterminada = idx === index;
                });
                this.syncUbicacion();
            },
            openPicker(index) {
                const direccion = this.direcciones[index];
                if (!direccion || !window.LogisticaAddressPicker) return;

                window.LogisticaAddressPicker.open(direccion, (payload) => {
                    const normalized = this.normalizePickerPayload(payload);
                    Object.assign(direccion, payload, normalized);
                    direccion.verificada_en_mapa = true;
                    this.syncUbicacion();
                });
            },
            normalizePickerPayload(payload = {}) {
                const placeId = payload.place_id || payload.placeId || '';
                const latitud = payload.latitud ?? payload.lat ?? '';
                const longitud = payload.longitud ?? payload.lng ?? payload.lon ?? '';

                return {
                    direccion_formateada: payload.direccion_formateada || payload.formatted_address || payload.address || '',
                    place_id: placeId,
                    latitud,
                    longitud,
                    metodo_verificacion: payload.metodo_verificacion || payload.metodo || payload.method || (placeId ? 'autocomplete' : 'mapa'),
                };
            },
            syncUbicacion() {
                const principal = this.direcciones.find(d => d.predeterminada) || this.direcciones[0];
                this.ubicacionResumen = principal?.direccion_formateada || '';
            },
        };
    }

    window.clienteDireccionesManager = clienteDireccionesManager;
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('clienteDireccionesManager', clienteDireccionesManager);
    });
</script>
@endpush
