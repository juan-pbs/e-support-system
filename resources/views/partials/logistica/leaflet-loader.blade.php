@once
    @push('scripts')
        <script>
            (function () {
                if (window.__logisticaEnsureLeaflet) {
                    return;
                }

                window.__logisticaEnsureLeaflet = function () {
                    if (window.L) {
                        return Promise.resolve(window.L);
                    }

                    if (window.__logisticaLeafletPromise) {
                        return window.__logisticaLeafletPromise;
                    }

                    window.__logisticaLeafletPromise = new Promise((resolve, reject) => {
                        if (!document.querySelector('link[data-logistica-leaflet-css]')) {
                            const css = document.createElement('link');
                            css.rel = 'stylesheet';
                            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                            css.setAttribute('data-logistica-leaflet-css', '1');
                            document.head.appendChild(css);
                        }

                        const existingScript = document.querySelector('script[data-logistica-leaflet-js]');
                        if (existingScript) {
                            existingScript.addEventListener('load', () => resolve(window.L));
                            existingScript.addEventListener('error', () => reject(new Error('No se pudo cargar Leaflet.')));
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        script.async = true;
                        script.defer = true;
                        script.setAttribute('data-logistica-leaflet-js', '1');
                        script.onload = () => {
                            if (window.L) {
                                resolve(window.L);
                            } else {
                                reject(new Error('Leaflet se cargó sin exponer window.L.'));
                            }
                        };
                        script.onerror = () => reject(new Error('No se pudo cargar Leaflet.'));
                        document.head.appendChild(script);
                    });

                    return window.__logisticaLeafletPromise;
                };

                window.__logisticaBuildOsmUrl = function (lat, lng, zoom = 16) {
                    return `https://www.openstreetmap.org/?mlat=${encodeURIComponent(lat)}&mlon=${encodeURIComponent(lng)}#map=${encodeURIComponent(zoom)}/${encodeURIComponent(lat)}/${encodeURIComponent(lng)}`;
                };

                window.__logisticaDistanceMeters = function (from, to) {
                    const earthRadius = 6371000;
                    const lat1 = (from.lat * Math.PI) / 180;
                    const lat2 = (to.lat * Math.PI) / 180;
                    const deltaLat = ((to.lat - from.lat) * Math.PI) / 180;
                    const deltaLng = ((to.lng - from.lng) * Math.PI) / 180;

                    const a = Math.sin(deltaLat / 2) ** 2
                        + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLng / 2) ** 2;

                    return earthRadius * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
                };

                window.__logisticaSortStopsFromOrigin = function (origin, stops) {
                    const remaining = Array.isArray(stops) ? [...stops] : [];
                    const ordered = [];
                    let current = origin;

                    while (remaining.length) {
                        let nearestIndex = 0;
                        let nearestDistance = window.__logisticaDistanceMeters(current, remaining[0]);

                        for (let index = 1; index < remaining.length; index += 1) {
                            const distance = window.__logisticaDistanceMeters(current, remaining[index]);
                            if (distance < nearestDistance) {
                                nearestIndex = index;
                                nearestDistance = distance;
                            }
                        }

                        const [nearest] = remaining.splice(nearestIndex, 1);
                        ordered.push(nearest);
                        current = nearest;
                    }

                    return ordered;
                };

                window.__logisticaFetchOsmRoute = async function (points) {
                    if (!Array.isArray(points) || points.length < 2) {
                        return points;
                    }

                    const coordinates = points
                        .map(([lat, lng]) => `${encodeURIComponent(lng)},${encodeURIComponent(lat)}`)
                        .join(';');

                    const url = `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson&steps=false`;
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo obtener la ruta.');
                    }

                    const data = await response.json();
                    const geometry = data?.routes?.[0]?.geometry?.coordinates ?? [];

                    if (!geometry.length) {
                        throw new Error('La ruta no devolvió coordenadas.');
                    }

                    return geometry.map(([lng, lat]) => [lat, lng]);
                };

                window.__logisticaFetchOsmTravelMatrix = async function (points) {
                    if (!Array.isArray(points) || points.length < 2) {
                        return [];
                    }

                    const coordinates = points
                        .map(([lat, lng]) => `${encodeURIComponent(lng)},${encodeURIComponent(lat)}`)
                        .join(';');

                    const url = `https://router.project-osrm.org/table/v1/driving/${coordinates}?annotations=duration`;
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo obtener la matriz de tiempos.');
                    }

                    const data = await response.json();
                    const durations = data?.durations ?? [];

                    if (!Array.isArray(durations) || !durations.length) {
                        throw new Error('La matriz de tiempos no devolvió datos válidos.');
                    }

                    return durations;
                };

                window.__logisticaGreedyOrderByMatrix = function (matrix, stops) {
                    const remaining = stops.map((_, index) => index + 1);
                    const ordered = [];
                    let currentIndex = 0;

                    while (remaining.length) {
                        let bestPos = 0;
                        let bestDistance = Number.POSITIVE_INFINITY;

                        remaining.forEach((matrixIndex, position) => {
                            const candidate = Number(matrix?.[currentIndex]?.[matrixIndex] ?? Number.POSITIVE_INFINITY);
                            if (candidate < bestDistance) {
                                bestDistance = candidate;
                                bestPos = position;
                            }
                        });

                        const [nextMatrixIndex] = remaining.splice(bestPos, 1);
                        ordered.push(stops[nextMatrixIndex - 1]);
                        currentIndex = nextMatrixIndex;
                    }

                    return ordered;
                };

                window.__logisticaSolveShortestOpenPath = function (matrix, stops) {
                    const n = stops.length;
                    if (n <= 1) {
                        return stops;
                    }

                    const size = 1 << n;
                    const dp = Array.from({ length: size }, () => Array(n).fill(Number.POSITIVE_INFINITY));
                    const parent = Array.from({ length: size }, () => Array(n).fill(-1));

                    for (let end = 0; end < n; end += 1) {
                        dp[1 << end][end] = Number(matrix?.[0]?.[end + 1] ?? Number.POSITIVE_INFINITY);
                    }

                    for (let mask = 1; mask < size; mask += 1) {
                        for (let end = 0; end < n; end += 1) {
                            if ((mask & (1 << end)) === 0) {
                                continue;
                            }

                            const previousMask = mask ^ (1 << end);
                            if (previousMask === 0) {
                                continue;
                            }

                            for (let previousEnd = 0; previousEnd < n; previousEnd += 1) {
                                if ((previousMask & (1 << previousEnd)) === 0) {
                                    continue;
                                }

                                const candidate = dp[previousMask][previousEnd]
                                    + Number(matrix?.[previousEnd + 1]?.[end + 1] ?? Number.POSITIVE_INFINITY);

                                if (candidate < dp[mask][end]) {
                                    dp[mask][end] = candidate;
                                    parent[mask][end] = previousEnd;
                                }
                            }
                        }
                    }

                    const fullMask = size - 1;
                    let bestEnd = 0;
                    let bestCost = dp[fullMask][0];

                    for (let end = 1; end < n; end += 1) {
                        if (dp[fullMask][end] < bestCost) {
                            bestCost = dp[fullMask][end];
                            bestEnd = end;
                        }
                    }

                    const order = [];
                    let mask = fullMask;
                    let current = bestEnd;

                    while (current !== -1) {
                        order.push(current);
                        const next = parent[mask][current];
                        mask ^= 1 << current;
                        current = next;
                    }

                    return order.reverse().map((stopIndex) => stops[stopIndex]);
                };

                window.__logisticaGetShortestStopOrder = async function (origin, stops) {
                    const validStops = Array.isArray(stops)
                        ? stops.filter((stop) => Number.isFinite(stop?.lat) && Number.isFinite(stop?.lng))
                        : [];

                    if (!origin || !validStops.length) {
                        return [];
                    }

                    const points = [
                        [origin.lat, origin.lng],
                        ...validStops.map((stop) => [stop.lat, stop.lng]),
                    ];

                    try {
                        const matrix = await window.__logisticaFetchOsmTravelMatrix(points);
                        if (validStops.length <= 8) {
                            return window.__logisticaSolveShortestOpenPath(matrix, validStops);
                        }

                        return window.__logisticaGreedyOrderByMatrix(matrix, validStops);
                    } catch (error) {
                        return window.__logisticaSortStopsFromOrigin(origin, validStops);
                    }
                };

                window.__logisticaBuildGoogleMapsRouteUrl = function (origin, stops) {
                    const validStops = Array.isArray(stops)
                        ? stops.filter((stop) => Number.isFinite(stop?.lat) && Number.isFinite(stop?.lng))
                        : [];

                    if (!origin || !validStops.length) {
                        return null;
                    }

                    const cappedStops = validStops.slice(0, 9);
                    const destination = cappedStops[cappedStops.length - 1];
                    const waypoints = cappedStops
                        .slice(0, -1)
                        .map((stop) => `${stop.lat},${stop.lng}`)
                        .join('|');

                    const url = new URL('https://www.google.com/maps/dir/');
                    url.searchParams.set('api', '1');
                    url.searchParams.set('origin', `${origin.lat},${origin.lng}`);
                    url.searchParams.set('destination', `${destination.lat},${destination.lng}`);
                    url.searchParams.set('travelmode', 'driving');
                    if (waypoints) {
                        url.searchParams.set('waypoints', waypoints);
                    }

                    return url.toString();
                };
            })();
        </script>
    @endpush
@endonce
