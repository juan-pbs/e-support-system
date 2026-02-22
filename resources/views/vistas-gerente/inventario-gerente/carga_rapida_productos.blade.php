@extends('layouts.sidebar-navigation')

@section('content')
<div class="max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <x-boton-volver />
        <h1 class="text-2xl md:text-3xl font-bold text-center text-gray-800 flex-1">
            Carga rápida (Excel/CSV)
        </h1>
        <div class="w-24"></div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-300 shadow">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300 shadow">
            {{ session('error') }}
        </div>
    @endif

    @if(!($preview ?? false))
        {{-- ===== PASO 1: SUBIR ARCHIVO ===== --}}
        <form method="POST"
              action="{{ route('cargaRapidaProd.procesar') }}"
              enctype="multipart/form-data"
              class="bg-white rounded-xl border p-5 shadow space-y-6">
            @csrf

            <div>
                <label class="block font-medium mb-1">Archivo *</label>
                <input type="file" name="archivo" accept=".xlsx,.csv,.txt"
                       class="w-full border rounded px-3 py-2" required>
                @error('archivo')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block font-medium mb-1">Modo de carga *</label>
                <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="modo_carga" value="solo_productos" checked>
                        <span>Crear/actualizar <strong>solo productos</strong> (sin inventario)</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="modo_carga" value="con_inventario">
                        <span>Crear/actualizar productos <strong>y generar entradas</strong> (piezas/paquetes/serie)</span>
                    </label>
                </div>
            </div>

            <div class="bg-gray-50 border rounded p-4 text-sm space-y-2">
                <p class="font-semibold">Qué se importa:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Se crean/actualizan <strong>productos</strong> (descripción, Prod/Serv, Unidad, Categoría y Número de parte).</li>
                    <li>Se crean/actualizan <strong>proveedores</strong> desde la columna <em>Emisor</em> (RFC opcional).</li>
                    <li>Si eliges <em>con inventario</em>, se generan <strong>entradas</strong> usando Cantidad, Valor unitario y Tipo de control.</li>
                    <li>Los precios se normalizan a <strong>MXN</strong> con 2 decimales. Si el archivo trae más, se redondean (se indica "redondeado").</li>
                </ul>
                <p class="font-semibold mt-2">Se descarta automáticamente:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Filas completamente vacías o sin descripción.</li>
                </ul>
            </div>

            <div class="pt-2">
                <button class="px-5 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                    Generar vista previa
                </button>
            </div>
        </form>
    @else
        {{-- ===== PASO 2: PREVIEW ===== --}}
        <div
            x-data="cargaPreview({{ json_encode($items) }}, '{{ $modo_carga }}')"
            class="space-y-4"
        >
            <div class="bg-white rounded-xl border p-4 shadow">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                    <div class="p-3 rounded bg-gray-50">
                        <div class="text-2xl font-bold" x-text="stats.total"></div>
                        <div class="text-gray-600 text-sm">Filas</div>
                    </div>
                    <div class="p-3 rounded bg-green-50">
                        <div class="text-2xl font-bold" x-text="stats.aceptables"></div>
                        <div class="text-gray-600 text-sm">Nuevos</div>
                    </div>
                    <div class="p-3 rounded bg-yellow-50">
                        <div class="text-2xl font-bold" x-text="stats.duplicados"></div>
                        <div class="text-gray-600 text-sm">Duplicados</div>
                    </div>
                    <div class="p-3 rounded bg-gray-50">
                        <div class="text-2xl font-bold" x-text="stats.vacios"></div>
                        <div class="text-gray-600 text-sm">Vacíos (no mostrados)</div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 items-center">
                    <button type="button"
                            class="px-3 py-1.5 border rounded text-sm"
                            @click="marcarTodos()">
                        Marcar todos
                    </button>
                    <button type="button"
                            class="px-3 py-1.5 border rounded text-sm"
                            @click="soloAceptables()">
                        Solo nuevos (no duplicados)
                    </button>
                    <button type="button"
                            class="px-3 py-1.5 border rounded text-sm"
                            @click="mostrarTodos = !mostrarTodos"
                            x-text="mostrarTodos ? 'Ocultar desmarcados' : 'Mostrar todos'">
                    </button>
                    <span class="ml-auto text-sm text-gray-600">
                        Modo de carga:
                        <strong>
                            {{ $modo_carga === 'con_inventario' ? 'con inventario' : 'solo productos' }}
                        </strong>
                    </span>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('cargaRapidaProd.procesar') }}"
                  @submit.prevent="confirmar($event)">
                @csrf
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="modo_carga" value="{{ $modo_carga }}">
                <input type="hidden" name="payload" x-model="payload">

                <div class="bg-white rounded-xl border shadow overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-center">OK</th>
                                <th class="p-2">Descripción</th>
                                <th class="p-2">Emisor</th>
                                <th class="p-2">Prod/Serv</th>
                                <th class="p-2">Unidad</th>
                                <th class="p-2">Tipo</th>
                                <th class="p-2">Cantidad</th>
                                <th class="p-2">Valor U. (MXN)</th>
                                <th class="p-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <template x-for="(it,idx) in items" :key="idx">
                            <tr class="border-t"
                                x-show="mostrarTodos || it.include"
                                :class="it.duplicado ? 'bg-yellow-50/40' : ''">
                                <td class="p-2 text-center">
                                    <input type="checkbox" x-model="it.include">
                                </td>

                                <td class="p-2 w-[360px]">
                                    <div class="font-medium line-clamp-2" x-text="it.descripcion"></div>
                                    <div class="text-[11px] text-gray-600 mt-0.5">
                                        Cat:
                                        <span x-text="it.categoria"></span>
                                        <template x-if="it.numero_parte">
                                            <span class="ml-2 text-gray-400">
                                                SKU: <span x-text="it.numero_parte"></span>
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                <td class="p-2">
                                    <div class="text-xs" x-text="it.proveedor_nombre || '—'"></div>
                                    <div class="text-[10px] text-gray-500" x-text="it.proveedor_rfc || ''"></div>
                                </td>

                                <td class="p-2">
                                    <div class="text-xs" x-text="it.clave_prodserv || '—'"></div>
                                </td>

                                <td class="p-2">
                                    <div class="text-xs"
                                         x-text="(it.clave_unidad || '—') + ' / ' + (it.unidad || '-')"></div>
                                </td>

                                <td class="p-2">
                                    <select class="border rounded px-2 py-1 text-xs"
                                            x-model="it.tipo_control"
                                            @change="(it.tipo_control === 'SERIE') ? openSeries(idx) : null">
                                        <option value="PIEZAS">Piezas</option>
                                        <option value="PAQUETES">Paquetes</option>
                                        <option value="SERIE">Serie</option>
                                    </select>
                                </td>

                                <td class="p-2">
                                    <input type="number" min="0"
                                           class="w-20 border rounded px-2 py-1 text-xs"
                                           x-model.number="it.cantidad">
                                </td>

                                <td class="p-2">
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="0.01" min="0"
                                               class="w-28 border rounded px-2 py-1 text-xs"
                                               x-model.number="it.valor_unitario">
                                        <span class="text-[10px] text-gray-500"
                                              x-show="it.valor_redondeado">
                                            redondeado
                                        </span>
                                    </div>
                                </td>

                                <td class="p-2">
                                    <span class="text-xs px-2 py-0.5 rounded"
                                          :class="it.duplicado
                                                   ? 'bg-yellow-100 text-yellow-800'
                                                   : 'bg-green-100 text-green-800'"
                                          x-text="it.duplicado
                                                  ? ('Duplicado: ' + (it.dup_reason || 'coincide'))
                                                  : 'Nuevo'">
                                    </span>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <a href="{{ route('cargaRapidaProd.index') }}"
                       class="px-4 py-2 border rounded">
                        Cancelar
                    </a>
                    <button class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">
                        Confirmar carga
                    </button>
                </div>
            </form>

            {{-- Modal de números de serie --}}
            <div x-show="seriesModal.open"
                 style="display:none"
                 class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                <div class="bg-white w-full max-w-xl rounded-xl shadow-xl p-5"
                     @click.away="closeSeries()">
                    <h3 class="text-lg font-semibold mb-1">Números de serie</h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Captura
                        <strong x-text="seriesModal.cantidad"></strong>
                        número(s) de serie para:
                        <span class="font-medium" x-text="seriesModal.descripcion"></span>
                    </p>

                    <div class="mb-3">
                        <textarea class="w-full border rounded p-2 text-sm"
                                  rows="4"
                                  placeholder="Pega aquí una lista (uno por línea) para autocompletar"
                                  x-model="seriesModal.pasteArea"
                                  @input="pasteToSeries()"></textarea>
                        <div class="text-xs text-gray-500 mt-1">
                            Si pegas una lista, se llenan los campos de abajo.
                        </div>
                    </div>

                    <div class="max-h-60 overflow-auto border rounded p-2">
                        <template x-for="n in seriesModal.cantidad" :key="n">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-12 text-xs text-gray-500">
                                    #<span x-text="n"></span>
                                </div>
                                <input type="text"
                                       class="flex-1 border rounded px-2 py-1 text-sm"
                                       :placeholder="'Serie ' + n"
                                       x-model="seriesModal.series[n-1]">
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button class="px-4 py-2 border rounded"
                                @click="closeSeries()">
                            Cancelar
                        </button>
                        <button class="px-4 py-2 bg-blue-600 text-white rounded"
                                @click="applySeries()">
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function cargaPreview(initialItems, modo) {
            return {
                modo,
                items: (initialItems || []).map(r => ({ ...r, series: r.series || [] })),
                payload: '',
                mostrarTodos: false,

                seriesModal: {
                    open: false,
                    idx: null,
                    cantidad: 0,
                    descripcion: '',
                    series: [],
                    pasteArea: '',
                },

                get stats() {
                    const s = { total: 0, duplicados: 0, aceptables: 0, vacios: 0 };
                    this.items.forEach(it => {
                        s.total++;
                        if (it.duplicado) s.duplicados++;
                        else s.aceptables++;
                    });
                    return s;
                },

                marcarTodos() {
                    this.items.forEach(i => i.include = true);
                },
                soloAceptables() {
                    this.items.forEach(i => i.include = !i.duplicado);
                },

                openSeries(idx) {
                    const it   = this.items[idx];
                    const cant = Math.max(1, parseInt(it.cantidad || 0, 10));

                    this.seriesModal = {
                        open: true,
                        idx,
                        cantidad: cant,
                        descripcion: it.descripcion || '',
                        series: Array.from(
                            { length: cant },
                            (_, k) => (it.series && it.series[k]) ? it.series[k] : ''
                        ),
                        pasteArea: '',
                    };
                },
                pasteToSeries() {
                    const lines = (this.seriesModal.pasteArea || '')
                        .split(/\r?\n/)
                        .map(s => s.trim())
                        .filter(Boolean);

                    lines.forEach((v, i) => {
                        if (i < this.seriesModal.cantidad) {
                            this.seriesModal.series[i] = v;
                        }
                    });
                },
                closeSeries() {
                    this.seriesModal.open = false;
                },
                applySeries() {
                    const idx = this.seriesModal.idx;
                    if (idx !== null) {
                        const arr = this.seriesModal.series
                            .map(s => (s || '').trim())
                            .filter(Boolean)
                            .slice(0, this.seriesModal.cantidad);
                        this.items[idx].series = arr;
                    }
                    this.closeSeries();
                },

                confirmar(e) {
                    const selected = this.items
                        .filter(it => !!it.include)
                        .map(it => ({
                            include: true,
                            duplicado: !!it.duplicado,
                            dup_reason: it.dup_reason || null,
                            existing_id: it.existing_id || null,

                            descripcion: it.descripcion,
                            categoria: it.categoria,
                            clave_prodserv: it.clave_prodserv,
                            clave_unidad: it.clave_unidad,
                            unidad: it.unidad,

                            numero_parte: it.numero_parte,

                            proveedor_nombre: it.proveedor_nombre,
                            proveedor_rfc: it.proveedor_rfc,

                            tipo_control: it.tipo_control || 'PIEZAS',
                            cantidad: parseInt(it.cantidad || 0, 10),
                            valor_unitario: Math.round(
                                (parseFloat(it.valor_unitario || 0) + Number.EPSILON) * 100
                            ) / 100,

                            series: Array.isArray(it.series) ? it.series : [],
                        }));

                    if (selected.length === 0) {
                        alert('No hay productos seleccionados.');
                        return;
                    }

                    this.payload = JSON.stringify(selected);
                    this.$nextTick(() => e.target.submit());
                },
            }
        }
        </script>
    @endif
</div>
@endsection
