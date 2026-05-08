@include('partials.logistica.leaflet-loader')

@once
    <div id="logistica-address-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto bg-black/50 p-4">
        <div class="mx-auto mt-8 max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b px-5 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Seleccionar dirección logística</h3>
                    <p class="text-sm text-gray-500">Busca una dirección real o marca el punto directamente en el mapa.</p>
                </div>
                <button type="button" id="logistica-address-close" class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                    Cerrar
                </button>
            </div>

            <div class="max-h-[calc(100vh-13rem)] overflow-y-auto">
                <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-[360px_minmax(0,1fr)]">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Buscar dirección</label>
                        <input
                            id="logistica-address-search"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Escribe la dirección o colonia"
                        >
                        <div id="logistica-address-suggestions" class="mt-2 hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"></div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Referencia</label>
                        <textarea
                            id="logistica-address-reference"
                            rows="3"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej. portón negro, bodega al fondo, acceso por lateral"
                        ></textarea>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                        <div class="font-semibold text-gray-900">Dirección seleccionada</div>
                        <div id="logistica-address-selected" class="mt-2 min-h-[3rem] text-sm text-gray-600">
                            Aún no has seleccionado un punto.
                        </div>
                        <div id="logistica-address-coords" class="mt-2 text-xs text-gray-500"></div>
                    </div>

                    <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800">
                        La ubicación debe quedar verificada en mapa para poder usarla en logística.
                    </div>
                </div>

                <div>
                    <div class="relative z-0 isolate overflow-hidden rounded-2xl border border-gray-200 bg-slate-100">
                        <div id="logistica-address-map" class="relative z-0 isolate h-[420px] w-full overflow-hidden bg-slate-100"></div>
                    </div>
                </div>
            </div>
            </div>

            <div class="flex flex-col gap-2 border-t px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" id="logistica-address-cancel" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                    Cancelar
                </button>
                <button type="button" id="logistica-address-confirm" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Usar dirección
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                if (window.LogisticaAddressPicker) return;

                const searchUrl = @js(route('logistica.direcciones.search'));
                const reverseUrl = @js(route('logistica.direcciones.reverse'));

                const DEFAULT_CENTER = [20.588793, -100.389888];
                let modal = null;
                let closeBtn = null;
                let cancelBtn = null;
                let confirmBtn = null;
                let searchInput = null;
                let suggestionsEl = null;
                let refInput = null;
                let selectedEl = null;
                let coordsEl = null;
                let mapEl = null;
                let map;
                let marker;
                let onConfirm = null;
                let currentData = null;
                let reverseLookupId = 0;
                let searchTimer = null;
                let activeSearchController = null;
                let eventsBound = false;

                function resolveElements() {
                    modal = document.getElementById('logistica-address-modal');
                    closeBtn = document.getElementById('logistica-address-close');
                    cancelBtn = document.getElementById('logistica-address-cancel');
                    confirmBtn = document.getElementById('logistica-address-confirm');
                    searchInput = document.getElementById('logistica-address-search');
                    suggestionsEl = document.getElementById('logistica-address-suggestions');
                    refInput = document.getElementById('logistica-address-reference');
                    selectedEl = document.getElementById('logistica-address-selected');
                    coordsEl = document.getElementById('logistica-address-coords');
                    mapEl = document.getElementById('logistica-address-map');

                    return !!(modal && closeBtn && cancelBtn && confirmBtn && searchInput && suggestionsEl && refInput && selectedEl && coordsEl && mapEl);
                }

                function updateSummary() {
                    if (!selectedEl || !coordsEl) return;

                    selectedEl.textContent = currentData?.direccion_formateada || 'Aún no has seleccionado un punto.';
                    coordsEl.textContent = currentData?.latitud && currentData?.longitud
                        ? `${Number(currentData.latitud).toFixed(6)}, ${Number(currentData.longitud).toFixed(6)}`
                        : '';
                }

                function clearSuggestions() {
                    if (!suggestionsEl) return;

                    suggestionsEl.innerHTML = '';
                    suggestionsEl.classList.add('hidden');
                }

                function placeIdFromResult(result) {
                    if (result?.osm_type && result?.osm_id) {
                        return `osm:${result.osm_type}:${result.osm_id}`;
                    }

                    if (result?.place_id) {
                        return `osm:${result.place_id}`;
                    }

                    return result?.display_name
                        ? `search:${result.display_name}`
                        : `coords:${result?.lat ?? ''},${result?.lon ?? ''}`;
                }

                function positionMarker(lat, lng, zoom = 16) {
                    if (!map || !marker) return;

                    marker.setLatLng([lat, lng]);
                    marker.setOpacity(1);
                    map.setView([lat, lng], zoom);
                }

                function renderSuggestions(results) {
                    suggestionsEl.innerHTML = '';

                    if (!Array.isArray(results) || !results.length) {
                        suggestionsEl.classList.add('hidden');
                        return;
                    }

                    results.forEach((result) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full border-b border-gray-100 px-4 py-3 text-left text-sm text-gray-700 transition hover:bg-blue-50 last:border-b-0';

                        const title = document.createElement('div');
                        title.className = 'font-medium text-gray-900';
                        title.textContent = result.display_name || 'Dirección';

                        const meta = document.createElement('div');
                        meta.className = 'mt-1 text-xs text-gray-500';
                        meta.textContent = `${Number(result.lat).toFixed(5)}, ${Number(result.lon).toFixed(5)}`;

                        button.appendChild(title);
                        button.appendChild(meta);
                        button.addEventListener('click', () => applySearchResult(result));
                        suggestionsEl.appendChild(button);
                    });

                    suggestionsEl.classList.remove('hidden');
                }

                function applySearchResult(result) {
                    const lat = Number(result.lat);
                    const lng = Number(result.lon);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                    currentData = {
                        direccion_formateada: result.display_name || searchInput.value.trim(),
                        place_id: placeIdFromResult(result),
                        latitud: lat,
                        longitud: lng,
                        referencia: refInput.value || '',
                        verificada_en_mapa: true,
                        metodo_verificacion: 'autocomplete',
                    };

                    searchInput.value = currentData.direccion_formateada;
                    positionMarker(lat, lng, 16);
                    clearSuggestions();
                    updateSummary();
                }

                function openModal() {
                    if (!modal) return;

                    modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeModal() {
                    if (!modal) return;

                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                    clearSuggestions();
                }

                function ensureMapReady(callback) {
                    if (!mapEl) {
                        callback();
                        return;
                    }

                    window.__logisticaEnsureLeaflet()
                        .then((L) => {
                            if (!map) {
                                map = L.map(mapEl, {
                                    center: DEFAULT_CENTER,
                                    zoom: 12,
                                    scrollWheelZoom: true,
                                });

                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 19,
                                    attribution: '&copy; OpenStreetMap contributors',
                                }).addTo(map);

                                marker = L.marker(DEFAULT_CENTER, {
                                    draggable: true,
                                    opacity: 0,
                                }).addTo(map);

                                marker.on('dragend', (event) => {
                                    const coords = event.target.getLatLng();
                                    applyCoordinates(coords.lat, coords.lng, 'mapa');
                                });

                                map.on('click', (event) => {
                                    applyCoordinates(event.latlng.lat, event.latlng.lng, 'mapa');
                                });
                            }

                            requestAnimationFrame(() => {
                                map.invalidateSize();
                            });
                            setTimeout(() => map.invalidateSize(), 180);
                            callback();
                        })
                        .catch(() => {
                            selectedEl.textContent = 'No se pudo cargar el mapa. Aún puedes buscar una dirección real y seleccionarla desde la lista.';
                            coordsEl.textContent = '';
                            callback();
                        });
                }

                async function lookupAddress(query) {
                    if (activeSearchController) {
                        activeSearchController.abort();
                    }

                    activeSearchController = new AbortController();
                    const url = `${searchUrl}?${new URLSearchParams({ q: query }).toString()}`;
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        signal: activeSearchController.signal,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo consultar el servicio de direcciones.');
                    }

                    return response.json();
                }

                async function reverseGeocode(lat, lng) {
                    const currentLookup = ++reverseLookupId;
                    const url = `${reverseUrl}?${new URLSearchParams({
                        lat: String(lat),
                        lng: String(lng),
                    }).toString()}`;
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo convertir el punto a una dirección.');
                    }

                    const result = await response.json();
                    if (currentLookup !== reverseLookupId) return null;

                    return result;
                }

                function applyCoordinates(lat, lng, method) {
                    const numericLat = Number(lat);
                    const numericLng = Number(lng);
                    if (Number.isNaN(numericLat) || Number.isNaN(numericLng)) return;

                    selectedEl.textContent = 'Buscando dirección para el punto seleccionado...';
                    coordsEl.textContent = `${numericLat.toFixed(6)}, ${numericLng.toFixed(6)}`;
                    positionMarker(numericLat, numericLng, 16);

                    reverseGeocode(numericLat, numericLng)
                        .then((result) => {
                            if (!result) return;

                            currentData = {
                                direccion_formateada: result.display_name || `${numericLat.toFixed(6)}, ${numericLng.toFixed(6)}`,
                                place_id: placeIdFromResult(result),
                                latitud: numericLat,
                                longitud: numericLng,
                                referencia: refInput.value || '',
                                verificada_en_mapa: true,
                                metodo_verificacion: method,
                            };

                            searchInput.value = currentData.direccion_formateada;
                            updateSummary();
                        })
                        .catch(() => {
                            currentData = {
                                direccion_formateada: `${numericLat.toFixed(6)}, ${numericLng.toFixed(6)}`,
                                place_id: `coords:${numericLat.toFixed(6)},${numericLng.toFixed(6)}`,
                                latitud: numericLat,
                                longitud: numericLng,
                                referencia: refInput.value || '',
                                verificada_en_mapa: true,
                                metodo_verificacion: method,
                            };

                            searchInput.value = currentData.direccion_formateada;
                            updateSummary();
                        });
                }

                function seedData(initial) {
                    currentData = initial ? { ...initial } : null;
                    if (!searchInput || !refInput) return;

                    searchInput.value = currentData?.direccion_formateada || '';
                    refInput.value = currentData?.referencia || '';
                    clearSuggestions();
                    updateSummary();

                    if (currentData?.latitud && currentData?.longitud && map && marker) {
                        positionMarker(Number(currentData.latitud), Number(currentData.longitud), 16);
                    } else if (map) {
                        marker.setOpacity(0);
                        map.setView(DEFAULT_CENTER, 12);
                    }
                }

                function bindEvents() {
                    if (eventsBound) {
                        return true;
                    }

                    if (!resolveElements()) {
                        return false;
                    }

                    [closeBtn, cancelBtn].forEach(btn => btn.addEventListener('click', closeModal));
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) closeModal();
                    });

                    confirmBtn.addEventListener('click', () => {
                        if (!currentData?.direccion_formateada || !currentData?.latitud || !currentData?.longitud) {
                            alert('Selecciona una dirección válida en el mapa.');
                            return;
                        }

                        currentData.referencia = refInput.value || '';
                        if (typeof onConfirm === 'function') {
                            onConfirm({ ...currentData });
                        }
                        closeModal();
                    });

                    searchInput.addEventListener('input', () => {
                        clearTimeout(searchTimer);
                        const query = searchInput.value.trim();

                        if (currentData && query !== currentData.direccion_formateada) {
                            currentData = null;
                            updateSummary();
                        }

                        if (query.length < 4) {
                            clearSuggestions();
                            return;
                        }

                        searchTimer = setTimeout(() => {
                            lookupAddress(query)
                                .then((results) => renderSuggestions(results))
                                .catch((error) => {
                                    if (error?.name === 'AbortError') return;
                                    clearSuggestions();
                                });
                        }, 320);
                    });

                    searchInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            clearSuggestions();
                        }
                    });

                    eventsBound = true;

                    return true;
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindEvents, { once: true });
                } else {
                    bindEvents();
                }

                window.LogisticaAddressPicker = {
                    open(initialData, callback) {
                        if (!bindEvents()) {
                            console.warn('No se pudo inicializar el selector de direcciones logísticas.');
                            return;
                        }

                        onConfirm = callback;
                        openModal();
                        ensureMapReady(() => seedData(initialData));
                    },
                };
            })();
        </script>
    @endpush
@endonce
