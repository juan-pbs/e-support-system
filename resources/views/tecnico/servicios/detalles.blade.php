@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Detalle del servicio')

@section('content')
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    /** @var \App\Models\OrdenServicio $orden */
    $oid   = $orden->id_orden_servicio ?? $orden->getKey();
    $folio = $orden->folio ?? ('ORD-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT));

    $isActaFirmada = mb_strtolower((string)($orden->acta_estado ?? '')) === 'firmada';

    // Moneda materiales no previstos (viene del controlador)
    $orderCurrency      = strtoupper($orderCurrency ?? ($orden->moneda ?? 'MXN'));
    $extrasBaseCurrency = strtoupper($extrasBaseCurrency ?? 'MXN');
    $orderExchangeRate  = $orderExchangeRate ?? (float)($orden->tasa_cambio ?? $orden->tipo_cambio ?? 1.0);

    $mnpUsesConversion  = $mnpUsesConversion ?? ($orderCurrency !== $extrasBaseCurrency && $orderExchangeRate > 1.0001);
@endphp

<div
    class="max-w-6xl mx-auto p-4 md:p-6 space-y-6"
    x-data="{
        previews: [],
        previewImages(event) {
            this.previews = [];
            const files = event.target.files || [];
            for (let i = 0; i < files.length; i++) {
                const f = files[i];
                this.previews.push({ name: f.name, url: URL.createObjectURL(f) });
            }
        },
        lightboxOpen: false,
        lightboxSrc: null,
        lightboxAlt: '',
        openLightbox(src, alt = '') {
            this.lightboxSrc = src;
            this.lightboxAlt = alt;
            this.lightboxOpen = true;
        },
        closeLightbox() { this.lightboxOpen = false; }
    }"
>

    {{-- MENSAJES DE ESTADO / ERRORES --}}
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold mb-1">Revisa los siguientes campos:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ENCABEZADO --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <x-boton-volver />

        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                Detalle de orden de servicio
            </h1>
            <p class="text-sm text-gray-500">
                Folio: <span class="font-mono">{{ $folio }}</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2 justify-end">
            {{-- Estado de la OS --}}
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                @if(($orden->estatus ?? $orden->estado ?? '') === 'Completada')
                    bg-green-100 text-green-800
                @elseif(($orden->estatus ?? $orden->estado ?? '') === 'En progreso')
                    bg-blue-100 text-blue-800
                @else
                    bg-gray-100 text-gray-700
                @endif
            ">
                {{ $orden->estatus ?? $orden->estado ?? 'Sin estatus' }}
            </span>

            {{-- Botón ACTA DE CONFORMIDAD (técnico) --}}
            <a href="{{ route('tecnico.ordenes.acta.vista', $oid) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M9 12h6m-8 4h8M9 8h2m2 0h2M7 4h10a2 2 0 012 2v14l-4-3-4 3-4-3-4 3V6a2 2 0 012-2z" />
                </svg>
                Acta de conformidad
            </a>
        </div>
    </div>

    {{-- AVISO DE BLOQUEO POR ACTA FIRMADA --}}
    @if($isActaFirmada)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs md:text-sm text-emerald-800">
            Esta orden ya cuenta con un acta de conformidad firmada.
            Los formularios de seguimiento e ingreso de materiales no previstos están bloqueados para evitar modificaciones.
        </div>
    @endif

    {{-- RESUMEN DE LA ORDEN --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 md:p-5">
        <div class="grid md:grid-cols-2 gap-3 text-sm">
            <p>
                <span class="font-medium text-gray-600">Cliente:</span>
                {{ $orden->cliente->nombre ?? '—' }}
            </p>
            <p>
                <span class="font-medium text-gray-600">Servicio:</span>
                {{ $orden->servicio ?? $orden->descripcion_servicio ?? '—' }}
            </p>
            <p>
                <span class="font-medium text-gray-600">Fecha de creación:</span>
                {{ optional($orden->created_at)->format('d/m/Y H:i') ?? '—' }}
            </p>
            <p>
                <span class="font-medium text-gray-600">Técnico(s):</span>
                @if(isset($tecnicos) && count($tecnicos))
                    {{ $tecnicos->pluck('name')->implode(', ') }}
                @elseif(method_exists($orden, 'tecnicos') && $orden->tecnicos)
                    {{ $orden->tecnicos->pluck('name')->implode(', ') }}
                @else
                    —
                @endif
            </p>
        </div>

        @if(!empty($orden->acta_pdf_hash))
            <div class="mt-3 text-xs text-gray-500">
                Huella SHA-256 del acta definitiva:
                <span class="font-mono break-all">{{ strtoupper($orden->acta_pdf_hash) }}</span>
            </div>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- COL 1 y 2: Seguimientos --}}
        <div class="lg:col-span-2 space-y-4">

            <div class="flex items-center justify-between">
                <h2 class="text-base md:text-lg font-semibold text-gray-800">
                    Seguimiento del servicio
                </h2>
            </div>

            {{-- FORMULARIO: nuevo seguimiento + imágenes --}}
            @if(!$isActaFirmada)
                <div class="bg-white border border-slate-200 rounded-xl p-4 md:p-5 space-y-3">
                    <form method="POST"
                          action="{{ route('tecnico.ordenes.seguimientos.store', $oid) }}"
                          enctype="multipart/form-data"
                          class="space-y-3">
                        @csrf

                        <div>
                            <label for="comentario" class="block text-xs font-semibold text-gray-600 mb-1">
                                Comentario de seguimiento (opcional)
                            </label>
                            <textarea
                                id="comentario"
                                name="comentario"
                                rows="3"
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Describe el avance, hallazgos o acciones realizadas en el servicio..."
                            >{{ old('comentario') }}</textarea>
                            <p class="mt-1 text-[11px] text-gray-500">
                                Puedes dejar el comentario vacío si solo quieres subir imágenes.
                            </p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-3 items-start">
                            <div>
                                <label for="imagenes" class="block text-xs font-semibold text-gray-600 mb-1">
                                    Imágenes (opcional)
                                </label>
                                <input
                                    type="file"
                                    name="imagenes[]"
                                    id="imagenes"
                                    multiple
                                    accept="image/*"
                                    @change="previewImages($event)"
                                    class="block w-full text-xs text-gray-700
                                           file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700
                                           hover:file:bg-indigo-100"
                                >
                                <p class="mt-1 text-[11px] text-gray-500">
                                    Puedes seleccionar varias imágenes. Máx. 4&nbsp;MB por archivo.
                                </p>

                                <div class="mt-2 grid grid-cols-3 md:grid-cols-4 gap-2" x-show="previews.length">
                                    <template x-for="(img, idx) in previews" :key="idx">
                                        <div class="aspect-video rounded-lg overflow-hidden border border-dashed border-slate-300">
                                            <img :src="img.url" :alt="img.name" class="w-full h-full object-cover">
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="flex md:justify-end mt-2 md:mt-6">
                                <button type="submit"
                                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-xs md:text-sm font-semibold text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M5 13l4 4L19 7" />
                                    </svg>
                                    Guardar seguimiento
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-xl p-4 md:p-5 text-xs md:text-sm text-gray-600">
                    El acta de conformidad de esta orden ya está <span class="font-semibold text-emerald-700">firmada</span>.
                    No es posible registrar nuevos comentarios de seguimiento ni subir más imágenes.
                </div>
            @endif

            @php
                $listaSeguimientos = collect();
                if (isset($seguimientos)) {
                    $listaSeguimientos = collect($seguimientos);
                } elseif (method_exists($orden, 'seguimientos') && $orden->seguimientos) {
                    $listaSeguimientos = $orden->seguimientos;
                }
            @endphp

            @if($listaSeguimientos->isEmpty())
                <div class="bg-white border border-dashed border-slate-200 rounded-xl p-4 text-sm text-gray-500">
                    Aún no hay comentarios de seguimiento registrados para esta orden.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($listaSeguimientos->sortByDesc('created_at') as $seg)
                        @php
                            $segId = $seg->id_seguimiento ?? $seg->id ?? $seg->getKey();
                            $texto = $seg->comentarios ?? $seg->observaciones ?? '(sin comentario)';
                        @endphp
                        <div class="bg-white border border-slate-200 rounded-xl p-3 md:p-4" x-data="{ editOpen:false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="text-sm text-gray-800">
                                        {{ $texto }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 flex flex-wrap gap-2">
                                        <span>{{ optional($seg->created_at)->format('d/m/Y H:i') ?? '' }}</span>
                                        @if(isset($seg->usuario) || isset($seg->user))
                                            <span>•</span>
                                            <span>
                                                Registrado por:
                                                {{ $seg->usuario->name ?? $seg->user->name ?? '—' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if(!$isActaFirmada)
                                    <div class="flex flex-col gap-1 items-end">
                                        <button type="button"
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                                @click="editOpen = !editOpen">
                                            Editar
                                        </button>

                                        <form method="POST"
                                              action="{{ route('tecnico.ordenes.seguimientos.destroy', [$oid, $segId]) }}"
                                              onsubmit="return confirm('¿Eliminar este comentario de seguimiento?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-xs text-red-600 hover:text-red-800 font-semibold">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            @if(!$isActaFirmada)
                                <div x-show="editOpen" x-cloak class="mt-3 border-t border-slate-200 pt-3">
                                    <form method="POST"
                                          action="{{ route('tecnico.ordenes.seguimientos.update', [$oid, $segId]) }}"
                                          class="space-y-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                                            Editar comentario
                                        </label>
                                        <textarea
                                            name="comentario"
                                            rows="2"
                                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >{{ old('comentario', $texto) }}</textarea>

                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                    class="px-3 py-1.5 text-xs rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50"
                                                    @click="editOpen = false">
                                                Cancelar
                                            </button>
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-semibold">
                                                Guardar cambios
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- GALERÍA DE IMÁGENES --}}
            @php
                $imagenesOrden = collect();
                if (isset($imagenes)) {
                    $imagenesOrden = collect($imagenes);
                } elseif (method_exists($orden, 'imagenes') && $orden->imagenes) {
                    $imagenesOrden = $orden->imagenes;
                }
            @endphp

            <div class="space-y-2">
                <h2 class="text-base md:text-lg font-semibold text-gray-800">
                    Imágenes registradas del servicio
                </h2>

                @if($imagenesOrden->isEmpty())
                    <div class="bg-white border border-dashed border-slate-200 rounded-xl p-4 text-sm text-gray-500">
                        Aún no se han subido imágenes para esta orden.
                    </div>
                @else
                    <div class="bg-white border border-slate-200 rounded-xl p-3 md:p-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            @foreach($imagenesOrden as $img)
                                @php
                                    $imgId = $img->id_imagen ?? $img->id ?? $img->getKey();
                                    $path = $img->ruta ?? $img->path ?? $img->imagen ?? null;
                                    $url  = $path
                                        ? (Str::startsWith($path, ['http://','https://','/'])
                                            ? $path
                                            : Storage::url($path))
                                        : null;
                                @endphp
                                @if($url)
                                    <div class="relative">
                                        <button
                                            type="button"
                                            class="block aspect-video bg-slate-100 rounded-lg overflow-hidden border border-slate-200 w-full"
                                            @click="openLightbox('{{ $url }}', 'Imagen del servicio')"
                                        >
                                            <img src="{{ $url }}" alt="Imagen de seguimiento"
                                                 class="w-full h-full object-cover">
                                        </button>

                                        @if(!$isActaFirmada)
                                            <form method="POST"
                                                  action="{{ route('tecnico.ordenes.imagenes.destroy', [$oid, $imgId]) }}"
                                                  onsubmit="return confirm('¿Eliminar esta imagen?');"
                                                  class="absolute top-1 right-1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="w-6 h-6 flex items-center justify-center rounded-full bg-black/60 hover:bg-black text-white text-xs font-bold">
                                                    ✕
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- COL 3: Materiales no previstos --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base md:text-lg font-semibold text-gray-800">
                    Materiales no previstos
                </h2>
            </div>

            {{-- FORMULARIO: nuevo material no previsto (TÉCNICO: solo descripcion + cantidad) --}}
            @if(!$isActaFirmada)
                <div class="bg-white border border-slate-200 rounded-xl p-3 md:p-4">
                    <form method="POST"
                          action="{{ route('tecnico.ordenes.extras.store', $oid) }}"
                          class="space-y-3">
                        @csrf

                        <div>
                            <label for="descripcion" class="block text-xs font-semibold text-gray-600 mb-1">
                                Concepto / descripción
                            </label>
                            <input
                                type="text"
                                id="descripcion"
                                name="descripcion"
                                value="{{ old('descripcion') }}"
                                required
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Ej. Cable UTP adicional, tornillería, etc."
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="cantidad" class="block text-xs font-semibold text-gray-600 mb-1">
                                    Cantidad
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    id="cantidad"
                                    name="cantidad"
                                    value="{{ old('cantidad', '1') }}"
                                    required
                                    class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-[11px] text-slate-600">
                                <span class="font-semibold text-slate-800">Precio:</span> lo asigna el gerente.<br>
                                Este material quedará como <span class="font-semibold">pendiente</span> hasta que se capture el precio.
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 hover:bg-slate-900 px-4 py-2 text-xs md:text-sm font-semibold text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M12 4v16m8-8H4" />
                                </svg>
                                Agregar material
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-xl p-3 md:p-4 text-xs md:text-sm text-gray-600">
                    El registro de nuevos materiales no previstos está bloqueado porque el acta de esta orden ya fue
                    <span class="font-semibold text-emerald-700">firmada</span>.
                </div>
            @endif

            @php
                $listaExtras = collect();
                if (isset($extras)) {
                    $listaExtras = collect($extras);
                } elseif (method_exists($orden, 'materialesExtras') && $orden->materialesExtras) {
                    $listaExtras = $orden->materialesExtras;
                }

                $totalExtrasDisplay = $extrasTotalDisplay ?? 0;
                $totalCantExtras    = $extrasCantidadTotal ?? 0;
                $pendientesPrecio   = $extrasPendientesPrecio ?? 0;
            @endphp

            @if($listaExtras->isEmpty())
                <div class="bg-white border border-dashed border-slate-200 rounded-xl p-4 text-sm text-gray-500">
                    No se han registrado materiales no previstos para esta orden.
                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 text-[11px] uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold border-b border-slate-200">Concepto</th>
                                <th class="px-3 py-2 text-right font-semibold border-b border-slate-200">Cant.</th>
                                <th class="px-3 py-2 text-right font-semibold border-b border-slate-200">
                                    P. unit. ({{ $orderCurrency }})
                                </th>
                                <th class="px-3 py-2 text-right font-semibold border-b border-slate-200">
                                    Importe ({{ $orderCurrency }})
                                </th>
                                @if(!$isActaFirmada)
                                    <th class="px-3 py-2 text-center font-semibold border-b border-slate-200">
                                        Acciones
                                    </th>
                                @endif
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @foreach($listaExtras as $extra)
                                @php
                                    $extraId     = $extra->id_material_extra ?? $extra->id ?? $extra->getKey();
                                    $cantDisplay = $extra->mnp_cantidad ?? ($extra->cantidad ?? 0);

                                    $puDisplay  = $extra->mnp_pu_display ?? (is_null($extra->precio_unitario) ? null : (float)$extra->precio_unitario);
                                    $subDisplay = $extra->mnp_sub_display ?? (is_null($puDisplay) ? null : ($cantDisplay * $puDisplay));

                                    $pendiente  = $extra->mnp_pendiente_precio ?? is_null($puDisplay);
                                @endphp

                                <tr x-data="{ editOpen:false }">
                                    <td class="px-3 py-2 text-[13px] text-gray-800 align-top">
                                        {{ $extra->descripcion ?? '—' }}
                                    </td>

                                    <td class="px-3 py-2 text-[13px] text-right text-gray-700 align-top">
                                        {{ number_format($cantDisplay, 2) }}
                                    </td>

                                    <td class="px-3 py-2 text-[13px] text-right text-gray-700 align-top">
                                        @if($pendiente)
                                            <span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">
                                                Pendiente
                                            </span>
                                        @else
                                            @if($orderCurrency === 'MXN')
                                                ${{ number_format($puDisplay, 2) }}
                                            @else
                                                {{ number_format($puDisplay, 2) }} {{ $orderCurrency }}
                                            @endif
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 text-[13px] text-right text-gray-900 font-medium align-top">
                                        @if($pendiente)
                                            <span class="text-slate-500 text-[12px]">—</span>
                                        @else
                                            @if($orderCurrency === 'MXN')
                                                ${{ number_format($subDisplay, 2) }}
                                            @else
                                                {{ number_format($subDisplay, 2) }} {{ $orderCurrency }}
                                            @endif
                                        @endif
                                    </td>

                                    @if(!$isActaFirmada)
                                        <td class="px-3 py-2 text-[12px] text-center align-top">
                                            <div class="flex flex-col items-center gap-1">
                                                <button type="button"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                                        @click="editOpen = !editOpen">
                                                    Editar
                                                </button>

                                                <form method="POST"
                                                      action="{{ route('tecnico.ordenes.extras.destroy', [$oid, $extraId]) }}"
                                                      onsubmit="return confirm('¿Eliminar este material no previsto?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-xs text-red-600 hover:text-red-800 font-semibold">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>

                                {{-- EDITAR (técnico: solo descripcion + cantidad) --}}
                                @if(!$isActaFirmada)
                                    <tr x-show="editOpen" x-cloak class="bg-slate-50">
                                        <td colspan="5" class="px-3 py-3">
                                            <form method="POST"
                                                  action="{{ route('tecnico.ordenes.extras.update', [$oid, $extraId]) }}"
                                                  class="grid grid-cols-1 gap-3 text-xs">
                                                @csrf
                                                @method('PUT')

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                                        Concepto / descripción
                                                    </label>
                                                    <input type="text"
                                                           name="descripcion"
                                                           value="{{ old('descripcion', $extra->descripcion ?? '') }}"
                                                           class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                           required>
                                                </div>

                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                                            Cantidad
                                                        </label>
                                                        <input type="number"
                                                               step="0.01"
                                                               min="0.01"
                                                               name="cantidad"
                                                               value="{{ old('cantidad', $extra->cantidad ?? $cantDisplay) }}"
                                                               class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                               required>
                                                    </div>

                                                    <div class="rounded-lg border border-slate-200 bg-white p-3 text-[11px] text-slate-600">
                                                        <span class="font-semibold text-slate-800">Precio:</span> lo asigna el gerente.<br>
                                                        @if($pendiente)
                                                            Estado actual: <span class="font-semibold text-amber-700">Pendiente</span>
                                                        @else
                                                            Ya tiene precio asignado por gerencia.
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="flex justify-end gap-2 mt-2">
                                                    <button type="button"
                                                            class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100"
                                                            @click="editOpen = false">
                                                        Cancelar
                                                    </button>
                                                    <button type="submit"
                                                            class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-semibold">
                                                        Guardar cambios
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="bg-slate-50">
                                <td colspan="{{ $isActaFirmada ? 3 : 4 }}" class="px-3 py-2 text-right text-[12px] font-semibold text-gray-700">
                                    Total cantidad (materiales)
                                </td>
                                <td class="px-3 py-2 text-right text-[13px] font-bold text-gray-900">
                                    {{ number_format($totalCantExtras, 2) }}
                                </td>
                            </tr>

                            <tr class="bg-slate-50">
                                <td colspan="{{ $isActaFirmada ? 3 : 4 }}" class="px-3 py-2 text-right text-[12px] font-semibold text-gray-700">
                                    Total con precio asignado
                                    @if($pendientesPrecio > 0)
                                        <span class="ml-2 text-[11px] font-semibold text-amber-700">
                                            ({{ $pendientesPrecio }} pendiente{{ $pendientesPrecio == 1 ? '' : 's' }})
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-[13px] font-bold text-gray-900">
                                    @if($orderCurrency === 'MXN')
                                        ${{ number_format($totalExtrasDisplay, 2) }}
                                    @else
                                        {{ number_format($totalExtrasDisplay, 2) }} {{ $orderCurrency }}
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($mnpUsesConversion)
                    <p class="mt-2 text-[11px] text-gray-500">
                        Material no previsto capturado en {{ $extrasBaseCurrency }}.
                        Mostrando equivalente en {{ $orderCurrency }}
                        usando la tasa de cambio de la orden ({{ $orderExchangeRate }}).
                    </p>
                @endif
            @endif
        </div>
    </div>

    {{-- MODAL LIGHTBOX PARA IMÁGENES --}}
    <div x-show="lightboxOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/70">
        <div class="relative max-w-4xl w-full px-4">
            <button type="button"
                    class="absolute right-0 -top-10 text-white/80 hover:text-white text-2xl"
                    @click="closeLightbox">
                &times;
            </button>
            <img :src="lightboxSrc"
                 :alt="lightboxAlt"
                 class="w-full max-h-[80vh] object-contain rounded-lg shadow-2xl bg-black">
        </div>
    </div>
</div>
@endsection
