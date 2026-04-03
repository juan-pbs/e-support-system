@extends('layouts.sidebar-navigation')

@section('title', 'Ordenes de Servicio')

@section('content')
<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    {{-- Header --}}
    <div class="mb-6 grid grid-cols-[auto,minmax(0,1fr),auto] items-center gap-3">
        <div class="flex items-center justify-start">
            <x-boton-volver />
        </div>
        <div class="min-w-0 px-2">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 text-center">
                Ordenes de Servicio
            </h1>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('ordenes.create') }}"
               class="justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 whitespace-nowrap shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva orden
            </a>

            <button type="button"
                    data-action="open-export"
                    class="justify-center bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 whitespace-nowrap shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5m0 0 5-5m-5 5V4" />
                </svg>
                Descargar OSs
            </button>
        </div>
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
        $exportDesdeDefault = now()->subDays(29)->format('Y-m-d');
        $exportHastaDefault = now()->format('Y-m-d');
    @endphp

    {{-- Filtros --}}
    <form method="GET" action="{{ route('ordenes.index') }}" class="mb-6">
        <div class="bg-white w-full rounded-xl border border-gray-200 shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">

                <div class="xl:col-span-2">
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

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Facturacion</label>
                    <select name="facturado" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas</option>
                        <option value="1" @selected(request('facturado') === '1')>Facturado</option>
                        <option value="0" @selected(request('facturado') === '0')>No facturado</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tecnico</label>
                    <select name="tecnico_id" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        @foreach (($tecnicos ?? collect()) as $tec)
                            <option value="{{ $tec->id }}" @selected((string)request('tecnico_id')===(string)$tec->id)>{{ $tec->name }}</option>
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
                $facturacionUpdateUrl = route('ordenes.facturacion.update', ['id' => $oid]);
                $estadoValor = (string) ($orden->estado ?? '-');
                $estadoBadgeClass = match ($estadoValor) {
                    'Pendiente' => 'bg-yellow-100 text-yellow-800',
                    'En proceso' => 'bg-blue-100 text-blue-800',
                    'Completada', 'Completado', 'Finalizado', 'Finalizada' => 'bg-green-100 text-green-800',
                    'Cancelada', 'Cancelado' => 'bg-red-100 text-red-800',
                    default => 'bg-gray-100 text-gray-800',
                };
                $facturacionBadgeClass = (int) ($orden->facturado ?? 0) === 1
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-amber-100 text-amber-800';

                $tipoLabel = $tipoOpciones[$orden->tipo_orden] ?? ($orden->tipo_orden ?? '—');
                $facturacionLabel = $orden->facturacion_label ?? ((int) ($orden->facturado ?? 0) === 1 ? 'Facturado' : 'No facturado');

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

                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ $estadoBadgeClass }}">
                                {{ $estadoValor }}
                            </span>

                            <span class="px-2 py-1 rounded-md bg-gray-50 border border-gray-200 text-gray-700 text-xs">
                                Prioridad: <span class="font-semibold">{{ $orden->prioridad ?? '—' }}</span>
                            </span>

                            <span class="px-2 py-1 rounded-md text-xs font-medium {{ $facturacionBadgeClass }}">
                                {{ $facturacionLabel }}
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
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">Facturacion</span>
                        <span class="font-medium {{ (int) ($orden->facturado ?? 0) === 1 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $facturacionLabel }}</span>
                    </div>
                    <form method="POST" action="{{ $facturacionUpdateUrl }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="facturado" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="0" @selected((int) ($orden->facturado ?? 0) === 0)>No facturado</option>
                            <option value="1" @selected((int) ($orden->facturado ?? 0) === 1)>Facturado</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-2 whitespace-nowrap">
                            Guardar
                        </button>
                    </form>
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
                    <th class="px-3 py-3 text-center w-24">Notas</th>
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
                        $facturacionUpdateUrl = route('ordenes.facturacion.update', ['id' => $oid]);
                        $facturacionLabel = $orden->facturacion_label ?? ((int) ($orden->facturado ?? 0) === 1 ? 'Facturado' : 'No facturado');
                        $estadoValor = (string) ($orden->estado ?? '-');
                        $estadoBadgeClass = match ($estadoValor) {
                            'Pendiente' => 'bg-yellow-100 text-yellow-800',
                            'En proceso' => 'bg-blue-100 text-blue-800',
                            'Completada', 'Completado', 'Finalizado', 'Finalizada' => 'bg-green-100 text-green-800',
                            'Cancelada', 'Cancelado' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                        $facturacionBadgeClass = (int) ($orden->facturado ?? 0) === 1
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800';

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
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ $estadoBadgeClass }}">
                                    {{ $estadoValor }}
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $facturacionBadgeClass }}">
                                    {{ $facturacionLabel }}
                                </span>
                            </div>
                        </td>

                        <td class="px-4 py-3">{{ $orden->prioridad ?? '—' }}</td>
                        <td class="px-4 py-3">{{ optional($orden->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-3 text-center">
                            <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white" title="Ver notas internas" onclick="showOrderNotesModalFromB64('{{ base64_encode(json_encode(['folio' => $folio, 'cliente' => ($orden->cliente->nombre ?? '—'), 'tipo' => $tipoLabel, 'estado' => ($orden->estado ?? '—'), 'prioridad' => ($orden->prioridad ?? '—'), 'facturacion' => $facturacionLabel, 'facturado' => (int) ($orden->facturado ?? 0), 'facturacion_update_url' => $facturacionUpdateUrl, 'tecnicos' => ($orden->tipo_orden === 'compra' ? 'No aplica' : (($orden->tecnicos && $orden->tecnicos->count()) ? $orden->tecnicos->pluck('name')->implode(', ') : ($orden->tecnico->name ?? 'Sin asignar'))), 'servicio' => ($orden->servicio ?? ''), 'resumen' => ($orden->descripcion_servicio ?? $orden->descripcion ?? ''), 'notas' => ($orden->condiciones_generales ?? '')], JSON_UNESCAPED_UNICODE)) }}')">
                                <span class="text-sm font-semibold">i</span>
                            </button>
                        </td>

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
                        <td class="px-4 py-6 text-center text-gray-500" colspan="11">
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

{{-- MODAL NOTAS --}}
<div id="notesModal" class="hidden fixed inset-0 z-40 bg-black/50">
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">Notas internas y resumen</h3>
        <button type="button" class="px-3 py-1.5 rounded-md bg-gray-800 text-white text-sm whitespace-nowrap" onclick="closeOrderNotesModal()">Cerrar</button>
      </div>
      <div class="px-5 py-4 space-y-3 text-sm">
        <div><span class="text-gray-500">Folio:</span> <span id="notesFolio" class="font-medium">—</span></div>
        <div><span class="text-gray-500">Cliente:</span> <span id="notesCliente" class="font-medium">—</span></div>
        <div><span class="text-gray-500">Tipo / Estado / Prioridad:</span> <span id="notesMeta" class="font-medium">—</span></div>
        <div class="space-y-2">
          <div><span class="text-gray-500">Facturacion:</span> <span id="notesFacturacion" class="font-medium">—</span></div>
          <form id="notesFacturacionForm" method="POST" action="#" class="flex flex-col sm:flex-row sm:items-center gap-2">
            @csrf
            @method('PATCH')
            <select id="notesFacturadoSelect" name="facturado" class="w-full sm:w-52 rounded-lg border-gray-300 text-sm focus:ring-2 focus:ring-blue-500">
              <option value="0">No facturado</option>
              <option value="1">Facturado</option>
            </select>
            <button id="notesFacturacionSubmit" type="submit" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm whitespace-nowrap">
              Guardar facturacion
            </button>
          </form>
        </div>
        <div><span class="text-gray-500">Técnico(s):</span> <span id="notesTecnicos" class="font-medium">—</span></div>
        <div><span class="text-gray-500">Servicio:</span> <p id="notesServicio" class="mt-1 bg-slate-50 border rounded p-2">—</p></div>
        <div><span class="text-gray-500">Resumen:</span> <p id="notesResumen" class="mt-1 bg-slate-50 border rounded p-2 whitespace-pre-line">—</p></div>
        <div><span class="text-gray-500">Notas internas:</span> <p id="notesInternas" class="mt-1 bg-amber-50 border border-amber-200 rounded p-2 whitespace-pre-line">Sin notas internas</p></div>
      </div>
    </div>
  </div>
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

{{-- MODAL EXPORTAR --}}
<div id="exportModal" class="hidden fixed inset-0 z-40 bg-black/50">
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">Descargar OSs</h3>
          <p class="text-sm text-gray-500">Selecciona el rango de fechas para generar el Excel.</p>
        </div>
        <button type="button" class="px-3 py-1.5 rounded-md bg-gray-800 text-white text-sm whitespace-nowrap" data-action="close-export">
          Cerrar
        </button>
      </div>

      <form method="GET" action="{{ route('ordenes.export') }}" class="px-5 py-4 space-y-4">
        <div class="flex flex-wrap gap-2">
          <button type="button" class="px-3 py-1.5 rounded-full border text-sm hover:bg-gray-50" data-range-days="7">Ultimos 7 dias</button>
          <button type="button" class="px-3 py-1.5 rounded-full border text-sm hover:bg-gray-50" data-range-days="15">Ultimos 15 dias</button>
          <button type="button" class="px-3 py-1.5 rounded-full border text-sm hover:bg-gray-50" data-range-days="30">Ultimos 30 dias</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="exportDesde" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
            <input id="exportDesde" name="desde" type="date" value="{{ $exportDesdeDefault }}" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" required>
          </div>
          <div>
            <label for="exportHasta" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
            <input id="exportHasta" name="hasta" type="date" value="{{ $exportHastaDefault }}" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" required>
          </div>
        </div>

        <p class="text-xs text-gray-500">
          El archivo incluye datos generales, tecnicos, importes, estatus de facturacion y totales de cada orden dentro del rango.
        </p>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-3 py-1.5 rounded-md border text-sm hover:bg-gray-50 whitespace-nowrap" data-action="close-export">
            Cancelar
          </button>
          <button type="submit" class="px-3 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm whitespace-nowrap">
            Descargar Excel
          </button>
        </div>
      </form>
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
  window.showOrderNotesModalFromB64 = function (payloadB64) {
    try {
      const b64 = String(payloadB64 || '');
      const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
      const jsonText = new TextDecoder('utf-8').decode(bytes);
      const data = JSON.parse(jsonText);
      window.showOrderNotesModal(data || {});
    } catch (e) {
      console.error('Error al abrir modal de notas', e);
    }
  };
  window.showOrderNotesModal = function (data) {
    const modal = document.getElementById('notesModal');
    if (!modal) return;
    document.getElementById('notesFolio').textContent = data.folio || '—';
    document.getElementById('notesCliente').textContent = data.cliente || '—';
    document.getElementById('notesMeta').textContent = [data.tipo || '—', data.estado || '—', data.prioridad || '—'].join(' / ');
    document.getElementById('notesFacturacion').textContent = data.facturacion || 'No facturado';
    document.getElementById('notesTecnicos').textContent = data.tecnicos || '—';
    document.getElementById('notesServicio').textContent = data.servicio || '—';
    document.getElementById('notesResumen').textContent = data.resumen || '—';
    document.getElementById('notesInternas').textContent = data.notas || 'Sin notas internas';
    if (notesFacturacionForm && notesFacturadoSelect && notesFacturacionSubmit) {
      const updateUrl = data.facturacion_update_url || '';
      notesFacturadoSelect.value = String(Number(data.facturado || 0));
      notesFacturacionForm.setAttribute('action', updateUrl || '#');
      notesFacturadoSelect.disabled = !updateUrl;
      notesFacturacionSubmit.disabled = !updateUrl;
      notesFacturacionSubmit.classList.toggle('opacity-60', !updateUrl);
      notesFacturacionSubmit.classList.toggle('cursor-not-allowed', !updateUrl);
    }
    modal.classList.remove('hidden');
  };
  window.closeOrderNotesModal = function () {
    document.getElementById('notesModal')?.classList.add('hidden');
  };
  const pdfModal = document.getElementById('pdfModal');
  const pdfFrame = document.getElementById('pdfFrame');
  const pdfTitle = document.getElementById('pdfModalTitle');
  const pdfDownloadBtn = document.getElementById('pdfDownloadBtn');
  const exportModal = document.getElementById('exportModal');
  const exportDesde = document.getElementById('exportDesde');
  const exportHasta = document.getElementById('exportHasta');

  const deleteModal = document.getElementById('deleteModal');
  const deleteForm  = document.getElementById('deleteForm');
  const delFolioEl  = document.getElementById('delFolio');
  const notesFacturacionForm = document.getElementById('notesFacturacionForm');
  const notesFacturadoSelect = document.getElementById('notesFacturadoSelect');
  const notesFacturacionSubmit = document.getElementById('notesFacturacionSubmit');

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
    pdfModal.classList.add('hidden');
    pdfFrame.src = '';
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="open-export"]');
    if (!btn || !exportModal) return;
    exportModal.classList.remove('hidden');
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-action="close-export"]');
    if (!btn || !exportModal) return;
    exportModal.classList.add('hidden');
  });

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-range-days]');
    if (!btn || !exportDesde || !exportHasta) return;

    const days = Number(btn.getAttribute('data-range-days') || 0);
    if (!days) return;

    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - (days - 1));

    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    exportDesde.value = formatDate(start);
    exportHasta.value = formatDate(end);
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
    deleteModal.classList.add('hidden');
  });

  [pdfModal, deleteModal, exportModal, document.getElementById('notesModal')].forEach(modal=>{
    modal?.addEventListener('click', (e)=>{
      if (e.target === modal) {
        modal.classList.add('hidden');
        if (modal === pdfModal) pdfFrame.src = '';
      }
    });
  });

  // Auto-hide alerts
  setTimeout(() => {
    document.getElementById('success-message')?.remove();
    document.getElementById('error-message')?.remove();
  }, 5000);
})();
</script>
@endpush
