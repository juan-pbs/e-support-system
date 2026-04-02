@extends('layouts.sidebar-navigation')

@section('title', 'Cotizaciones')

@section('content')
@php
    $acUrl = \Illuminate\Support\Facades\Route::has('cotizaciones.autocomplete')
        ? route('cotizaciones.autocomplete')
        : url('/cotizaciones/autocomplete');
@endphp

<style>[x-cloak]{display:none !important}</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    {{-- Header responsive --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-center gap-3">
            <x-boton-volver />
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800">
                Cotizaciones
            </h1>
        </div>

        <a href="{{ route('cotizaciones.crear') }}"
           class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
            <i class="fas fa-plus"></i> Nueva cotización
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(()=>show=false, 5000)"
             class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-300 shadow">
            <strong>Éxito:</strong> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(()=>show=false, 7000)"
             class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300 shadow">
            <strong>Error:</strong> {{ session('error') }}
        </div>
    @endif

    {{-- Filtro rápido (responsive) --}}
    <form method="GET" action="{{ route('cotizaciones.vista') }}" class="mb-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">

            <div class="md:col-span-3 relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>

                <input
                    id="buscar-cotizaciones"
                    type="text"
                    name="buscar"
                    value="{{ request('buscar') }}"
                    placeholder="Buscar por SET-#, cliente, correo o descripción…"
                    autocomplete="off"
                    class="w-full border px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500"
                />

                <input
                    id="cotizacion-id"
                    type="hidden"
                    name="cotizacion_id"
                    value="{{ request('cotizacion_id') }}"
                />

                <div id="resultados-cotizaciones"
                     class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden hidden">
                    <ul id="resultados-lista" class="max-h-72 overflow-auto"></ul>
                </div>

                <p class="text-[11px] text-gray-500 mt-1">
                    Si eliges una sugerencia (SET-###), filtra exacto. Si escribes texto, busca por cliente/correo/descripcion.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 md:justify-end">
                <button class="w-full sm:w-auto px-4 py-2 rounded-lg bg-gray-800 text-white">Filtrar</button>

                <a href="{{ route('cotizaciones.vista') }}"
                   class="w-full sm:w-auto text-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </div>
    </form>

    {{-- ========================= --}}
    {{-- MÓVIL: TARJETAS --}}
    {{-- ========================= --}}
    <div class="md:hidden space-y-3">
        @forelse($cotizaciones as $c)
            @php
                $vencida = $c->vigencia ? \Carbon\Carbon::parse($c->vigencia)->isPast() : false;
                $ultimaEdicion   = $c->last_edited_at ?: $c->updated_at;
                $ultimaProcesado = $c->last_processed_at;

                $processCount = (int)($c->process_count ?? 0);
                $editCount    = (int)($c->edit_count ?? 0);
            @endphp

            <div class="bg-white border rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900">
                            SET-{{ $c->id_cotizacion }}
                        </div>

                        <div class="text-sm text-gray-900 mt-1 break-words">
                            {{ $c->cliente->nombre ?? '—' }}
                        </div>

                        <div class="text-xs text-gray-500 break-words">
                            {{ $c->cliente->correo_electronico ?? '' }}
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-sm font-bold text-gray-900 whitespace-nowrap">
                            ${{ number_format($c->total, 2) }} {{ $c->moneda }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-sm text-gray-700 break-words">
                    <span class="text-xs text-gray-500">Descripción:</span>
                    <div class="mt-1">
                        {{ $c->descripcion ?: '—' }}
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Fecha</span>
                        <span class="font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-3">
                        <span class="text-xs text-gray-500">Vigencia</span>
                        <span class="font-medium text-gray-900">
                            {{ $c->vigencia ? \Carbon\Carbon::parse($c->vigencia)->format('d/m/Y') : '—' }}
                        </span>
                    </div>
                </div>

                {{-- Estatus --}}
                <div class="mt-3 flex flex-wrap gap-1">
                    <span class="px-2 py-0.5 rounded text-[11px] bg-emerald-100 text-emerald-800"
                          title="{{ $ultimaProcesado ? ('Último procesado: '.\Carbon\Carbon::parse($ultimaProcesado)->diffForHumans()) : 'Nunca procesada' }}">
                        Procesada ×{{ $processCount }}
                    </span>

                    @if($vencida)
                        <span class="px-2 py-0.5 rounded text-[11px] bg-rose-100 text-rose-800">Vencida</span>
                    @elseif($c->vigencia)
                        <span class="px-2 py-0.5 rounded text-[11px] bg-slate-100 text-slate-700">
                            Vence {{ \Carbon\Carbon::parse($c->vigencia)->diffForHumans(null, true) }}
                        </span>
                    @endif

                    <span class="px-2 py-0.5 rounded text-[11px] bg-amber-100 text-amber-800"
                          title="{{ $ultimaEdicion ? ('Última edición: '.\Carbon\Carbon::parse($ultimaEdicion)->diffForHumans()) : 'Nunca editada' }}">
                        Editada ×{{ $editCount }}
                    </span>

                    @if(!empty($c->estado_cotizacion))
                        <span class="px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-700">
                            {{ \Illuminate\Support\Str::title($c->estado_cotizacion) }}
                        </span>
                    @endif
                </div>

                {{-- Acciones móvil --}}
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="#"
                       class="text-center px-3 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 js-open-pdf"
                       data-url="{{ route('cotizaciones.verPDF', $c->id_cotizacion) }}"
                       data-title="Cotización SET-{{ $c->id_cotizacion }}">
                        PDF
                    </a>

                    <a href="{{ route('cotizaciones.editar', $c->id_cotizacion) }}"
                       class="text-center px-3 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                        Editar
                    </a>

                    <a href="{{ route('cotizaciones.procesar', $c->id_cotizacion) }}"
                       class="col-span-2 text-center px-3 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700">
                        Procesar a OS
                    </a>

                    <form action="{{ route('cotizaciones.eliminar', $c->id_cotizacion) }}" method="POST"
                          class="col-span-2"
                          onsubmit="return confirm('¿Eliminar la cotización SET-{{ $c->id_cotizacion }}?')">
                        @csrf @method('DELETE')
                        <button class="w-full px-3 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-6 text-center text-gray-500">
                No hay cotizaciones registradas.
            </div>
        @endforelse
    </div>

    {{-- ========================= --}}
    {{-- DESKTOP: TABLA --}}
    {{-- ========================= --}}
    <div class="hidden md:block overflow-x-auto bg-white rounded-xl border border-gray-200 shadow">
        <table class="min-w-[980px] w-full">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-3 text-left">#</th>
                    <th class="px-3 py-3 text-left">Cliente</th>
                    <th class="px-3 py-3 text-left">Descripción</th>
                    <th class="px-3 py-3 text-left">Fecha</th>
                    <th class="px-3 py-3 text-left">Vigencia</th>
                    <th class="px-3 py-3 text-left">Moneda</th>
                    <th class="px-3 py-3 text-right">Total</th>
                    <th class="px-3 py-3 text-left">Estatus</th>
                    <th class="px-3 py-3 text-center">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y">
                @forelse($cotizaciones as $c)
                    @php
                        $vencida = $c->vigencia ? \Carbon\Carbon::parse($c->vigencia)->isPast() : false;
                        $ultimaEdicion   = $c->last_edited_at ?: $c->updated_at;
                        $ultimaProcesado = $c->last_processed_at;

                        $processCount = (int)($c->process_count ?? 0);
                        $editCount    = (int)($c->edit_count ?? 0);
                    @endphp

                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-3">SET-{{ $c->id_cotizacion }}</td>

                        <td class="px-3 py-3">
                            <div class="font-medium">{{ $c->cliente->nombre ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $c->cliente->correo_electronico ?? '' }}</div>
                        </td>

                        <td class="px-3 py-3 text-sm text-gray-700">
                            <div class="line-clamp-2">{{ $c->descripcion ?: '—' }}</div>
                        </td>

                        <td class="px-3 py-3 text-sm">
                            {{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}
                        </td>

                        <td class="px-3 py-3 text-sm">
                            {{ $c->vigencia ? \Carbon\Carbon::parse($c->vigencia)->format('d/m/Y') : '—' }}
                        </td>

                        <td class="px-3 py-3 text-sm">{{ $c->moneda }}</td>

                        <td class="px-3 py-3 text-right font-semibold whitespace-nowrap">
                            ${{ number_format($c->total, 2) }} {{ $c->moneda }}
                        </td>

                        <td class="px-3 py-3">
                            <div class="flex flex-wrap items-center gap-1">
                                <span class="px-2 py-0.5 rounded text-[11px] bg-emerald-100 text-emerald-800"
                                      title="{{ $ultimaProcesado ? ('Último procesado: '.\Carbon\Carbon::parse($ultimaProcesado)->diffForHumans()) : 'Nunca procesada' }}">
                                    Procesada ×{{ $processCount }}
                                </span>

                                @if($vencida)
                                    <span class="px-2 py-0.5 rounded text-[11px] bg-rose-100 text-rose-800">Vencida</span>
                                @elseif($c->vigencia)
                                    <span class="px-2 py-0.5 rounded text-[11px] bg-slate-100 text-slate-700">
                                        Vence {{ \Carbon\Carbon::parse($c->vigencia)->diffForHumans(null, true) }}
                                    </span>
                                @endif

                                <span class="px-2 py-0.5 rounded text-[11px] bg-amber-100 text-amber-800"
                                      title="{{ $ultimaEdicion ? ('Última edición: '.\Carbon\Carbon::parse($ultimaEdicion)->diffForHumans()) : 'Nunca editada' }}">
                                    Editada ×{{ $editCount }}
                                </span>

                                @if(!empty($c->estado_cotizacion))
                                    <span class="px-2 py-0.5 rounded text-[11px] bg-gray-100 text-gray-700">
                                        {{ \Illuminate\Support\Str::title($c->estado_cotizacion) }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-3 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="#"
                                   class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 js-open-pdf"
                                   data-url="{{ route('cotizaciones.verPDF', $c->id_cotizacion) }}"
                                   data-title="Cotización SET-{{ $c->id_cotizacion }}"
                                   title="Ver PDF">PDF</a>

                                <a href="{{ route('cotizaciones.editar', $c->id_cotizacion) }}"
                                   class="px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                   title="Editar">Editar</a>

                                <a href="{{ route('cotizaciones.procesar', $c->id_cotizacion) }}"
                                   class="px-2 py-1 text-xs rounded bg-green-600 text-white hover:bg-green-700"
                                   title="Procesar a OS">Procesar</a>

                                <form action="{{ route('cotizaciones.eliminar', $c->id_cotizacion) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar la cotización SET-{{ $c->id_cotizacion }}?')">
                                    @csrf @method('DELETE')
                                    <button class="px-2 py-1 text-xs rounded bg-red-600 text-white hover:bg-red-700"
                                            title="Eliminar">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-10 text-center text-gray-500">
                            No hay cotizaciones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $cotizaciones->withQueryString()->links() }}
    </div>
</div>

{{-- Modal para ver PDF --}}
<div id="pdfModal" class="fixed inset-0 z-50 hidden">
    <div id="pdfModalBackdrop" class="absolute inset-0 bg-black/50"></div>

    <div class="relative mx-auto w-full max-w-5xl h-[92vh] mt-4 md:mt-8 px-3">
        <div class="bg-white rounded-xl shadow-lg h-full flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <h3 id="pdfModalTitle" class="font-semibold text-gray-800 text-sm md:text-base">Ver PDF</h3>
                <div class="flex items-center gap-2">
                    <button id="pdfModalDownload"
                            class="hidden md:inline-flex px-3 py-1.5 text-xs rounded bg-gray-100 hover:bg-gray-200"
                            title="Descargar PDF">Descargar</button>
                    <button id="pdfModalClose"
                            class="px-3 py-1.5 text-sm rounded bg-gray-800 text-white hover:bg-black"
                            title="Cerrar">Cerrar</button>
                </div>
            </div>
            <div class="flex-1">
                <iframe id="pdfFrame" class="w-full h-full border-0" title="Visor PDF"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    // ===== PDF MODAL =====
    const modal      = document.getElementById('pdfModal');
    const backdrop   = document.getElementById('pdfModalBackdrop');
    const frame      = document.getElementById('pdfFrame');
    const titleEl    = document.getElementById('pdfModalTitle');
    const btnClose   = document.getElementById('pdfModalClose');
    const btnDownload= document.getElementById('pdfModalDownload');
    let currentPdfUrl = null;

    function openPdfModal(url, title = 'Ver PDF') {
        currentPdfUrl = url;
        const sep = url.includes('?') ? '&' : '?';
        frame.src = url + sep + 'ts=' + Date.now();
        titleEl.textContent = title;
        modal.classList.remove('hidden');
        btnDownload.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closePdfModal() {
        modal.classList.add('hidden');
        frame.src = 'about:blank';
        currentPdfUrl = null;
        btnDownload.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('.js-open-pdf').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const url   = btn.getAttribute('data-url');
            const title = btn.getAttribute('data-title') || 'Ver PDF';
            if (!url) return;
            openPdfModal(url, title);
        });
    });

    btnClose.addEventListener('click', closePdfModal);
    backdrop.addEventListener('click', closePdfModal);

    btnDownload.addEventListener('click', () => {
        if (!currentPdfUrl) return;
        const sep = currentPdfUrl.includes('?') ? '&' : '?';
        const downloadUrl = currentPdfUrl + sep + 'download=1';
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePdfModal();
    });

    // ===== AUTOCOMPLETE COTIZACIONES (submit automático) =====
    const acUrl = @json($acUrl);
    const input = document.getElementById('buscar-cotizaciones');
    const hiddenId = document.getElementById('cotizacion-id');

    const box = document.getElementById('resultados-cotizaciones');
    const ul  = document.getElementById('resultados-lista');

    const form = input ? input.closest('form') : null;

    let t = null;

    function hideBox() {
        box.classList.add('hidden');
        ul.innerHTML = '';
    }

    function showBox() {
        box.classList.remove('hidden');
    }

    function renderItems(items) {
        ul.innerHTML = '';
        if (!items || items.length === 0) {
            hideBox();
            return;
        }

        items.forEach(it => {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer border-b last:border-b-0';
            li.textContent = it.label;

            li.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                input.value = it.label;
                hiddenId.value = it.id ?? '';

                hideBox();
                if (form) form.submit();
            });

            ul.appendChild(li);
        });

        showBox();
    }

    async function fetchAutocomplete(term) {
        const url = acUrl + (acUrl.includes('?') ? '&' : '?') + 'term=' + encodeURIComponent(term);
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        if (!res.ok) return [];
        return await res.json();
    }

    input?.addEventListener('input', () => {
        hiddenId.value = '';
        const term = input.value.trim();
        clearTimeout(t);

        if (term.length < 2) {
            hideBox();
            return;
        }

        t = setTimeout(async () => {
            try {
                const items = await fetchAutocomplete(term);
                renderItems(items);
            } catch (e) {
                hideBox();
            }
        }, 180);
    });

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && form) {
            e.preventDefault();
            form.submit();
        }
        if (e.key === 'Escape') hideBox();
    });

    document.addEventListener('click', (e) => {
        if (!box.contains(e.target) && e.target !== input) hideBox();
    });

    form?.addEventListener('submit', () => {
        if (!hiddenId.value) {
            const num = (input.value || '').replace(/\D+/g, '');
            if (num) hiddenId.value = num;
        }
    });

});
</script>
@endpush
