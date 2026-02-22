@extends('layouts.sidebar-navigation')

@section('title', 'Órdenes de Servicio')

@section('content')
<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-4">
        <x-boton-volver />
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 flex-1 text-center md:text-left">
            Órdenes de Servicio
        </h1>
        <div class="w-8 md:hidden"></div>
    </div>

    {{-- Botón (full en móvil) --}}
    <div class="mb-6">
        <a href="{{ route('ordenes.create') }}"
           class="w-full md:w-auto justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva orden
        </a>
    </div>

    {{-- Mensajes --}}
    @if (session('success'))
        <div id="success-message" class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div id="error-message" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    @php
        $tipoOpciones = [
            'compra'            => 'Compra',
            'servicio_simple'   => 'Servicio (simple)',
            'servicio_proyecto' => 'Servicio (proyecto)',
        ];

        $acUrlOrdenes = route('ordenes.autocomplete');
    @endphp

    {{-- Filtros --}}
    <form method="GET" action="{{ route('ordenes.index') }}" class="mb-6">
        <div class="bg-white w-full rounded-xl border border-gray-200 shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">

                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <x-ordenes-autocomplete-bar
                        :autocompleteUrl="$acUrlOrdenes"
                        placeholder="Buscar por cliente, folio o servicio..."
                        inputId="buscar-ordenes"
                        resultId="resultados-ordenes"
                        name="q"
                        idName="orden_id"
                        :value="request('q')"
                        :idValue="request('orden_id')"
                        :submitOnSelect="true"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los estados</option>
                        @foreach (['Pendiente','En proceso','Completada','Cancelada'] as $opt)
                            <option value="{{ $opt }}" @selected(request('estado')===$opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="tipo_orden" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipoOpciones as $val => $label)
                            <option value="{{ $val }}" @selected(request('tipo_orden')===$val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2">
                        Filtrar
                    </button>
                    <a href="{{ route('ordenes.index') }}"
                       class="rounded-lg border px-4 py-2 text-gray-700 hover:bg-gray-50">
                        Limpiar
                    </a>
                </div>

            </div>
        </div>
    </form>

    {{-- ====== MÓVIL/TABLET: TARJETAS (hasta <lg) ====== --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($ordenes as $orden)
            @php
                $oid = $orden->id_orden_servicio
                    ?? $orden->id
                    ?? (method_exists($orden, 'getKey') ? $orden->getKey() : null);

                $folio = $orden->folio ?? ('ORD-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT));

                $pdfViewUrl = route('ordenes.pdf', ['id' => $oid]);
                $pdfDownloadUrl = route('ordenes.pdf', ['id' => $oid, 'download' => 1]);
                $editUrl = route('ordenes.edit', ['id' => $oid]);
                $deleteUrl = route('ordenes.destroy', ['id' => $oid]);

                $tipoLabel = $tipoOpciones[$orden->tipo_orden] ?? ($orden->tipo_orden ?? '—');

                $tecnicosTxt = '—';
                if($orden->tipo_orden === 'compra') $tecnicosTxt = 'No aplica';
                else if($orden->tecnicos && $orden->tecnicos->count()) $tecnicosTxt = $orden->tecnicos->pluck('name')->implode(', ');
                else $tecnicosTxt = $orden->tecnico->name ?? 'Sin asignar';
            @endphp

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">Folio</p>
                        <p class="text-base font-semibold text-gray-900 truncate">{{ $folio }}</p>

                        <p class="mt-2 text-xs text-gray-500">Cliente</p>
                        <p class="text-sm text-gray-800 truncate">{{ $orden->cliente->nombre ?? '—' }}</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="px-2 py-1 rounded-md bg-gray-100 text-gray-800 text-xs font-medium">
                                {{ $tipoLabel }}
                            </span>

                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                @class([
                                    'bg-yellow-100 text-yellow-800' => $orden->estado==='Pendiente',
                                    'bg-blue-100 text-blue-800'     => $orden->estado==='En proceso',
                                    'bg-green-100 text-green-800'   => $orden->estado==='Completada',
                                    'bg-red-100 text-red-800'       => $orden->estado==='Cancelada',
                                    'bg-gray-100 text-gray-800'     => !in_array($orden->estado, ['Pendiente','En proceso','Completada','Cancelada']),
                                ])">
                                {{ $orden->estado ?? '—' }}
                            </span>

                            <span class="px-2 py-1 rounded-md bg-gray-50 border border-gray-200 text-gray-700 text-xs">
                                Prioridad: <span class="font-semibold">{{ $orden->prioridad ?? '—' }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">Técnico(s)</span>
                        <span class="font-medium text-gray-800 text-right break-words">{{ $tecnicosTxt }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">Creación</span>
                        <span class="font-medium text-gray-800">{{ optional($orden->created_at)->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <a href="{{ $editUrl }}"
                       class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2">
                        <span class="font-semibold">Editar</span>
                    </a>

                    <button type="button"
                            class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            data-action="open-pdf"
                            data-id="{{ $oid }}"
                            data-folio="{{ $folio }}"
                            data-title="Orden de Servicio — {{ $folio }}"
                            data-view="{{ $pdfViewUrl }}"
                            data-download="{{ $pdfDownloadUrl }}">
                        <span class="font-semibold">PDF</span>
                    </button>

                    <button type="button"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            data-action="open-delete"
                            data-id="{{ $oid }}"
                            data-folio="{{ $folio }}"
                            data-delete="{{ $deleteUrl }}">
                        <span class="font-semibold">Eliminar</span>
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500">
                No hay órdenes que coincidan con tu búsqueda.
            </div>
        @endforelse
    </div>

    {{-- ====== ESCRITORIO: TABLA (lg y arriba) ====== --}}
    <div class="hidden lg:block overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr class="text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <th class="px-4 py-3 text-left">Folio</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Técnico(s)</th>
                    <th class="px-4 py-3 text-left">Estado</th>
                    <th class="px-4 py-3 text-left">Prioridad</th>
                    <th class="px-4 py-3 text-left">Creación</th>
                    <th class="px-3 py-3 text-center w-28">Editar</th>
                    <th class="px-3 py-3 text-center w-28">PDF</th>
                    <th class="px-3 py-3 text-center w-32">Eliminar</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($ordenes as $orden)
                    @php
                        $oid = $orden->id_orden_servicio
                            ?? $orden->id
                            ?? (method_exists($orden, 'getKey') ? $orden->getKey() : null);

                        $folio = $orden->folio ?? ('ORD-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT));

                        $pdfViewUrl = route('ordenes.pdf', ['id' => $oid]);
                        $pdfDownloadUrl = route('ordenes.pdf', ['id' => $oid, 'download' => 1]);
                        $editUrl = route('ordenes.edit', ['id' => $oid]);
                        $deleteUrl = route('ordenes.destroy', ['id' => $oid]);

                        $tipoLabel = $tipoOpciones[$orden->tipo_orden] ?? ($orden->tipo_orden ?? '—');
                    @endphp

                    <tr class="text-sm text-gray-800 align-middle">
                        <td class="px-4 py-3">{{ $folio }}</td>
                        <td class="px-4 py-3">{{ $orden->cliente->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $tipoLabel }}</td>

                        <td class="px-4 py-3">
                            @if($orden->tipo_orden === 'compra')
                                <span class="text-gray-500">No aplica</span>
                            @elseif($orden->tecnicos && $orden->tecnicos->count())
                                {{ $orden->tecnicos->pluck('name')->implode(', ') }}
                            @else
                                {{ $orden->tecnico->name ?? 'Sin asignar' }}
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                @class([
                                    'bg-yellow-100 text-yellow-800' => $orden->estado==='Pendiente',
                                    'bg-blue-100 text-blue-800'     => $orden->estado==='En proceso',
                                    'bg-green-100 text-green-800'   => $orden->estado==='Completada',
                                    'bg-red-100 text-red-800'       => $orden->estado==='Cancelada',
                                    'bg-gray-100 text-gray-800'     => !in_array($orden->estado, ['Pendiente','En proceso','Completada','Cancelada']),
                                ])">
                                {{ $orden->estado ?? '—' }}
                            </span>
                        </td>

                        <td class="px-4 py-3">{{ $orden->prioridad ?? '—' }}</td>
                        <td class="px-4 py-3">{{ optional($orden->created_at)->format('d/m/Y H:i') }}</td>

                        <td class="px-3 py-3 text-center">
                            <a href="{{ $editUrl }}"
                               class="px-3 py-1.5 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-sm whitespace-nowrap">
                                Editar
                            </a>
                        </td>

                        <td class="px-3 py-3 text-center">
                            <button type="button"
                                    class="px-3 py-1.5 rounded-md bg-sky-600 hover:bg-sky-700 text-white text-sm whitespace-nowrap"
                                    data-action="open-pdf"
                                    data-id="{{ $oid }}"
                                    data-folio="{{ $folio }}"
                                    data-title="Orden de Servicio — {{ $folio }}"
                                    data-view="{{ $pdfViewUrl }}"
                                    data-download="{{ $pdfDownloadUrl }}">
                                PDF
                            </button>
                        </td>

                        <td class="px-3 py-3 text-center">
                            <button type="button"
                                    class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm whitespace-nowrap"
                                    data-action="open-delete"
                                    data-id="{{ $oid }}"
                                    data-folio="{{ $folio }}"
                                    data-delete="{{ $deleteUrl }}">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500" colspan="10">
                            No hay órdenes que coincidan con tu búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if(method_exists($ordenes, 'links'))
        <div class="mt-6">
            {{ $ordenes->withQueryString()->links() }}
        </div>
    @endif

</div>

{{-- MODAL PDF --}}
<div id="pdfModal" class="hidden fixed inset-0 z-40 bg-black/50">
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-5xl bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 id="pdfModalTitle" class="font-semibold text-gray-800">PDF</h3>
        <div class="flex items-center gap-2">
          <a id="pdfDownloadBtn" href="#" target="_blank"
             class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 whitespace-nowrap">
             Descargar
          </a>
          <button type="button" class="px-3 py-1.5 rounded-md bg-gray-800 text-white text-sm whitespace-nowrap" data-action="close-pdf">
            Cerrar
          </button>
        </div>
      </div>
      <div class="h-[75vh]">
        <iframe id="pdfFrame" class="w-full h-full" src=""></iframe>
      </div>
    </div>
  </div>
</div>

{{-- MODAL ELIMINAR --}}
<div id="deleteModal" class="hidden fixed inset-0 z-40 bg-black/50">
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="px-5 py-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Eliminar orden</h3>
      </div>
      <div class="px-5 py-4 space-y-3">
        <p class="text-sm text-gray-700">
          <strong>⚠️ No se recomienda eliminar órdenes</strong> porque afectará las estadísticas de los reportes.
          Solo elimina si la orden <span class="font-semibold">no es válida</span>.
        </p>
        <p class="text-sm text-gray-600">¿Deseas eliminar la orden <span id="delFolio" class="font-mono"></span>?</p>
      </div>
      <div class="px-5 pb-4 flex items-center justify-end gap-2">
        <button type="button" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 whitespace-nowrap" data-action="close-delete">
          Cancelar
        </button>
        <form id="deleteForm" method="POST" action="#">
          @csrf
          @method('DELETE')
          <button type="submit" class="px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm whitespace-nowrap">
            Eliminar definitivamente
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const pdfModal = document.getElementById('pdfModal');
  const pdfFrame = document.getElementById('pdfFrame');
  const pdfTitle = document.getElementById('pdfModalTitle');
  const pdfDownloadBtn = document.getElementById('pdfDownloadBtn');

  const deleteModal = document.getElementById('deleteModal');
  const deleteForm  = document.getElementById('deleteForm');
  const delFolioEl  = document.getElementById('delFolio');

  function closePdf(){
    pdfModal.classList.add('hidden');
    pdfFrame.src = '';
  }
  function closeDelete(){
    deleteModal.classList.add('hidden');
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="open-pdf"]');
    if (!btn) return;

    const folio = btn.getAttribute('data-folio') || btn.getAttribute('data-id');
    const viewUrl = btn.getAttribute('data-view');
    const downloadUrl = btn.getAttribute('data-download');
    const customTitle = btn.getAttribute('data-title');

    pdfTitle.textContent = customTitle || `PDF — ${folio}`;
    pdfFrame.src = viewUrl;
    pdfDownloadBtn.href = downloadUrl || viewUrl;

    pdfModal.classList.remove('hidden');
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="close-pdf"]');
    if (!btn) return;
    closePdf();
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="open-delete"]');
    if (!btn) return;

    const folio = btn.getAttribute('data-folio') || btn.getAttribute('data-id');
    const deleteUrl = btn.getAttribute('data-delete');

    deleteForm.setAttribute('action', deleteUrl);
    delFolioEl.textContent = folio;

    deleteModal.classList.remove('hidden');
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="close-delete"]');
    if (!btn) return;
    closeDelete();
  });

  [pdfModal, deleteModal].forEach(modal=>{
    modal?.addEventListener('click', (e)=>{
      if (e.target === modal) {
        modal.classList.add('hidden');
        if (modal === pdfModal) pdfFrame.src = '';
      }
    });
  });

  // Escape key support
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
      if (!pdfModal.classList.contains('hidden')) closePdf();
      if (!deleteModal.classList.contains('hidden')) closeDelete();
    }
  });

  // Auto-hide alerts
  setTimeout(() => {
    document.getElementById('success-message')?.remove();
    document.getElementById('error-message')?.remove();
  }, 5000);
})();
</script>
@endpush
