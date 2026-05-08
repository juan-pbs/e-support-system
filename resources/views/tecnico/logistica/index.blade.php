@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Logística')

@section('content')
@php
    $mapMovimientos = $movimientos->getCollection()->map(function ($movimiento) {
        return [
            'id' => $movimiento->id,
            'tipo' => $movimiento->tipo_label,
            'estado' => $movimiento->estado_label,
            'estado_key' => $movimiento->estado,
            'direccion' => $movimiento->direccion_formateada,
            'alias' => $movimiento->alias_direccion,
            'lat' => $movimiento->latitud,
            'lng' => $movimiento->longitud,
            'tecnico_id' => $movimiento->tecnico_id,
            'url' => route('tecnico.logistica.show', $movimiento),
        ];
    })->values();
@endphp
@include('partials.logistica.leaflet-loader')

<div class="mx-auto max-w-7xl px-0 py-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 sm:text-3xl">Ruta logística</h1>
        <p class="text-sm text-gray-500">Tus entregas y recolecciones activas con mapa y validación por ubicación actual.</p>
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

    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Mapa de ruta</h2>
                <p class="text-sm text-gray-500">La ruta se recalcula con tu ubicación actual y ordena las recolecciones o entregas para recorrer el trayecto más corto posible.</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    id="open-google-maps-route"
                    class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled
                >
                    Abrir ruta en Google Maps
                </button>
                <div id="geo-status" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">Buscando ubicación…</div>
            </div>
        </div>
        <div id="logistica-tecnico-map" class="relative z-0 isolate h-[380px] overflow-hidden rounded-2xl border border-gray-200 bg-slate-100"></div>
    </div>

    <div class="space-y-4">
        @forelse ($movimientos as $movimiento)
            <a href="{{ route('tecnico.logistica.show', $movimiento) }}" class="block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
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
                        <div class="mt-2 text-lg font-semibold text-gray-900">{{ $movimiento->alias_direccion ?: 'Sin alias' }}</div>
                        <div class="text-sm text-gray-600">{{ $movimiento->direccion_formateada ?: 'Sin dirección capturada' }}</div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $movimiento->tipo === 'recoleccion' ? 'Origen proveedor / inventario' : 'Entrega desde orden de servicio' }}
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        <div>Programada: {{ optional($movimiento->fecha_programada)->format('d/m/Y') ?: '—' }}</div>
                        <div>Jornada: {{ optional($movimiento->jornada)->folio ?: 'Sin jornada' }}</div>
                        <div>
                            Responsable:
                            {{ optional($movimiento->tecnico)->name ?: 'Se asigna cuando un técnico la toma' }}
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-gray-500">
                No tienes movimientos logísticos asignados en este momento.
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
    const geoStatus = document.getElementById('geo-status');
    const el = document.getElementById('logistica-tecnico-map');
    const googleRouteButton = document.getElementById('open-google-maps-route');

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

            const destinationMarkers = [];
            const destinationBounds = [];

            markers.forEach((item) => {
                if (item.lat === null || item.lng === null) return;
                const lat = Number(item.lat);
                const lng = Number(item.lng);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                destinationBounds.push([lat, lng]);
                destinationMarkers.push({
                    ...item,
                    lat,
                    lng,
                });

                const popup = `
                    <div style="max-width:240px">
                        <strong>${item.tipo} #${item.id}</strong><br>
                        ${item.alias ? `${item.alias}<br>` : ''}
                        ${item.direccion || 'Sin dirección'}<br>
                        Estado: ${item.estado}<br>
                        <a href="${item.url}" style="display:inline-block;margin:6px 0 4px;">Abrir detalle</a><br>
                        <a href="${window.__logisticaBuildOsmUrl(lat, lng)}" target="_blank" rel="noopener noreferrer">Abrir en mapa</a>
                    </div>
                `;

                L.marker([lat, lng]).addTo(map).bindPopup(popup);
            });

            let currentMarker = null;
            let routeLayer = null;
            let firstFitDone = false;
            let routeRequestId = 0;
            let latestOrigin = null;
            let latestOrderedStops = [];

            const drawRoute = async (origin) => {
                const requestId = ++routeRequestId;

                if (routeLayer) {
                    map.removeLayer(routeLayer);
                    routeLayer = null;
                }

                if (!origin || !destinationMarkers.length) {
                    if (origin && !firstFitDone) {
                        map.setView([origin.lat, origin.lng], 14);
                        firstFitDone = true;
                    }
                    latestOrigin = origin;
                    latestOrderedStops = [];
                    if (googleRouteButton) {
                        googleRouteButton.disabled = true;
                    }
                    return;
                }

                const activeStops = destinationMarkers.filter((stop) =>
                    ['pendiente', 'asignado', 'en_ruta', 'en_sitio'].includes(stop.estado_key)
                );
                const routeStopsPool = activeStops.length ? activeStops : destinationMarkers;
                const orderedStops = await window.__logisticaGetShortestStopOrder(origin, routeStopsPool);

                if (requestId !== routeRequestId) {
                    return;
                }

                latestOrigin = origin;
                latestOrderedStops = orderedStops;
                if (googleRouteButton) {
                    googleRouteButton.disabled = orderedStops.length === 0;
                }

                const routePoints = [
                    [origin.lat, origin.lng],
                    ...orderedStops.map((stop) => [stop.lat, stop.lng]),
                ];

                let routeCoordinates = routePoints;

                try {
                    const fetchedRoute = await window.__logisticaFetchOsmRoute(routePoints);
                    if (Array.isArray(fetchedRoute) && fetchedRoute.length >= 2) {
                        routeCoordinates = fetchedRoute;
                    }
                } catch (error) {
                    routeCoordinates = routePoints;
                }

                if (requestId !== routeRequestId) {
                    return;
                }

                routeLayer = L.polyline(routeCoordinates, {
                    color: '#2563eb',
                    weight: 5,
                    opacity: 0.8,
                }).addTo(map);

                if (!firstFitDone) {
                    map.fitBounds(routeLayer.getBounds(), { padding: [24, 24] });
                    firstFitDone = true;
                }
            };

            if (destinationBounds.length) {
                map.fitBounds(destinationBounds, { padding: [24, 24] });
            }

            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(async (position) => {
                    const lat = Number(position.coords.latitude);
                    const lng = Number(position.coords.longitude);

                    if (currentMarker) {
                        currentMarker.setLatLng([lat, lng]);
                    } else {
                        currentMarker = L.circleMarker([lat, lng], {
                            radius: 8,
                            color: '#065f46',
                            weight: 2,
                            fillColor: '#10b981',
                            fillOpacity: 1,
                        }).addTo(map).bindPopup('Tu ubicación actual');
                    }

                    await drawRoute({ lat, lng });

                    geoStatus.textContent = destinationMarkers.length
                        ? 'Ruta actualizada con tu ubicación'
                        : 'Ubicación actual lista';
                    geoStatus.className = 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700';
                }, () => {
                    geoStatus.textContent = 'Activa la ubicación para validar y calcular la ruta';
                    geoStatus.className = 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700';
                    if (googleRouteButton) {
                        googleRouteButton.disabled = true;
                    }
                }, {
                    enableHighAccuracy: true,
                    maximumAge: 10000,
                    timeout: 15000,
                });
            } else {
                geoStatus.textContent = 'Este dispositivo no soporta geolocalización';
                geoStatus.className = 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700';
                if (googleRouteButton) {
                    googleRouteButton.disabled = true;
                }
            }

            googleRouteButton?.addEventListener('click', () => {
                if (!latestOrigin || !latestOrderedStops.length) {
                    return;
                }

                const url = window.__logisticaBuildGoogleMapsRouteUrl(latestOrigin, latestOrderedStops);
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });
        })
        .catch(() => {
            geoStatus.textContent = 'No se pudo cargar el mapa';
            geoStatus.className = 'rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700';
            if (googleRouteButton) {
                googleRouteButton.disabled = true;
            }
            showFallback('Revisa tu conexión o vuelve a cargar la página.');
        });
});
</script>
@endpush
