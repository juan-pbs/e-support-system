@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Detalle logístico')

@section('content')
@php
    $mapMovimiento = [
        'id' => $movimiento->id,
        'tipo' => $movimiento->tipo_label,
        'estado' => $movimiento->estado_label,
        'direccion' => $movimiento->direccion_formateada,
        'alias' => $movimiento->alias_direccion,
        'lat' => $movimiento->latitud,
        'lng' => $movimiento->longitud,
        'tecnico_id' => $movimiento->tecnico_id,
    ];
@endphp
@include('partials.logistica.leaflet-loader')

<div class="mx-auto max-w-6xl px-0 py-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
        <x-boton-volver />
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-800 sm:text-3xl">Movimiento #{{ $movimiento->id }}</h1>
            <p class="text-sm text-gray-500">{{ $movimiento->tipo_label }} · {{ $movimiento->estado_label }}</p>
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">#{{ $movimiento->id }}</span>
                    <span class="rounded-full {{ $movimiento->tipo === 'recoleccion' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }} px-3 py-1 text-xs font-semibold">
                        {{ $movimiento->tipo_label }}
                    </span>
                    @if (is_null($movimiento->tecnico_id) && $movimiento->tipo === 'recoleccion' && $movimiento->origen_tipo === 'inventario_programado')
                        <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                            Disponible para tomar
                        </span>
                    @endif
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        {{ $movimiento->estado_label }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-sm font-medium text-gray-500">Alias</div>
                        <div class="text-base font-semibold text-gray-900">{{ $movimiento->alias_direccion ?: 'Sin alias' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Jornada</div>
                        <div class="text-base font-semibold text-gray-900">{{ optional($movimiento->jornada)->folio ?: 'Sin jornada' }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-sm font-medium text-gray-500">Dirección</div>
                        <div class="text-base font-semibold text-gray-900">{{ $movimiento->direccion_formateada ?: 'Sin dirección' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Contacto</div>
                        <div class="text-base font-semibold text-gray-900">{{ $movimiento->contacto ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Teléfono</div>
                        <div class="text-base font-semibold text-gray-900">{{ $movimiento->telefono ?: '—' }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-sm font-medium text-gray-500">Referencia</div>
                        <div class="text-base font-semibold text-gray-900">{{ $movimiento->referencia ?: '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Mapa del punto</h2>
                        <p id="movement-geo-status" class="text-sm text-gray-500">Solicitando ubicación actual para recalcular la ruta…</p>
                    </div>
                    <button
                        type="button"
                        id="open-google-maps-movement"
                        class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled
                    >
                        Abrir en Google Maps
                    </button>
                </div>
                <div id="logistica-tecnico-show-map" class="relative z-0 isolate h-[360px] overflow-hidden rounded-2xl border border-gray-200 bg-slate-100"></div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Productos</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($movimiento->detalles as $detalle)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $detalle->nombre_producto }}</div>
                            <div class="text-sm text-gray-500">Cantidad: {{ number_format((float) $detalle->cantidad, 2) }} {{ $detalle->unidad ?: '' }}</div>
                            <div class="text-sm text-gray-500">Control: {{ $detalle->tipo_control ?: '—' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Avance</h2>
                <div class="mt-4 space-y-3">
                    <form method="POST" action="{{ route('tecnico.logistica.estado', $movimiento) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="estado" value="en_ruta">
                        <input type="hidden" name="current_lat" class="current-lat">
                        <input type="hidden" name="current_lng" class="current-lng">
                        <button class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700">
                            Marcar en ruta
                        </button>
                    </form>

                    <form method="POST" action="{{ route('tecnico.logistica.estado', $movimiento) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="estado" value="en_sitio">
                        <input type="hidden" name="current_lat" class="current-lat">
                        <input type="hidden" name="current_lng" class="current-lng">
                        <button class="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-medium text-white hover:bg-amber-600">
                            Marcar en sitio
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Cerrar movimiento</h2>
                <p class="mt-1 text-sm text-gray-500">Puedes adjuntar evidencias opcionales y tu ubicación actual para completar esta {{ strtolower($movimiento->tipo_label) }}.</p>

                <form method="POST" action="{{ route('tecnico.logistica.completar', $movimiento) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="current_lat" class="current-lat">
                    <input type="hidden" name="current_lng" class="current-lng">

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Fotos / evidencias (opcional)</label>
                        <input type="file" name="evidencias[]" accept="image/*" multiple class="w-full rounded-xl border border-gray-300 px-3 py-2">
                        <p class="mt-1 text-xs text-gray-500">Puedes seleccionar varias imágenes en un solo apartado.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Comentario</label>
                        <textarea name="comentario" rows="3" class="w-full rounded-xl border border-gray-300 px-4 py-3" placeholder="Observaciones del cierre o incidencia atendida">{{ old('comentario') }}</textarea>
                    </div>

                    <button class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white hover:bg-emerald-700">
                        {{ $movimiento->tipo === 'recoleccion' ? 'Marcar recogido' : 'Marcar entregado' }}
                    </button>
                </form>
            </div>

            @if ($movimiento->evidencias->isNotEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-800">Evidencias registradas</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3">
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
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const point = @json($mapMovimiento);
    const geoStatus = document.getElementById('movement-geo-status');
    const latInputs = document.querySelectorAll('.current-lat');
    const lngInputs = document.querySelectorAll('.current-lng');
    const el = document.getElementById('logistica-tecnico-show-map');
    const googleMapsButton = document.getElementById('open-google-maps-movement');
    let latestOrigin = null;

    function syncCoords(lat, lng) {
        latInputs.forEach(input => input.value = lat);
        lngInputs.forEach(input => input.value = lng);
    }

    function showFallback(message) {
        if (!el) return;
        el.innerHTML = `
            <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center text-sm text-slate-600">
                <div>
                    <div class="font-semibold text-slate-700">No se pudo cargar el mapa.</div>
                    <div class="mt-2">${message}</div>
                </div>
            </div>
        `;
    }

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition((position) => {
            syncCoords(position.coords.latitude, position.coords.longitude);
            latestOrigin = {
                lat: Number(position.coords.latitude),
                lng: Number(position.coords.longitude),
            };
            if (googleMapsButton && point.lat !== null && point.lng !== null) {
                googleMapsButton.disabled = false;
            }
            geoStatus.textContent = 'Ubicación actual lista para validar llegada, cierre y ruta.';
        }, () => {
            geoStatus.textContent = 'Activa la ubicación del dispositivo para validar este movimiento.';
            if (googleMapsButton) {
                googleMapsButton.disabled = true;
            }
        }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 });
    }

    if (!el) return;

    window.__logisticaEnsureLeaflet()
        .then((L) => {
            const hasPoint = point.lat !== null && point.lng !== null;
            const center = hasPoint
                ? [Number(point.lat), Number(point.lng)]
                : [20.588793, -100.389888];

            const map = L.map(el, {
                center,
                zoom: hasPoint ? 15 : 11,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            if (hasPoint) {
                const lat = Number(point.lat);
                const lng = Number(point.lng);
                const popup = `
                    <div style="max-width:240px">
                        <strong>${point.tipo} #${point.id}</strong><br>
                        ${point.alias ? `${point.alias}<br>` : ''}
                        ${point.direccion || 'Sin dirección'}<br>
                        Estado: ${point.estado}<br>
                        <a href="${window.__logisticaBuildOsmUrl(lat, lng)}" target="_blank" rel="noopener noreferrer">Abrir en mapa</a>
                    </div>
                `;

                L.marker([lat, lng]).addTo(map).bindPopup(popup);
            }

            let routeLayer = null;
            let currentMarker = null;

            const drawRoute = async (current) => {
                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }

                if (!current || !hasPoint) {
                    return;
                }

                let routeCoordinates = [
                    [current[0], current[1]],
                    [Number(point.lat), Number(point.lng)],
                ];

                try {
                    const fetchedRoute = await window.__logisticaFetchOsmRoute(routeCoordinates);
                    if (Array.isArray(fetchedRoute) && fetchedRoute.length >= 2) {
                        routeCoordinates = fetchedRoute;
                    }
                } catch (error) {
                    routeCoordinates = [
                        [current[0], current[1]],
                        [Number(point.lat), Number(point.lng)],
                    ];
                }

                routeLayer = L.polyline(routeCoordinates, {
                    color: '#2563eb',
                    weight: 5,
                    opacity: 0.8,
                }).addTo(map);

                map.fitBounds(routeLayer.getBounds(), { padding: [24, 24] });
            };

            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(async (position) => {
                    const current = [Number(position.coords.latitude), Number(position.coords.longitude)];
                    syncCoords(current[0], current[1]);
                    latestOrigin = { lat: current[0], lng: current[1] };
                    if (googleMapsButton && hasPoint) {
                        googleMapsButton.disabled = false;
                    }

                    if (currentMarker) {
                        currentMarker.setLatLng(current);
                    } else {
                        currentMarker = L.circleMarker(current, {
                            radius: 8,
                            color: '#065f46',
                            weight: 2,
                            fillColor: '#10b981',
                            fillOpacity: 1,
                        }).addTo(map).bindPopup('Tu ubicación actual');
                    }

                    if (!hasPoint) {
                        map.setView(current, 15);
                    }

                    await drawRoute(current);
                }, () => {
                    geoStatus.textContent = 'Activa la ubicación del dispositivo para validar este movimiento.';
                    if (googleMapsButton) {
                        googleMapsButton.disabled = true;
                    }
                }, {
                    enableHighAccuracy: true,
                    maximumAge: 10000,
                    timeout: 15000,
                });
            } else if (hasPoint) {
                map.setView(center, 15);
            }

            googleMapsButton?.addEventListener('click', () => {
                if (!latestOrigin || !hasPoint) {
                    return;
                }

                const url = window.__logisticaBuildGoogleMapsRouteUrl(latestOrigin, [{
                    lat: Number(point.lat),
                    lng: Number(point.lng),
                }]);

                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });
        })
        .catch(() => {
            showFallback('Revisa tu conexión o vuelve a cargar la página.');
            if (googleMapsButton) {
                googleMapsButton.disabled = true;
            }
        });
});
</script>
@endpush
