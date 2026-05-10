@extends('layouts.sidebar-navigation')

@section('title', 'Logística')

@section('content')
@php
    $mapMovimientos = $movimientos->getCollection()->map(function ($movimiento) {
        return [
            'id' => $movimiento->id,
            'tipo' => $movimiento->tipo_label,
            'estado' => $movimiento->estado_label,
            'direccion' => $movimiento->direccion_formateada,
            'alias' => $movimiento->alias_direccion,
            'lat' => $movimiento->latitud,
            'lng' => $movimiento->longitud,
            'tecnico' => optional($movimiento->tecnico)->name,
        ];
    })->values();

    $stats = [
        'pendientes' => $movimientos->getCollection()->whereIn('estado', ['pendiente', 'asignado'])->count(),
        'en_ruta' => $movimientos->getCollection()->whereIn('estado', ['en_ruta', 'en_sitio'])->count(),
        'completados' => $movimientos->getCollection()->whereIn('estado', ['recogido', 'entregado'])->count(),
    ];
@endphp
@include('partials.logistica.leaflet-loader')

<div class="mx-auto max-w-7xl px-0 py-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
        <x-boton-volver />
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-800 sm:text-3xl">Logística</h1>
            <p class="text-sm text-gray-500">Jornadas, entregas y recolecciones ligadas a órdenes e inventario.</p>
        </div>
    </div>

    @foreach (['success', 'error'] as $key)
        @if (session($key))
            <div class="mb-4 rounded-xl border px-4 py-3 {{ $key === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-inside list-disc text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
            <div class="text-sm font-medium text-blue-900">Jornada activa</div>
            <div class="mt-2 text-2xl font-bold text-blue-700">{{ $jornadaActiva?->folio ?? '—' }}</div>
            <div class="mt-1 text-xs text-blue-800">{{ $jornadaActiva?->nombre ?: 'Sin jornada abierta' }}</div>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="text-sm font-medium text-amber-900">Pendientes</div>
            <div class="mt-2 text-2xl font-bold text-amber-700">{{ $stats['pendientes'] }}</div>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <div class="text-sm font-medium text-indigo-900">En ruta / sitio</div>
            <div class="mt-2 text-2xl font-bold text-indigo-700">{{ $stats['en_ruta'] }}</div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <div class="text-sm font-medium text-emerald-900">Recogidos / entregados</div>
            <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $stats['completados'] }}</div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @if ($jornadaActiva)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-sm font-semibold text-emerald-900">Jornada abierta</div>
                    <div class="mt-2 text-lg font-bold text-emerald-700">{{ $jornadaActiva->folio }}</div>
                    <div class="text-sm text-emerald-800">{{ $jornadaActiva->nombre ?: 'Sin nombre' }}</div>
                    <div class="mt-1 text-xs text-emerald-700">Abierta {{ optional($jornadaActiva->opened_at)->format('d/m/Y H:i') ?: 'sin hora' }}</div>

                    <form method="POST" action="{{ route('logistica.jornadas.close', $jornadaActiva) }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Cerrar jornada
                        </button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('logistica.jornadas.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nombre de la jornada</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej. Ruta martes zona centro">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Fecha</label>
                        <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Observaciones</label>
                        <textarea name="observaciones" rows="3"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Cobertura, vehículo, notas de la ruta">{{ old('observaciones') }}</textarea>
                    </div>
                    <button class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Abrir jornada
                    </button>
                </form>
            @endif

            <div class="mt-5 border-t pt-4">
                <div class="text-sm font-semibold text-gray-800">Historial reciente</div>
                <div class="mt-3 space-y-2">
                    @forelse ($jornadas as $jornada)
                        <div class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <div class="font-medium text-gray-900">{{ $jornada->folio }}{{ $jornada->nombre ? ' — ' . $jornada->nombre : '' }}</div>
                            <div class="text-xs text-gray-500">{{ ucfirst($jornada->estado) }} · {{ $jornada->movimientos_count }} movimientos</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Todavía no hay jornadas registradas.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Mapa de movimientos</h2>
                    <p class="text-sm text-gray-500">Se muestran los puntos de entrega y recolección de esta página.</p>
                </div>
            </div>
            <div id="logistica-gerencia-map" class="relative z-0 isolate h-[420px] overflow-hidden rounded-2xl border border-gray-200 bg-slate-100"></div>
        </div>
    </div>

    <form method="GET" action="{{ route('logistica.index') }}" class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Buscar</label>
                <x-ordenes-autocomplete-bar
                    :autocompleteUrl="route('logistica.autocomplete')"
                    placeholder="Movimiento, jornada, cliente, proveedor, direccion u orden..."
                    inputId="buscar-logistica"
                    resultId="resultados-logistica"
                    name="q"
                    idName="movimiento_id"
                    :value="$filtros['q'] ?? request('q')"
                    :idValue="$filtros['movimiento_id'] ?? request('movimiento_id')"
                    :submitOnSelect="true"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                <select name="estado" class="w-full rounded-xl border border-gray-300 px-4 py-3">
                    <option value="">Todos</option>
                    @foreach (['pendiente', 'asignado', 'en_ruta', 'en_sitio', 'recogido', 'entregado', 'incidencia', 'cancelado'] as $estado)
                        <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                <select name="tipo" class="w-full rounded-xl border border-gray-300 px-4 py-3">
                    <option value="">Todos</option>
                    <option value="entrega" @selected(($filtros['tipo'] ?? '') === 'entrega')>Entrega</option>
                    <option value="recoleccion" @selected(($filtros['tipo'] ?? '') === 'recoleccion')>Recolección</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Técnico</label>
                <select name="tecnico_id" class="w-full rounded-xl border border-gray-300 px-4 py-3">
                    <option value="">Todos</option>
                    @foreach ($tecnicos as $tecnico)
                        <option value="{{ $tecnico->id }}" @selected((string) ($filtros['tecnico_id'] ?? '') === (string) $tecnico->id)>{{ $tecnico->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="flex-1 rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700">Buscar</button>
                <a href="{{ route('logistica.index') }}" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="space-y-4">
        @forelse ($movimientos as $movimiento)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">#{{ $movimiento->id }}</span>
                            <span class="rounded-full {{ $movimiento->tipo === 'recoleccion' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }} px-3 py-1 text-xs font-semibold">
                                {{ $movimiento->tipo_label }}
                            </span>
                            @if (is_null($movimiento->tecnico_id) && in_array($movimiento->estado, ['pendiente', 'asignado'], true) && (($movimiento->tipo === 'recoleccion' && $movimiento->origen_tipo === 'inventario_programado') || ($movimiento->tipo === 'entrega' && $movimiento->origen_tipo === 'orden_servicio')))
                                <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                                    Disponible para cualquier técnico
                                </span>
                            @endif
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                {{ $movimiento->estado_label }}
                            </span>
                        </div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ $movimiento->alias_direccion ?: 'Sin alias' }}
                        </div>
                        <div class="text-sm text-gray-600">{{ $movimiento->direccion_formateada ?: 'Sin dirección capturada' }}</div>
                        <div class="text-sm text-gray-500">
                            Técnico:
                            <span class="font-medium text-gray-700">{{ optional($movimiento->tecnico)->name ?: 'Disponible para tomar' }}</span>
                            · Jornada:
                            <span class="font-medium text-gray-700">{{ optional($movimiento->jornada)->folio ?: 'Pendiente' }}</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            Origen:
                            <span class="font-medium text-gray-700">
                                @if ($movimiento->orden_servicio_id)
                                    {{ $movimiento->ordenServicio?->folio ?? 'Orden vinculada' }}
                                @elseif ($movimiento->clave_proveedor)
                                    {{ $movimiento->proveedor?->nombre ?? 'Proveedor' }}
                                @else
                                    {{ ucfirst(str_replace('_', ' ', (string) $movimiento->origen_tipo)) }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="w-full max-w-xl rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <form method="POST" action="{{ route('logistica.movimientos.update', $movimiento) }}" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Jornada</label>
                                <select name="jornada_logistica_id" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Sin jornada</option>
                                    @foreach ($jornadas as $jornada)
                                        <option value="{{ $jornada->id }}" @selected((int) $movimiento->jornada_logistica_id === (int) $jornada->id)>{{ $jornada->folio }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Técnico</label>
                                <select name="tecnico_id" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Sin asignar</option>
                                    @foreach ($tecnicos as $tecnico)
                                        <option value="{{ $tecnico->id }}" @selected((int) $movimiento->tecnico_id === (int) $tecnico->id)>{{ $tecnico->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Fecha programada</label>
                                <input type="date" name="fecha_programada" value="{{ optional($movimiento->fecha_programada)->toDateString() }}"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Hora</label>
                                <input type="time" name="hora_programada" value="{{ $movimiento->hora_programada ? \Illuminate\Support\Str::of((string) $movimiento->hora_programada)->substr(0, 5) : '' }}"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-600">Observaciones</label>
                                <textarea name="observaciones" rows="2"
                                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">{{ old('observaciones', $movimiento->observaciones) }}</textarea>
                            </div>
                            <div class="md:col-span-2 flex flex-col gap-2 sm:flex-row sm:justify-between">
                                @if ($movimiento->tipo === 'recoleccion' && $movimiento->estado === 'recogido' && !$movimiento->recepcion_confirmada_at)
                                    <button formaction="{{ route('logistica.movimientos.confirmarRecepcion', $movimiento) }}" formmethod="POST"
                                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                        Confirmar recepción en inventario
                                    </button>
                                @else
                                    <span class="text-xs text-gray-500">
                                        @if ($movimiento->recepcion_confirmada_at)
                                            Recepción confirmada {{ $movimiento->recepcion_confirmada_at->format('d/m/Y H:i') }}
                                        @else
                                            Esperando avance del técnico
                                        @endif
                                    </span>
                                @endif

                                <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($movimiento->detalles->isNotEmpty())
                    <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="text-sm font-semibold text-gray-800">Detalle de productos</div>
                        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                            @foreach ($movimiento->detalles as $detalle)
                                <div class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                                    <div class="font-medium text-gray-900">{{ $detalle->nombre_producto }}</div>
                                    <div class="text-gray-500">Cantidad: {{ number_format((float) $detalle->cantidad, 2) }} {{ $detalle->unidad ?: '' }}</div>
                                    <div class="text-gray-500">Control: {{ $detalle->tipo_control ?: '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($movimiento->evidencias->isNotEmpty())
                    <div class="mt-4">
                        <div class="mb-2 text-sm font-semibold text-gray-800">Evidencias</div>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            @foreach ($movimiento->evidencias as $evidencia)
                                <a href="{{ asset('storage/' . ltrim($evidencia->ruta_archivo, '/')) }}" target="_blank"
                                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <img src="{{ asset('storage/' . ltrim($evidencia->ruta_archivo, '/')) }}" alt="{{ $evidencia->tipo_foto }}" class="h-28 w-full object-cover">
                                    <div class="px-3 py-2 text-xs font-medium text-gray-700">{{ \Illuminate\Support\Str::of($evidencia->tipo_foto)->replace('_', ' ')->title() }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-gray-500">
                No hay movimientos logísticos con los filtros seleccionados.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $movimientos->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const markers = @json($mapMovimientos);
    const el = document.getElementById('logistica-gerencia-map');

    if (!el) return;

    const showFallback = (message) => {
        el.innerHTML = `
            <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center text-sm text-slate-600">
                <div>
                    <div class="font-semibold text-slate-700">No se pudo cargar el mapa.</div>
                    <div class="mt-2">${message}</div>
                </div>
            </div>
        `;
    };

    window.__logisticaEnsureLeaflet()
        .then((L) => {
            const map = L.map(el, {
                center: [20.588793, -100.389888],
                zoom: 11,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const bounds = [];

            markers.forEach((item) => {
                if (item.lat === null || item.lng === null) return;
                const lat = Number(item.lat);
                const lng = Number(item.lng);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                bounds.push([lat, lng]);

                const popup = `
                    <div style="max-width:250px">
                        <strong>${item.tipo} #${item.id}</strong><br>
                        ${item.alias ? `${item.alias}<br>` : ''}
                        ${item.direccion || 'Sin dirección'}<br>
                        Estado: ${item.estado}<br>
                        Técnico: ${item.tecnico || 'Sin asignar'}<br>
                        <a href="${window.__logisticaBuildOsmUrl(lat, lng)}" target="_blank" rel="noopener noreferrer">Abrir en mapa</a>
                    </div>
                `;

                L.marker([lat, lng]).addTo(map).bindPopup(popup);
            });

            if (bounds.length) {
                map.fitBounds(bounds, { padding: [24, 24] });
            }
        })
        .catch(() => {
            showFallback('Revisa tu conexión o vuelve a cargar la página.');
        });
});
</script>
@endpush
