{{-- resources/views/vistas-gerente/reportes/seguimiento-servicios.blade.php --}}
@extends('layouts.sidebar-navigation')

@section('title', 'Vistas de Reportes')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Contenedor general: full width para aprovechar mejor la pantalla en PC --}}
<div class="w-full mx-auto p-4 md:p-6">
  <div class="overflow-hidden bg-white/90 backdrop-blur rounded-2xl shadow border border-gray-100">

    <!-- Header -->
    <div class="p-6 border-b bg-gradient-to-r from-slate-50 to-white">
      {{-- ✅ responsive: en móvil apila --}}
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start md:items-center gap-3">
          <x-boton-volver />

          <div class="shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M9 12.75 11.25 15 15 9.75M9 6h6m-8.25 15h10.5A2.25 2.25 0 0 0 19.5 18.75V7.5A2.25 2.25 0 0 0 17.25 5.25H15A3 3 0 0 0 12 3a3 3 0 0 0-3 2.25H6.75A2.25 2.25 0 0 0 4.5 7.5v11.25A2.25 2.25 0 0 0 6.75 21z" />
            </svg>
          </div>
          <div>
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Seguimiento de servicios</h2>
            <p class="text-gray-500 text-sm">Monitorea estado, prioridad y costos adicionales por quincena.</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button id="refreshBtn"
                  class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                    d="M21 12a9 9 0 1 1-3.5-7.07M21 3v6h-6" />
            </svg>
            Actualizar
          </button>
        </div>
      </div>
    </div>

    @if (session('error'))
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
        {{ session('error') }}
      </div>
    @endif

    <!-- Resumen (arriba) -->
    <div class="px-6 pt-4">
      <div id="serviceSummary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
    </div>

    <!-- Filtros + quincena -->
    <div class="p-6 border-b space-y-4">
      <!-- Selector de quincena -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Rango de fechas</p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg border bg-white shadow-sm overflow-hidden">
              <button id="prevQuincenaBtn" type="button"
                      class="px-3 py-1.5 text-xs md:text-sm border-r hover:bg-gray-50 flex items-center gap-1">
                <span class="text-lg leading-none">‹</span>
                <span class="hidden sm:inline">Anterior</span>
              </button>
              <button id="todayQuincenaBtn" type="button"
                      class="px-3 py-1.5 text-xs md:text-sm hover:bg-gray-50">
                Quincena actual
              </button>
              <button id="nextQuincenaBtn" type="button"
                      class="px-3 py-1.5 text-xs md:text-sm border-l hover:bg-gray-50 flex items-center gap-1">
                <span class="hidden sm:inline">Siguiente</span>
                <span class="text-lg leading-none">›</span>
              </button>
            </div>
            <span id="quincenaLabel" class="text-sm md:text-base font-medium text-gray-700"></span>
          </div>
        </div>
        <p class="text-xs text-gray-500 max-w-sm">
          La tabla y el resumen muestran únicamente las órdenes de servicio dentro de la quincena seleccionada.
        </p>
      </div>

      <!-- Filtros: estado, prioridad, moneda, técnico + leyenda de prioridades -->
      <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 md:items-end">
          <!-- Estado -->
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Estado</label>
            <div class="relative">
              <select id="statusFilter" class="w-full appearance-none border-gray-300 rounded-lg shadow-sm pr-10">
                <option value="all">Todos los estados</option>
                <option value="en-proceso">En proceso</option>
                <option value="finalizado-sin-firmar">Finalizado (sin firmar)</option>
                <option value="cancelado">Cancelado</option>
                <option value="finalizado">Finalizado</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Prioridad -->
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Prioridad</label>
            <div class="relative">
              <select id="priorityFilter" class="w-full appearance-none border-gray-300 rounded-lg shadow-sm pr-10">
                <option value="all">Todas</option>
                <option value="Baja">Baja</option>
                <option value="Media">Media</option>
                <option value="Alta">Alta</option>
                <option value="Urgente">Urgente</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Moneda -->
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Moneda</label>
            <div class="relative">
              <select id="currencyFilter" class="w-full appearance-none border-gray-300 rounded-lg shadow-sm pr-10">
                <option value="all">Todas</option>
                <option value="MXN">MXN</option>
                <option value="USD">USD</option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
            </div>
          </div>

          <!-- Técnico -->
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-700">Técnico</label>
            <input
              id="technicianFilter"
              type="text"
              placeholder="Nombre de técnico..."
              class="w-full border-gray-300 rounded-lg shadow-sm"
            >
          </div>
        </div>

        <!-- Leyenda prioridades -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="text-gray-500 mr-1">Leyenda de prioridad:</span>
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-800">Baja</span>
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800">Media</span>
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-amber-100 text-amber-800">Alta</span>
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-800">Urgente</span>
        </div>
      </div>
    </div>

    {{-- ✅ TABLA escritorio + TARJETAS móvil --}}
    <div class="px-6 pb-6">
      <div class="rounded-2xl border border-gray-100 overflow-hidden bg-white shadow-sm">

        <div id="serviceDataWrap">
          {{-- Móvil: tarjetas --}}
          <div id="serviceCardsWrap" class="p-4 space-y-3 lg:hidden"></div>

          {{-- Escritorio: tabla --}}
          <div class="hidden lg:block overflow-x-auto md:overflow-visible">
            <table class="min-w-full w-full table-auto text-sm text-left align-middle">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-gray-500 sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">ID Orden</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Técnico</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Estatus</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Prioridad</th>
                  <th class="px-4 py-3 font-semibold text-center whitespace-nowrap">Asignación</th>
                  <th class="px-4 py-3 font-semibold text-center whitespace-nowrap">Acta</th>
                  <th class="px-4 py-3 font-semibold text-center whitespace-nowrap">Comentarios</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Material no previsto</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Moneda</th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">
                    Total adicional
                    <span class="block text-[10px] text-gray-500 normal-case">(moneda de la orden)</span>
                  </th>
                  <th class="px-4 py-3 font-semibold whitespace-nowrap">Total final</th>
                </tr>
              </thead>
              <tbody id="serviceTableBody" class="divide-y divide-gray-100">
                <!-- Dinámico -->
              </tbody>
            </table>
          </div>
        </div>

        <!-- Estado: Cargando -->
        <div id="loadingState" class="hidden p-6">
          <div class="animate-pulse grid grid-cols-1 gap-3">
            @for ($i=0; $i<4; $i++)
              <div class="h-12 bg-gray-100 rounded"></div>
            @endfor
          </div>
        </div>

        <!-- Estado: vacío -->
        <div id="emptyState" class="hidden p-10 text-center text-gray-500">
          <div class="mx-auto mb-2 h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
            <span class="text-xl">🗂️</span>
          </div>
          <p class="font-medium">No hay resultados para el filtro seleccionado.</p>
        </div>

        <!-- Estado: error -->
        <div id="errorState" class="hidden p-10 text-center text-red-600">
          <div class="mx-auto mb-2 h-12 w-12 rounded-full bg-red-50 flex items-center justify-center">
            <span class="text-xl">⚠️</span>
          </div>
          <p class="font-medium">No fue posible cargar los datos.</p>
          <p class="text-sm text-red-500">Intenta actualizar o cambia el filtro.</p>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Extras -->
<div id="extrasModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="closeExtras()"></div>
  <div class="relative bg-white w-full max-w-2xl mx-auto mt-16 md:mt-20 rounded-2xl shadow-xl">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">
        Materiales no previstos — <span id="extrasOrderLabel" class="text-gray-700"></span>
      </h3>
      <button class="text-gray-500 hover:text-gray-700" onclick="closeExtras()" aria-label="Cerrar">✕</button>
    </div>

    <div class="p-5 space-y-4">
      {{-- Nota de moneda de captura / conversión --}}
      <p id="extrasCurrencyNote"
         class="text-xs text-gray-600 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 flex items-start gap-2">
        <span class="mt-0.5 text-blue-500">ℹ️</span>
        <span>
          Los precios de materiales no previstos se capturan siempre en <strong>MXN</strong>.
          Si la orden está en <strong>USD</strong>, el reporte convierte estos montos a dólares
          usando la tasa de cambio registrada para la orden.
        </span>
      </p>

      <div class="overflow-x-auto rounded border">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="text-left">
              <th class="px-3 py-2">Descripción</th>
              <th class="px-3 py-2 text-right">Cantidad</th>
              <th class="px-3 py-2 text-right">
                Precio unitario
                <span class="block text-[10px] text-gray-500 normal-case">capturado en MXN (opcional)</span>
              </th>
              <th class="px-3 py-2 text-right">Subtotal</th>
              <th class="px-3 py-2">Acciones</th>
            </tr>
          </thead>
          <tbody id="extrasBody" class="divide-y"></tbody>
          <tfoot class="bg-gray-50">
            <tr id="extrasFooterRow">
              <td class="px-3 py-2">
                <input id="exDesc" type="text" placeholder="Descripción"
                       class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </td>
              <td class="px-3 py-2">
                <input id="exCant" type="number" step="0.01" min="0.01" value="1"
                       class="w-28 text-right border-gray-300 rounded-lg">
              </td>
              <td class="px-3 py-2">
                {{-- ✅ precio opcional: vacío = pendiente --}}
                <input id="exPU" type="number" step="0.01" min="0" placeholder="(opcional)"
                       class="w-32 text-right border-gray-300 rounded-lg">
              </td>
              <td class="px-3 py-2 text-right text-gray-500">—</td>
              <td class="px-3 py-2">
                <button id="addExtraBtn" onclick="saveExtra()"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v16m8-8H4" />
                  </svg>
                  <span>Guardar</span>
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="flex items-center justify-end">
        <div class="text-sm text-right space-y-0.5">
          <div>
            <span class="text-gray-600">Total adicional (solo con precio asignado, MXN): </span>
            <span id="extrasTotal" class="font-semibold text-gray-900">$0.00</span>
          </div>

          {{-- ✅ Línea de pendientes --}}
          <div id="extrasPendingLine" class="hidden text-[11px] text-amber-700"></div>

          <div id="extrasTotalUsdWrapper" class="hidden">
            <span class="text-[11px] text-gray-500">
              Equivalente aproximado en USD para esta orden:
            </span>
            <span id="extrasTotalUsd" class="text-[11px] font-semibold text-gray-900 ml-1">$0.00</span>
          </div>
        </div>
      </div>
    </div>

    <div class="px-5 py-4 border-t text-right">
      <button onclick="closeExtras()" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal Avance -->
<div id="progressModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="closeProgress()"></div>
  <div class="relative bg-white w-full max-w-4xl mx-auto mt-10 md:mt-14 rounded-2xl shadow-xl">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">
        Detalles de avance — <span id="progressOrderLabel" class="text-gray-700"></span>
      </h3>
      <div class="flex items-center gap-2">
        <button id="openAddCommentBtn"
                class="px-3 py-1.5 rounded-md border hover:bg-gray-50 text-sm">Agregar comentario</button>
        <button id="openAddImagesBtn"
                class="px-3 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-sm">Agregar imágenes</button>
        <button class="ml-2 text-gray-500 hover:text-gray-700" onclick="closeProgress()" aria-label="Cerrar">✕</button>
      </div>
    </div>

    <div id="progressBody" class="p-5 space-y-5 max-h-[70vh] overflow-y-auto"></div>

    <div class="px-5 py-4 border-t text-right">
      <button onclick="closeProgress()" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cerrar</button>
    </div>
  </div>
</div>

<!-- Modal: Agregar comentario -->
<div id="commentModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="closeCommentModal()"></div>
  <div class="relative bg-white w-full max-w-xl mx-auto mt-20 rounded-2xl shadow-xl">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Nuevo comentario</h3>
      <button class="text-gray-500 hover:text-gray-700" onclick="closeCommentModal()">✕</button>
    </div>
    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Comentario</label>
        <textarea id="commentText" rows="4"
                  class="w-full border-gray-300 rounded-lg"
                  placeholder="Describe el avance..."></textarea>
      </div>
    </div>
    <div class="px-5 py-4 border-t text-right">
      <button onclick="closeCommentModal()" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cancelar</button>
      <button id="saveCommentBtn"
              class="ml-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
        Guardar
      </button>
    </div>
  </div>
</div>

<!-- Modal: Agregar imágenes -->
<div id="imagesModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40" onclick="closeImagesModal()"></div>
  <div class="relative bg-white w-full max-w-2xl mx-auto mt-16 rounded-2xl shadow-xl">
    <div class="px-5 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Agregar imágenes</h3>
      <button class="text-gray-500 hover:text-gray-700" onclick="closeImagesModal()">✕</button>
    </div>
    <div class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-2">Imágenes</label>
        <input id="imgFiles" type="file" accept="image/*" multiple class="block w-full">
        <div id="imgPreview" class="mt-3 grid grid-cols-3 gap-2"></div>
      </div>
    </div>
    <div class="px-5 py-4 border-t text-right">
      <button onclick="closeImagesModal()" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Cancelar</button>
      <button id="saveImagesBtn"
              class="ml-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60">
        Subir
      </button>
    </div>
  </div>
</div>

<!-- Modal visor de imagen individual -->
<div id="imageViewerModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/70" onclick="closeImageViewer()"></div>

  <div class="relative flex items-center justify-center min-h-screen pointer-events-none">
    <div class="relative max-w-5xl w-full px-4 pointer-events-auto">
      <div class="bg-black rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between gap-3 px-4 py-3 text-white bg-black/70">
          <div class="flex-1 min-w-0">
            <div id="viewerCaption" class="text-sm truncate"></div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="px-2 py-1 rounded border border-white/40 text-xs" onclick="zoomOut()">−</button>
            <span id="zoomLabel" class="text-xs w-14 text-center">100%</span>
            <button type="button" class="px-2 py-1 rounded border border-white/40 text-xs" onclick="zoomIn()">+</button>
            <button type="button" class="text-xs px-2 py-1 border border-white/40 rounded" onclick="resetImageZoom()">100%</button>
            <button type="button" class="text-lg px-2" onclick="closeImageViewer()">✕</button>
          </div>
        </div>

        <div class="bg-black flex items-center justify-center p-2">
          <img id="viewerImg"
               src=""
               alt="Imagen de seguimiento"
               class="max-h-[80vh] w-auto object-contain transition-transform duration-150 ease-out">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal visor PDF de acta -->
<div id="pdfModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50" onclick="closePdfModal()"></div>
  <div class="relative w-full max-w-5xl mx-auto mt-10 bg-white rounded-2xl shadow-2xl flex flex-col h-[80vh]">
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <div class="min-w-0">
        <h3 class="text-lg font-semibold text-gray-900">Vista previa de acta (PDF)</h3>
        <p class="hidden sm:block text-xs text-gray-500">Revisa el documento y descrágalo en PDF si es necesario.</p>
      </div>
      <div class="flex items-center gap-2">
        <a id="pdfDownloadLink"
           href="#"
           target="_blank"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-600 text-blue-600 text-xs md:text-sm hover:bg-blue-50">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M4 20h16M12 4v9m0 0 3.5-3.5M12 13 8.5 9.5" />
          </svg>
          <span>Descargar PDF</span>
        </a>

        <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closePdfModal()">✕</button>
      </div>
    </div>

    <div class="flex-1 bg-gray-100">
      <iframe id="pdfViewerFrame"
              src=""
              class="w-full h-full rounded-b-2xl border-0"
              frameborder="0"></iframe>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
/* ==== CSRF + helpers ==== */
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const JSON_HEADERS = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': csrf
};
const GET_HEADERS = {
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest'
};
const FORM_HEADERS = {
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': csrf
};

const baseOrden = "{{ url('/ordenes') }}";
const baseApi   = "{{ url('/api/ordenes') }}";

const tableBody        = document.getElementById("serviceTableBody");
const summary          = document.getElementById("serviceSummary");
const statusFilter     = document.getElementById("statusFilter");
const priorityFilter   = document.getElementById("priorityFilter");
const currencyFilter   = document.getElementById("currencyFilter");
const technicianFilter = document.getElementById("technicianFilter");
const refreshBtn       = document.getElementById("refreshBtn");
const loadingState     = document.getElementById("loadingState");
const emptyState       = document.getElementById("emptyState");
const errorState       = document.getElementById("errorState");

/* ✅ wrappers para data (tabla/cards) */
const dataWrap  = document.getElementById("serviceDataWrap");
const cardsWrap = document.getElementById("serviceCardsWrap");

// Botones del modal de avances
const addCommentBtn = document.getElementById('openAddCommentBtn');
const addImagesBtn  = document.getElementById('openAddImagesBtn');
addCommentBtn && addCommentBtn.addEventListener('click', openCommentModal);
addImagesBtn  && addImagesBtn.addEventListener('click', openImagesModal);

/* ==== Quincena (rango de fechas) ==== */
const prevQuincenaBtn  = document.getElementById('prevQuincenaBtn');
const nextQuincenaBtn  = document.getElementById('nextQuincenaBtn');
const todayQuincenaBtn = document.getElementById('todayQuincenaBtn');
const quincenaLabelEl  = document.getElementById('quincenaLabel');

const monthNames = [
  'enero','febrero','marzo','abril','mayo','junio',
  'julio','agosto','septiembre','octubre','noviembre','diciembre'
];

let currentQuincenaYear  = null;
let currentQuincenaMonth = null; // 1-12
let currentQuincenaHalf  = 1;    // 1 = 1–15, 2 = 16–fin
let currentQuincenaStart = null;
let currentQuincenaEnd   = null;

function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}

function setQuincena(year, month, half) {
  currentQuincenaYear  = year;
  currentQuincenaMonth = month;
  currentQuincenaHalf  = half;

  const startDay = half === 1 ? 1 : 16;
  const endDay   = half === 1 ? 15 : daysInMonth(year, month);

  currentQuincenaStart = new Date(year, month - 1, startDay);
  currentQuincenaEnd   = new Date(year, month - 1, endDay);

  updateQuincenaLabel();
}

function initQuincenaFromToday() {
  const now   = new Date();
  const year  = now.getFullYear();
  const month = now.getMonth() + 1;
  const half  = now.getDate() <= 15 ? 1 : 2;
  setQuincena(year, month, half);
}

function moveQuincena(offsetHalf) {
  let half  = currentQuincenaHalf + offsetHalf;
  let month = currentQuincenaMonth;
  let year  = currentQuincenaYear;

  if (half < 1) { half = 2; month -= 1; }
  else if (half > 2) { half = 1; month += 1; }

  if (month < 1) { month = 12; year -= 1; }
  else if (month > 12) { month = 1; year += 1; }

  setQuincena(year, month, half);
}

function toISODateLocal(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function updateQuincenaLabel() {
  if (!quincenaLabelEl || !currentQuincenaStart || !currentQuincenaEnd) return;
  const d1 = String(currentQuincenaStart.getDate()).padStart(2, '0');
  const d2 = String(currentQuincenaEnd.getDate()).padStart(2, '0');
  const mName = monthNames[currentQuincenaStart.getMonth()];
  const year  = currentQuincenaStart.getFullYear();
  quincenaLabelEl.textContent = `${d1}–${d2} ${mName.charAt(0).toUpperCase() + mName.slice(1)} ${year}`;
}

/* ==== Estados de carga ==== */
function showOnly(stateId) {
  const showData = stateId === 'table';

  if (dataWrap) dataWrap.classList.toggle('hidden', !showData);

  loadingState.classList.toggle('hidden', stateId !== 'loading');
  emptyState.classList.toggle('hidden', stateId !== 'empty');
  errorState.classList.toggle('hidden', stateId !== 'error');
}

/**
 * Formato de moneda.
 */
function formatCurrency(num, currency = "MXN") {
  const n = Number(num || 0);
  const curr = (currency || "MXN").toUpperCase();
  const locale = curr === "USD" ? "en-US" : "es-MX";

  try {
    return n.toLocaleString(locale, { style: "currency", currency: curr });
  } catch (e) {
    return n.toLocaleString(locale) + " " + curr;
  }
}

/**
 * Órdenes bloqueadas para edición (finalizadas).
 */
function isOrderLocked(status) {
  const s = (status || '').toString().toLowerCase();
  return s === 'finalizado';
}

function getStatusBadge(status) {
  const color = {
    "en-proceso": "bg-green-100 text-green-800",
    "finalizado": "bg-blue-100 text-blue-800",
    "cancelado": "bg-red-100 text-red-800",
    "finalizado-sin-firmar": "bg-yellow-100 text-yellow-800"
  }[status] || "bg-gray-100 text-gray-800";
  return `<span class="px-2 py-1 rounded-full text-xs font-medium ${color}">${escapeHtml(status)}</span>`;
}

function getPriorityBadge(priorityRaw) {
  const p = (priorityRaw || '').toString().trim();
  const color = {
    'Baja':     'bg-gray-100 text-gray-800',
    'Media':    'bg-blue-100 text-blue-800',
    'Alta':     'bg-amber-100 text-amber-800',
    'Urgente':  'bg-red-100 text-red-800'
  }[p] || 'bg-gray-100 text-gray-800';
  const label = p || '—';
  return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${color}">${escapeHtml(label)}</span>`;
}

async function fetchSeguimiento() {
  const estado = statusFilter.value || 'all';

  const params = new URLSearchParams();
  params.set('estado', estado);

  if (priorityFilter && priorityFilter.value && priorityFilter.value !== 'all') {
    params.set('prioridad', priorityFilter.value);
  }

  if (currencyFilter && currencyFilter.value && currencyFilter.value !== 'all') {
    params.set('moneda', currencyFilter.value);
  }

  if (technicianFilter && technicianFilter.value.trim() !== '') {
    params.set('tecnico', technicianFilter.value.trim());
  }

  if (currentQuincenaStart && currentQuincenaEnd) {
    params.set('desde', toISODateLocal(currentQuincenaStart));
    params.set('hasta', toISODateLocal(currentQuincenaEnd));
  }

  const url = `{{ route('api.seguimiento-servicios') }}?${params.toString()}`;
  const res = await fetch(url, { headers: GET_HEADERS, credentials: 'same-origin' });
  if (!res.ok) throw new Error('Error al cargar seguimiento');
  return res.json();
}

/**
 * BOTÓN ASIGNAR TÉCNICO (TABLA)
 */
function renderAsignarBtn(item) {
  const tipo   = (item.tipo || item.tipo_orden || '').toLowerCase();
  const locked = isOrderLocked(item.status);

  if (tipo === 'compra') {
    return `<button type="button"
              class="px-3 py-1.5 rounded-md bg-gray-200 text-gray-500 text-xs cursor-not-allowed whitespace-nowrap"
              disabled aria-disabled="true"
              title="Las órdenes de compra no requieren técnico">
              No requiere
            </button>`;
  }

  if (locked) {
    return `<button type="button"
              class="px-3 py-1.5 rounded-md bg-gray-200 text-gray-500 text-xs cursor-not-allowed whitespace-nowrap"
              disabled aria-disabled="true"
              title="La orden está cerrada, no se pueden asignar técnicos.">
              Servicio cerrado
            </button>`;
  }

  const href = `${baseOrden}/${item.id}/asignar`;
  return `<a href="${href}"
            class="px-3 py-1.5 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-xs whitespace-nowrap">
            Asignar técnico
          </a>`;
}

function renderActaBtn(item) {
  const actaEstado = (item.actaEstado || item.acta_estado || '').toLowerCase();

  if (actaEstado === 'firmada') {
    const pdf = `${baseOrden}/${item.id}/acta/pdf`;
    return `
      <button type="button"
              onclick='openPdfModal(${JSON.stringify(pdf)})'
              class="px-3 py-1.5 rounded-md bg-gray-700 hover:bg-gray-800 text-white text-xs whitespace-nowrap">
        Acta (PDF)
      </button>
    `;
  }

  const vista = `${baseOrden}/${item.id}/acta`;
  if (actaEstado === 'borrador') {
    return `<a href="${vista}"
              class="px-3 py-1.5 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-xs whitespace-nowrap">
              Acta (borrador)
            </a>`;
  }
  return `<a href="${vista}"
            class="px-3 py-1.5 rounded-md bg-gray-700 hover:bg-gray-800 text-white text-xs whitespace-nowrap">
            Acta conformidad
          </a>`;
}

function renderEyeBtn(item) {
  return `
    <button type="button" title="Ver detalles de avance"
      class="inline-flex items-center justify-center w-9 h-9 rounded-lg border hover:bg-gray-50"
      onclick="openProgress(${item.id}, '${item.orderId}', '${item.status}')">
      <svg class="h-5 w-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M2.25 12s3.75-7.5 9.75-7.5S21.75 12 21.75 12s-3.75 7.5-9.75 7.5S2.25 12 2.25 12z" />
        <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
      </svg>
    </button>
  `;
}

/* ✅ botones para TARJETAS */
function renderAsignarBtnCard(item) {
  const tipo   = (item.tipo || item.tipo_orden || '').toLowerCase();
  const locked = isOrderLocked(item.status);

  if (tipo === 'compra') {
    return `<button type="button"
              class="w-full px-3 py-2 rounded-lg bg-gray-200 text-gray-500 text-sm cursor-not-allowed"
              disabled>
              No requiere
            </button>`;
  }

  if (locked) {
    return `<button type="button"
              class="w-full px-3 py-2 rounded-lg bg-gray-200 text-gray-500 text-sm cursor-not-allowed"
              disabled>
              Servicio cerrado
            </button>`;
  }

  const href = `${baseOrden}/${item.id}/asignar`;
  return `<a href="${href}"
            class="w-full px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm text-center font-medium">
            Asignar técnico
          </a>`;
}

function renderActaBtnCard(item) {
  const actaEstado = (item.actaEstado || item.acta_estado || '').toLowerCase();

  if (actaEstado === 'firmada') {
    const pdf = `${baseOrden}/${item.id}/acta/pdf`;
    return `<button type="button"
              class="w-full px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium"
              onclick='openPdfModal(${JSON.stringify(pdf)})'>
              Ver acta (PDF)
            </button>`;
  }

  const vista = `${baseOrden}/${item.id}/acta`;

  if (actaEstado === 'borrador') {
    return `<a href="${vista}"
              class="w-full px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm text-center font-medium">
              Acta (borrador)
            </a>`;
  }

  return `<a href="${vista}"
            class="w-full px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-900 text-white text-sm text-center font-medium">
            Acta conformidad
          </a>`;
}

function renderCards(rows) {
  if (!cardsWrap) return;
  cardsWrap.innerHTML = '';

  rows.forEach(item => {
    const priority = item.priority ?? item.prioridad ?? '';
    const currency = (item.currency || 'MXN').toUpperCase();

    const matRaw = item.unforeseeenMaterial || item.unforeseenMaterial || '—';
    const mat = escapeHtml(matRaw);

    const locked = isOrderLocked(item.status);
    const extrasBtnLabel = locked ? 'Ver materiales' : 'Editar materiales';

    const additionalTotal    = Number(item.additionalTotal || 0);
    const additionalTotalMxn = Number(item.additionalTotalMxn || 0);

    const pendingCount = Number(item.extrasPendingCount || 0);
    const pendingBadge = pendingCount > 0
      ? `<span class="ml-2 inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">Pend: ${pendingCount}</span>`
      : '';

    let extraLine = '';
    if (currency === 'USD' && additionalTotalMxn > 0) {
      extraLine = `<div class="text-[11px] text-gray-500 mt-0.5">
        Capturado en MXN: ${formatCurrency(additionalTotalMxn, 'MXN')}
      </div>`;
    }

    const extrasOnclick   = `openExtras(${item.id}, ${JSON.stringify(item.orderId)}, ${JSON.stringify(item.status)}, ${JSON.stringify(currency)}, ${Number(item.exchangeRate || 1)})`;
    const progressOnclick = `openProgress(${item.id}, ${JSON.stringify(item.orderId)}, ${JSON.stringify(item.status)})`;

    cardsWrap.innerHTML += `
      <div class="border border-gray-200 rounded-2xl p-4 shadow-sm bg-white">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-xs text-gray-500">ID Orden</div>
            <div class="text-base font-semibold text-blue-700 truncate">${escapeHtml(item.orderId || '—')}</div>

            <div class="mt-2 text-sm text-gray-800">
              <span class="text-xs text-gray-500">Técnico:</span>
              <span class="font-medium">${escapeHtml(item.technician || '—')}</span>
            </div>
          </div>

          <div class="flex flex-col items-end gap-2 shrink-0">
            ${getStatusBadge(item.status)}
            ${getPriorityBadge(priority)}
          </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2">
          <div class="rounded-xl border bg-gray-50 p-3">
            <div class="text-[11px] text-gray-500">Moneda</div>
            <div class="text-sm font-semibold text-gray-900">${currency}</div>
          </div>
          <div class="rounded-xl border bg-gray-50 p-3">
            <div class="text-[11px] text-gray-500">Total final</div>
            <div class="text-sm font-semibold text-gray-900 tabular-nums">
              ${formatCurrency(item.finalTotal, currency)}
            </div>
          </div>
        </div>

        <div class="mt-2 rounded-xl border bg-gray-50 p-3">
          <div class="flex items-center justify-between gap-2">
            <div class="text-[11px] text-gray-500">Total adicional</div>
            <div class="text-sm font-semibold text-gray-900 tabular-nums">
              ${formatCurrency(additionalTotal, currency)}
            </div>
          </div>
          ${extraLine}
        </div>

        <div class="mt-3">
          <div class="text-[11px] text-gray-500">Material no previsto</div>
          <div class="text-sm text-gray-800 truncate" title="${escapeHtmlAttr(matRaw)}">${mat}${pendingBadge}</div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
          ${renderAsignarBtnCard(item)}
          ${renderActaBtnCard(item)}
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
          <button type="button"
            class="w-full px-3 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50"
            onclick='${progressOnclick}'>
            Ver avances
          </button>

          <button type="button"
            class="w-full px-3 py-2 rounded-lg ${locked ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700 text-white'} text-sm font-medium"
            ${locked ? 'disabled' : ''}
            onclick='${extrasOnclick}'>
            ${extrasBtnLabel}
          </button>
        </div>
      </div>
    `;
  });
}

function renderTable(rows) {
  tableBody.innerHTML = "";

  // tarjetas móvil
  renderCards(rows);

  if (!rows.length) {
    showOnly('empty');
    return;
  }

  rows.forEach(item => {
    const priority   = item.priority ?? item.prioridad ?? '';
    const matRaw     = item.unforeseeenMaterial || item.unforeseenMaterial || '-';
    const mat        = escapeHtml(matRaw);

    const pendingCount = Number(item.extrasPendingCount || 0);
    const pendingBadge = pendingCount > 0
      ? `<span class="ml-2 inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">Pend: ${pendingCount}</span>`
      : '';

    const locked     = isOrderLocked(item.status);
    const currency   = (item.currency || 'MXN').toUpperCase();

    const additionalTotal     = Number(item.additionalTotal || 0);
    const additionalTotalMxn  = Number(item.additionalTotalMxn || 0);

    const extrasBtnLabel = locked ? 'Ver' : 'Editar';
    const extrasBtnTitle = locked
      ? 'Servicio finalizado: solo lectura de materiales no previstos.'
      : 'Ver / editar materiales no previstos';

    const extrasBtnClasses = locked
      ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border border-gray-200 text-gray-600 bg-gray-50 hover:bg-gray-100'
      : 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border border-blue-100 text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-200';

    let totalExtraHtml = '';
    if (currency === 'USD') {
      totalExtraHtml = `
        <div class="flex flex-col">
          <span>${formatCurrency(additionalTotal, 'USD')}</span>
          ${additionalTotalMxn > 0
            ? `<span class="text-[11px] text-gray-500">
                 Capturado en MXN: ${formatCurrency(additionalTotalMxn, 'MXN')}
               </span>`
            : ''
          }
        </div>
      `;
    } else {
      totalExtraHtml = formatCurrency(additionalTotal, currency);
    }

    tableBody.innerHTML += `
      <tr class="hover:bg-slate-50/80 transition-colors">
        <td class="px-4 py-3 font-semibold text-blue-700 whitespace-nowrap">${escapeHtml(item.orderId)}</td>
        <td class="px-4 py-3 font-medium text-gray-800 max-w-[180px] truncate" title="${escapeHtmlAttr(item.technician || '—')}">
          ${escapeHtml(item.technician || '—')}
        </td>
        <td class="px-4 py-3 whitespace-nowrap">${getStatusBadge(item.status)}</td>
        <td class="px-4 py-3 whitespace-nowrap">${getPriorityBadge(priority)}</td>
        <td class="px-4 py-3 text-center whitespace-nowrap">${renderAsignarBtn(item)}</td>
        <td class="px-4 py-3 text-center whitespace-nowrap">${renderActaBtn(item)}</td>
        <td class="px-4 py-3 text-center whitespace-nowrap">${renderEyeBtn(item)}</td>
        <td class="px-4 py-3 text-sm">
          <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
              <span class="truncate max-w-[220px]" title="${escapeHtmlAttr(matRaw)}">${mat}</span>
              ${pendingBadge}
              <button
                  class="${extrasBtnClasses}"
                  title="${extrasBtnTitle}"
                  onclick="openExtras(${item.id}, '${item.orderId}', '${item.status}', '${currency}', ${item.exchangeRate || 1})">
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M15.232 5.232a2.5 2.5 0 1 1 3.536 3.536L8.5 19H5v-3.5z" />
                  </svg>
                  <span>${extrasBtnLabel}</span>
              </button>
            </div>
          </div>
        </td>
        <td class="px-4 py-3 font-medium text-gray-700 whitespace-nowrap">${currency}</td>
        <td class="px-4 py-3 font-medium tabular-nums whitespace-nowrap">${totalExtraHtml}</td>
        <td class="px-4 py-3 font-semibold text-gray-900 tabular-nums whitespace-nowrap">${formatCurrency(item.finalTotal, currency)}</td>
      </tr>
    `;
  });

  showOnly('table');
}

function summaryCard(label, value, emphasize = false) {
  const emphasizeClasses = emphasize
    ? 'bg-blue-50 border-blue-100'
    : 'bg-slate-50 border-slate-100';
  return `
    <div class="rounded-2xl border ${emphasizeClasses} px-4 py-3 shadow-sm">
      <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">${escapeHtml(label)}</div>
      <div class="mt-1 text-xl font-semibold text-slate-900 tabular-nums">${value}</div>
    </div>
  `;
}

function renderSummary(s) {
  const totalFact = s.totalFacturado ?? 0;
  const resumenMoneda = s.monedaResumen || 'MXN';

  summary.innerHTML = `
    ${summaryCard('Total de servicios', s.total ?? 0, true)}
    ${summaryCard('En proceso', s.enProceso ?? 0, false)}
    ${summaryCard('Finalizados', s.finalizados ?? 0, false)}
    ${summaryCard('Total facturado', formatCurrency(totalFact, resumenMoneda), true)}
  `;
}

async function updateView() {
  try {
    showOnly('loading');
    const data = await fetchSeguimiento();
    renderTable(data.rows || []);
    renderSummary(data.summary || { total:0, enProceso:0, finalizados:0, totalFacturado:0, monedaResumen: 'MXN' });
  } catch (e) {
    console.error(e);
    showOnly('error');
  }
}

function debounce(fn, ms=300) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(()=>fn(...args), ms); };
}

statusFilter    && statusFilter.addEventListener("change", debounce(updateView, 50));
priorityFilter  && priorityFilter.addEventListener("change", debounce(updateView, 50));
currencyFilter  && currencyFilter.addEventListener("change", debounce(updateView, 50));
technicianFilter&& technicianFilter.addEventListener("input", debounce(updateView, 300));

prevQuincenaBtn?.addEventListener('click', () => { moveQuincena(-1); updateView(); });
nextQuincenaBtn?.addEventListener('click', () => { moveQuincena(1);  updateView(); });
todayQuincenaBtn?.addEventListener('click', () => { initQuincenaFromToday(); updateView(); });

document.addEventListener('DOMContentLoaded', () => {
  initQuincenaFromToday();
  updateView();
});

refreshBtn?.addEventListener('click', updateView);

/* ===== Modal de Extras ===== */
let currentOrderId = null;
let currentOrderStatus = null;
let currentOrderCurrency = 'MXN';
let currentOrderExchangeRate = 1.0;

function closeExtras() {
  document.getElementById('extrasModal').classList.add('hidden');
  currentOrderId = null;
  currentOrderStatus = null;
  currentOrderCurrency = 'MXN';
  currentOrderExchangeRate = 1.0;

  // limpiar inputs
  const exDesc = document.getElementById('exDesc');
  const exCant = document.getElementById('exCant');
  const exPU   = document.getElementById('exPU');
  if (exDesc) { exDesc.value = ''; delete exDesc.dataset.editId; }
  if (exCant) exCant.value = '1';
  if (exPU)   exPU.value = '';

  const addBtn = document.getElementById('addExtraBtn');
  if (addBtn) addBtn.disabled = false;
}

function updateExtrasCurrencyNote() {
  const noteEl = document.getElementById('extrasCurrencyNote');
  if (!noteEl) return;

  if (currentOrderCurrency === 'USD') {
    noteEl.innerHTML = `
      <span class="mt-0.5 text-blue-500">ℹ️</span>
      <span>
        Los precios de materiales no previstos se capturan siempre en <strong>MXN</strong>.<br>
        Esta orden está en <strong>USD</strong>: en el reporte el total adicional se convierte a dólares
        usando la tasa de cambio registrada.
      </span>
    `;
  } else {
    noteEl.innerHTML = `
      <span class="mt-0.5 text-blue-500">ℹ️</span>
      <span>
        Los precios de materiales no previstos se capturan siempre en <strong>MXN</strong>.
      </span>
    `;
  }
}

async function openExtras(orderId, orderLabel, status, currency = 'MXN', exchangeRate = 1) {
  currentOrderId = orderId;
  currentOrderStatus = status || null;
  currentOrderCurrency = (currency || 'MXN').toUpperCase();
  currentOrderExchangeRate = Number(exchangeRate) > 0 ? Number(exchangeRate) : 1.0;

  document.getElementById('extrasOrderLabel').textContent = orderLabel || ('OS-' + orderId);

  updateExtrasCurrencyNote();

  const footerRow = document.getElementById('extrasFooterRow');
  const locked = isOrderLocked(currentOrderStatus);
  if (footerRow) footerRow.classList.toggle('hidden', locked);

  await loadExtras();
  document.getElementById('extrasModal').classList.remove('hidden');
}

/* ✅ UPDATED: soporta precio_unitario null => Pendiente */
async function loadExtras() {
  const url = `${baseApi}/${currentOrderId}/extras`;
  const res = await fetch(url, { headers: GET_HEADERS, credentials: 'same-origin' });
  const json = res.ok ? await res.json() : { extras:[], totalAdicional:0, pendientes:0, pendientesCantidad:0, moneda:'MXN' };

  const body = document.getElementById('extrasBody');
  body.innerHTML = '';

  const locked = isOrderLocked(currentOrderStatus);

  (json.extras || []).forEach(e => {
    const pendiente = (e.pendiente === true) || (e.precio_unitario === null || e.precio_unitario === undefined);

    const puHtml = pendiente
      ? `<span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">Pendiente</span>`
      : formatCurrency(e.precio_unitario, 'MXN');

const subHtml = (pendiente || e.subtotal === null || e.subtotal === undefined)
  ? `<span class="text-gray-400">—</span>`
  : formatCurrency(e.subtotal, 'MXN');


    const actionsHtml = locked
      ? '<span class="text-xs text-gray-400">Solo lectura</span>'
      : `
        <div class="flex gap-2">
          <button class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-md border border-blue-100 bg-blue-50 text-blue-700 hover:bg-blue-100"
                  onclick='editExtra(${JSON.stringify(e.id)}, ${JSON.stringify(e.descripcion)}, ${JSON.stringify(e.cantidad)}, ${JSON.stringify(e.precio_unitario)})'>
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M15.232 5.232a2.5 2.5 0 1 1 3.536 3.536L8.5 19H5v-3.5z" />
            </svg>
            <span>Editar</span>
          </button>
          <button class="px-2 py-1 text-xs border border-red-300 text-red-600 rounded hover:bg-red-50" onclick="deleteExtra(${e.id})">
            Eliminar
          </button>
        </div>
      `;

    body.innerHTML += `
      <tr>
        <td class="px-3 py-2">${escapeHtml(e.descripcion || '')}</td>
        <td class="px-3 py-2 text-right tabular-nums">${Number(e.cantidad || 0).toFixed(2)}</td>
        <td class="px-3 py-2 text-right tabular-nums">${puHtml}</td>
        <td class="px-3 py-2 text-right tabular-nums">${subHtml}</td>
        <td class="px-3 py-2">${actionsHtml}</td>
      </tr>
    `;
  });

  // Total MXN (solo con precio asignado)
  const totalMxn = Number(json.totalAdicional || 0);
  document.getElementById('extrasTotal').textContent = formatCurrency(totalMxn, 'MXN');

  // Pendientes
  const pendientes = Number(json.pendientes || 0);
  const pendQty = Number(json.pendientesCantidad || 0);
  const line = document.getElementById('extrasPendingLine');
  if (line) {
    if (pendientes > 0) {
      line.classList.remove('hidden');
      line.textContent = `Pendientes de precio: ${pendientes} (cantidad total: ${pendQty.toFixed(2)})`;
    } else {
      line.classList.add('hidden');
      line.textContent = '';
    }
  }

  // Equivalente USD si aplica (convertimos totalMxn / tasa)
  const usdWrapper = document.getElementById('extrasTotalUsdWrapper');
  const usdSpan    = document.getElementById('extrasTotalUsd');

  if (usdWrapper && usdSpan) {
    if (currentOrderCurrency === 'USD' && currentOrderExchangeRate > 0) {
      const equivUsd = totalMxn / currentOrderExchangeRate;
      usdSpan.textContent = formatCurrency(equivUsd, 'USD');
      usdWrapper.classList.remove('hidden');
    } else {
      usdWrapper.classList.add('hidden');
      usdSpan.textContent = '';
    }
  }
}

/* ✅ UPDATED: pu null => input vacío */
function editExtra(id, desc, cant, pu) {
  document.getElementById('exDesc').value = desc || '';
  document.getElementById('exCant').value = cant ?? 1;
  document.getElementById('exPU').value   = (pu === null || pu === undefined) ? '' : pu;
  document.getElementById('exDesc').dataset.editId = id;
}

/* ✅ UPDATED: precio opcional (null) */
async function saveExtra() {
  if (isOrderLocked(currentOrderStatus)) {
    alert('Los materiales no previstos no se pueden modificar en servicios finalizados.');
    return;
  }

  const desc = document.getElementById('exDesc').value.trim();
  const cant = parseFloat(document.getElementById('exCant').value || '1');

  const puRaw = (document.getElementById('exPU').value || '').trim();
  const pu = puRaw === '' ? null : parseFloat(puRaw);

  const addBtn = document.getElementById('addExtraBtn');

  if (!desc) { alert('La descripción es requerida'); return; }
  if (isNaN(cant) || cant < 0.01) { alert('Cantidad inválida'); return; }

  if (pu !== null && (isNaN(pu) || pu < 0)) {
    alert('Precio unitario inválido');
    return;
  }

  const editId = document.getElementById('exDesc').dataset.editId;
  const isEdit = !!editId;

  const url = isEdit
    ? `${baseApi}/${currentOrderId}/extras/${editId}`
    : `${baseApi}/${currentOrderId}/extras`;

  const method = isEdit ? 'PUT' : 'POST';

  addBtn.disabled = true;

  const res = await fetch(url, {
    method,
    headers: JSON_HEADERS,
    credentials: 'same-origin',
    body: JSON.stringify({ descripcion: desc, cantidad: cant, precio_unitario: pu })
  });

  addBtn.disabled = false;

  if (!res.ok) {
    if (res.status === 419) {
      alert('Tu sesión expiró. Recarga la página.');
    } else {
      console.error('Error guardando material extra', res.status, await res.text().catch(()=> ''));
      alert('No se pudo guardar el material.');
    }
    return;
  }

  document.getElementById('exDesc').value = '';
  document.getElementById('exCant').value = '1';
  document.getElementById('exPU').value   = '';
  delete document.getElementById('exDesc').dataset.editId;

  await loadExtras();
  await updateView();
}

async function deleteExtra(id) {
  if (isOrderLocked(currentOrderStatus)) {
    alert('Los materiales no previstos no se pueden modificar en servicios finalizados.');
    return;
  }

  if (!confirm('¿Eliminar este material?')) return;
  const url = `${baseApi}/${currentOrderId}/extras/${id}`;
  const res = await fetch(url, {
    method: 'DELETE',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf
    },
    credentials: 'same-origin'
  });

  if (!res.ok) {
    if (res.status === 419) {
      alert('Tu sesión expiró. Recarga la página.');
    } else {
      console.error('Error eliminando material extra', res.status, await res.text().catch(()=> ''));
      alert('No se pudo eliminar.');
    }
    return;
  }

  await loadExtras();
  await updateView();
}

/* ===== Avances (seguimientos + imágenes) ===== */
let progressOrderId = null;
let progressOrderStatus = null;
let progressSeguimientos = [];
let progressImagenes = [];

function closeProgress() {
  document.getElementById('progressModal').classList.add('hidden');
  progressOrderId = null;
  progressOrderStatus = null;
  progressSeguimientos = [];
  progressImagenes = [];
}

async function openProgress(orderId, orderLabel, status) {
  progressOrderId = orderId;
  progressOrderStatus = status || null;
  document.getElementById('progressOrderLabel').textContent = orderLabel || ('OS-' + orderId);

  const locked = isOrderLocked(progressOrderStatus);
  const addCommentBtnEl = document.getElementById('openAddCommentBtn');
  const addImagesBtnEl  = document.getElementById('openAddImagesBtn');

  if (addCommentBtnEl) {
    addCommentBtnEl.disabled = locked;
    addCommentBtnEl.classList.toggle('opacity-60', locked);
    addCommentBtnEl.classList.toggle('cursor-not-allowed', locked);
    addCommentBtnEl.title = locked
      ? 'No se pueden agregar comentarios a servicios finalizados.'
      : 'Agregar comentario de avance';
  }

  if (addImagesBtnEl) {
    addImagesBtnEl.disabled = locked;
    addImagesBtnEl.classList.toggle('opacity-60', locked);
    addImagesBtnEl.classList.toggle('cursor-not-allowed', locked);
    addImagesBtnEl.title = locked
      ? 'No se pueden agregar imágenes a servicios finalizados.'
      : 'Agregar imágenes de avance';
  }

  const body = document.getElementById('progressBody');
  body.innerHTML = '<div class="text-gray-500">Cargando avances…</div>';
  document.getElementById('progressModal').classList.remove('hidden');

  try {
    await loadProgress();
  } catch (e) {
    console.error(e);
    body.innerHTML = '<div class="text-red-600">No se pudieron cargar los avances.</div>';
  }
}

async function loadProgress() {
  const url = `${baseApi}/${progressOrderId}/seguimientos`;
  const res = await fetch(url, { headers: GET_HEADERS, credentials: 'same-origin' });
  if (!res.ok) throw new Error('HTTP ' + res.status);

  const json = await res.json();

  progressSeguimientos = Array.isArray(json.seguimientos) ? json.seguimientos : [];
  progressImagenes     = Array.isArray(json.imagenes)     ? json.imagenes     : [];

  if (!progressImagenes.length && progressSeguimientos.length) {
    const seen = new Set();
    progressSeguimientos.forEach(s => {
      if (!Array.isArray(s.imagenes)) return;
      s.imagenes.forEach(img => {
        if (!img) return;
        const key = img.id || img.id_imagen || img.id_seguimiento_imagen || img.ruta || img.url;
        if (key && seen.has(key)) return;
        if (key) seen.add(key);
        progressImagenes.push(img);
      });
    });
  }

  renderProgress();
}

function renderProgress() {
  const cont = document.getElementById('progressBody');

  const hasComments = progressSeguimientos && progressSeguimientos.length;
  const hasImages   = progressImagenes && progressImagenes.length;

  if (!hasComments && !hasImages) {
    cont.innerHTML = `<div class="text-center text-gray-500">Sin avances aún. Agrega comentarios o imágenes desde los botones superiores.</div>`;
    return;
  }

  let comentariosHtml = '';
  if (hasComments) {
    comentariosHtml = progressSeguimientos.map(s => {
      const fecha = s.fecha_fmt || s.fecha || '';
      return `
        <div class="rounded-xl border p-4 bg-white/60">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs text-gray-500">${escapeHtml(fecha)}</div>
              <div class="mt-1 text-gray-900 text-sm whitespace-pre-line">${escapeHtml(s.descripcion || '')}</div>
            </div>
            <div class="flex items-center gap-1">
              ${getStatusBadge(progressOrderStatus)}
            </div>
          </div>
        </div>
      `;
    }).join('');
  } else {
    comentariosHtml = `<div class="text-sm text-gray-500">No hay comentarios registrados.</div>`;
  }

  let imagenesHtml = '';
  if (hasImages) {
    imagenesHtml = progressImagenes.map(img => {
      const rawTitulo = img.titulo || img.descripcion || 'Imagen de seguimiento';
      const urlAttr = escapeHtmlAttr(img.url || img.ruta || img.path || '');
      const fechaImg = img.fecha_fmt || img.fecha || img.created_at || '';

      const captionParts = [];
      if (fechaImg) captionParts.push(fechaImg);
      if (img.nota || img.descripcion) captionParts.push(img.nota || img.descripcion);
      const caption = captionParts.join(' — ');

      return `
        <div class="rounded-xl border bg-white/60 overflow-hidden">
          <button type="button"
                  class="block w-full group"
                  onclick='openImageViewer("${urlAttr}", "${escapeHtmlAttr(caption || rawTitulo)}")'>
            <img src="${urlAttr}"
                 alt="${escapeHtmlAttr(rawTitulo)}"
                 class="h-48 w-full object-contain bg-black group-hover:opacity-95 transition-opacity">
          </button>
          ${caption
            ? `<div class="px-3 py-2 border-t bg-gray-50 text-[11px] text-gray-600">${escapeHtml(caption)}</div>`
            : ''
          }
        </div>
      `;
    }).join('');
  } else {
    imagenesHtml = `<div class="text-sm text-gray-500">No hay imágenes registradas.</div>`;
  }

  cont.innerHTML = `
    <div class="space-y-6">
      <div>
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Comentarios</h4>
        <div class="space-y-3">
          ${comentariosHtml}
        </div>
      </div>

      <div class="border-t pt-4">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Imágenes</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          ${imagenesHtml}
        </div>
      </div>
    </div>
  `;
}

function escapeHtml(t) {
  return (t || '').replace(/[&<>"']/g, m =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])
  );
}
function escapeHtmlAttr(t) {
  return escapeHtml(t || '').replace(/"/g, '&quot;');
}

/* ===== Modal comentario ===== */
function openCommentModal() {
  if (isOrderLocked(progressOrderStatus)) {
    alert('No se pueden agregar comentarios a servicios finalizados.');
    return;
  }

  const txt = document.getElementById('commentText');
  if (txt) txt.value = '';

  document.getElementById('commentModal').classList.remove('hidden');
  document.getElementById('saveCommentBtn').onclick = saveComment;
}

function closeCommentModal() {
  document.getElementById('commentModal').classList.add('hidden');
}

async function saveComment() {
  if (isOrderLocked(progressOrderStatus)) {
    alert('No se pueden agregar comentarios a servicios finalizados.');
    return;
  }

  const textarea = document.getElementById('commentText');
  const texto = (textarea?.value || '').trim();

  if (!texto) {
    alert('El comentario es obligatorio.');
    return;
  }

  const url = `${baseApi}/${progressOrderId}/seguimientos`;
  const res = await fetch(url, {
    method: 'POST',
    headers: JSON_HEADERS,
    credentials: 'same-origin',
    body: JSON.stringify({ descripcion: texto })
  });

  if (!res.ok) {
    if (res.status === 419) alert('Sesión expirada. Recarga la página.');
    else alert('No se pudo guardar el comentario.');
    return;
  }

  closeCommentModal();
  await loadProgress();
  await updateView();
}

/* ===== Modal imágenes ===== */
function openImagesModal() {
  if (isOrderLocked(progressOrderStatus)) {
    alert('No se pueden agregar imágenes a servicios finalizados.');
    return;
  }

  const input = document.getElementById('imgFiles');
  const preview = document.getElementById('imgPreview');

  if (input) input.value = '';
  if (preview) preview.innerHTML = '';

  document.getElementById('imagesModal').classList.remove('hidden');

  if (input) input.onchange = previewImages;
  const btn = document.getElementById('saveImagesBtn');
  if (btn) {
    btn.disabled = false;
    btn.onclick = saveImages;
  }
}

function closeImagesModal() {
  document.getElementById('imagesModal').classList.add('hidden');
}

function previewImages(e) {
  const files = Array.from(e.target.files || []);
  const cont = document.getElementById('imgPreview');
  cont.innerHTML = '';
  files.forEach(f => {
    const url = URL.createObjectURL(f);
    const div = document.createElement('div');
    div.className = 'relative';
    div.innerHTML = `<img src="${url}" class="h-24 w-full object-cover rounded-lg border" alt="preview">`;
    cont.appendChild(div);
  });
}

async function saveImages() {
  if (isOrderLocked(progressOrderStatus)) {
    alert('No se pueden agregar imágenes a servicios finalizados.');
    return;
  }

  const input = document.getElementById('imgFiles');
  const files = Array.from(input.files || []);

  if (!files.length) {
    alert('Selecciona al menos una imagen.');
    return;
  }

  const url = `${baseApi}/${progressOrderId}/imagenes`;
  const fd = new FormData();
  files.forEach(f => fd.append('imagenes[]', f));

  const btn = document.getElementById('saveImagesBtn');
  if (btn) btn.disabled = true;

  const res = await fetch(url, {
    method: 'POST',
    headers: FORM_HEADERS,
    credentials: 'same-origin',
    body: fd
  });

  if (btn) btn.disabled = false;

  if (!res.ok) {
    if (res.status === 419) alert('Sesión expirada. Recarga la página.');
    else alert('No se pudieron subir las imágenes.');
    return;
  }

  closeImagesModal();
  await loadProgress();
}

/* ===== Visor de imágenes con zoom ===== */
const imageViewerModal   = document.getElementById('imageViewerModal');
const imageViewerImg     = document.getElementById('viewerImg');
const imageViewerCaption = document.getElementById('viewerCaption');
const zoomLabel          = document.getElementById('zoomLabel');

let imageZoom = 1;
const minZoom = 0.5;
const maxZoom = 3;
const stepZoom = 0.25;

function applyZoom() {
  if (!imageViewerImg) return;
  imageZoom = Math.min(maxZoom, Math.max(minZoom, imageZoom));
  imageViewerImg.style.transform = `scale(${imageZoom})`;
  if (zoomLabel) zoomLabel.textContent = Math.round(imageZoom * 100) + '%';
}

function openImageViewer(url, caption = '') {
  if (!imageViewerModal || !imageViewerImg) return;
  imageViewerImg.src = url;
  imageViewerImg.alt = caption || 'Imagen de seguimiento';
  imageViewerCaption.textContent = caption || '';
  imageZoom = 1;
  applyZoom();
  imageViewerModal.classList.remove('hidden');
}

function closeImageViewer() {
  if (!imageViewerModal || !imageViewerImg) return;
  imageViewerModal.classList.add('hidden');
  imageViewerImg.src = '';
  imageViewerCaption.textContent = '';
}

function zoomIn() { imageZoom += stepZoom; applyZoom(); }
function zoomOut(){ imageZoom -= stepZoom; applyZoom(); }
function resetImageZoom(){ imageZoom = 1; applyZoom(); }

if (imageViewerImg) {
  imageViewerImg.addEventListener('wheel', (e) => {
    e.preventDefault();
    imageZoom += (e.deltaY < 0 ? stepZoom : -stepZoom);
    applyZoom();
  });
}

/* ===== Visor PDF ===== */
const pdfModal         = document.getElementById('pdfModal');
const pdfFrame         = document.getElementById('pdfViewerFrame');
const pdfDownloadLink  = document.getElementById('pdfDownloadLink');

function openPdfModal(url) {
  if (!pdfModal || !pdfFrame) return;
  pdfFrame.src = url;

  if (pdfDownloadLink) {
    pdfDownloadLink.href = url;
    pdfDownloadLink.setAttribute('download', '');
  }

  pdfModal.classList.remove('hidden');
}

function closePdfModal() {
  if (!pdfModal || !pdfFrame) return;
  pdfModal.classList.add('hidden');
  pdfFrame.src = '';

  if (pdfDownloadLink) {
    pdfDownloadLink.href = '#';
    pdfDownloadLink.removeAttribute('download');
  }
}

/* Cerrar modales con ESC */
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    ['extrasModal','progressModal','commentModal','imagesModal','imageViewerModal','pdfModal'].forEach(id => {
      const m = document.getElementById(id);
      if (m && !m.classList.contains('hidden')) m.classList.add('hidden');
    });
  }
});
</script>
@endpush
