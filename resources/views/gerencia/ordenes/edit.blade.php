@extends('layouts.sidebar-navigation')

@section('title', 'Editar Orden de Servicio')

@section('content')
@php
use Illuminate\Support\Str;

// ===== helper para detectar control por N/S =====
$hasSerialDetect = function ($tipo) {
    $t = Str::of((string)$tipo)->lower()->__toString();
    if ($t === 'piezas') return false; // ✨ nunca considerar "piezas" como serial
    $compact = str_replace([' ', '.', '-', '_'], '', $t);
    if (Str::contains($compact, ['serie','serial','numerodeserie'])) return true;
    return preg_match('/\bns\b|n\/s/i', (string)$tipo) === 1;
};

// Prefill de rutas/valores
$saveUrl = route('ordenes.update', ['id' => $orden->id_orden_servicio]);
$pdfUrl  = route('ordenes.pdf', ['id' => $orden->id_orden_servicio]);

$costoServicioPrefill = old('precio', (float)($orden->precio ?? 0));
$costoOperativoPrefill = old('costo_operativo', (float)($orden->costo_operativo ?? 0));
$descripcionServicioPrefill = old('descripcion_servicio', (string)($orden->descripcion_servicio ?? ''));
$precioEscritoPrefill = old('precio_escrito', $orden->precio_escrito ?? '');
$precioEscritoAutoPrefill = old('precio_escrito') === null && blank($orden->precio_escrito ?? null);

// ===== lista clientes para AUTOCOMPLETE =====
$clientesSearchList = collect($clientes ?? [])->map(function($c){
    $label = trim(($c->nombre ?? '').' '.(($c->nombre_empresa ?? '') ? '— '.$c->nombre_empresa : ''));
    return [
        'clave_cliente' => (string) $c->clave_cliente,
        'label' => $label,
        'ubicacion' => (string) ($c->ubicacion ?? ''),
    ];
})->values();
@endphp

<style>[x-cloak]{display:none!important}</style>

<noscript>
    <div class="max-w-7xl mx-auto mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3">
        Esta pantalla usa JavaScript (Alpine) para previsualizar y guardar. Activa JavaScript para evitar fallos.
    </div>
</noscript>

<div class="max-w-7xl mx-auto"
     x-data="formOrdenServicio(
        @js($productosPrefill ?? []),
        @js(old('moneda', $orden->moneda ?? 'MXN')),
        @js(old('id_cliente', $orden->id_cliente)),
        @js(old('tipo_orden', $orden->tipo_orden ?? 'servicio_simple')),
        @js((bool) old('sin_tecnico', ((string)($orden->tipo_orden ?? '') === 'compra'))),
        @js($clientesSearchList)
     )"
     x-init="init()">

    {{-- Encabezado --}}
    <div class="relative mb-10 text-center mx-auto">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Editar Orden de Servicio</h1>

        <div class="flex items-center justify-between mb-6">
            <x-boton-volver />
        </div>
    </div>

    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3">
        Editando la orden <strong>#{{ $orden->folio ?? $orden->id_orden_servicio }}</strong>
    </div>

    @isset($cotizacion)
        <div class="mb-5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-800 px-4 py-3">
            Orden generada a partir de la cotización
            <strong>#{{ $cotizacion->folio ?? $cotizacion->id_cotizacion }}</strong>
        </div>
    @endisset

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="ordenForm"
          action="{{ $saveUrl }}"
          method="POST"
          enctype="multipart/form-data"
          @submit.prevent>
        @csrf
        @method('PUT')
        @isset($cotizacion)
            <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id_cotizacion }}">
            <input type="hidden" name="estado_cotizacion" value="{{ old('estado_cotizacion','Procesada') }}">
        @endisset

        {{-- ✅ Token para reservas de N/S durante la captura --}}
        <input type="hidden" name="serial_token" :value="serialToken">
        <input type="hidden" name="orden_id_context" value="{{ $orden->id_orden_servicio ?? $orden->getKey() }}">

        {{-- Datos calculados --}}
        <input type="hidden" name="tasa_cambio" :value="usdToMxn">
        <input type="hidden" name="tipo_cambio" :value="usdToMxn">
        <input type="hidden" name="impuestos" :value="round2(totalImpuestos)">
        <input type="hidden" name="total_orden" :value="round2(totalOrden)">
        <input type="hidden" name="autorizado_por" value="{{ auth()->id() }}">

        {{-- ✅ ANTICIPO (datos para backend) --}}
        <input type="hidden" name="anticipo_calculado" :value="round2(anticipoCalculado)">
        <input type="hidden" name="saldo_pendiente" :value="round2(saldoPendiente)">
        <input type="hidden" name="anticipo_calculado_mxn" :value="round2(anticipoCalculadoMXN())">
        <input type="hidden" name="saldo_pendiente_mxn" :value="round2(saldoPendienteMXN())">

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800">Datos principales</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- CLIENTE --}}
                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>

                    @isset($cotizacion)
                        <input type="text" class="w-full rounded-lg border-gray-300 bg-gray-100"
                               value="{{ $cotizacion->cliente->nombre }} {{ $cotizacion->cliente->nombre_empresa ? '— '.$cotizacion->cliente->nombre_empresa : '' }}" disabled>
                        <input type="hidden" name="id_cliente" value="{{ $cotizacion->cliente->clave_cliente }}">
                    @else
                        <div class="relative">
                            <input type="text"
                                   class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Buscar cliente por nombre o empresa..."
                                   x-model="clienteSearch"
                                   @focus="showClienteList = true; filterClientes()"
                                   @input="showClienteList = true; filterClientes()"
                                   @keydown.escape="showClienteList=false">

                            <input type="hidden" name="id_cliente" x-model="idCliente" required>

                            <div x-show="showClienteList"
                                 x-cloak
                                 @click.outside="showClienteList=false"
                                 class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="c in clientesFiltrados" :key="c.clave_cliente">
                                    <button type="button"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-50"
                                            @click="selectCliente(c)">
                                        <div class="text-sm font-medium text-gray-900" x-text="c.label"></div>
                                        <div class="text-xs text-gray-500" x-text="c.ubicacion ? ('Ubicación: ' + c.ubicacion) : 'Ubicación: —'"></div>
                                    </button>
                                </template>

                                <div x-show="clientesFiltrados.length === 0"
                                     class="px-3 py-3 text-sm text-gray-500">
                                    Sin resultados.
                                </div>
                            </div>

                            <button type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    x-show="clienteSearch"
                                    x-cloak
                                    @click="clearCliente()">
                                ✕
                            </button>
                        </div>
                    @endisset

                    <p class="text-xs text-gray-500 mt-1">
                        Ubicación: <span class="font-medium" x-text="ubicacionCliente || '—'"></span>
                    </p>
                </div>

                {{-- Tipo de orden --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de orden</label>
                    @php
                        $tiposOrdenOpts = $tiposOrden ?? ['compra','servicio_simple','servicio_proyecto'];
                        $labels = [
                            'compra' => 'Compra',
                            'servicio_simple' => 'Servicio simple',
                            'servicio_proyecto' => 'Servicio proyecto',
                        ];
                    @endphp
                    <select name="tipo_orden" x-model="tipoOrden" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                        @foreach($tiposOrdenOpts as $t)
                            <option value="{{ $t }}" @selected(old('tipo_orden', $orden->tipo_orden ?? 'servicio_simple')===$t)>{{ $labels[$t] ?? ucfirst(str_replace('_',' ', $t)) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Prioridad --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                    <select name="prioridad" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                        @foreach(($prioridades ?? ['Baja','Media','Alta','Urgente']) as $p)
                            <option value="{{ $p }}" @selected(old('prioridad', $orden->prioridad ?? 'Baja')===$p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Técnicos --}}
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Técnicos</label>

                    <label class="inline-flex items-center gap-2 text-sm mb-2">
                        <input type="checkbox"
                               id="chkSinTecnico"
                               name="sin_tecnico"
                               value="1"
                               x-model="sinTecnico"
                               @change="if(sinTecnico) clearTecnicos()"
                               class="rounded border-gray-300">
                        <span>No requiere técnico (es una compra)</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Técnicos asignados (uno o varios)</label>
                            @php $oldTec = collect(old('tecnicos_ids', ($orden->tecnicos?->pluck('id')->toArray() ?? [])))->map(fn($v)=>(int)$v); @endphp
                            <select name="tecnicos_ids[]" multiple size="4"
                                    :disabled="sinTecnico"
                                    class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                                    :class="sinTecnico ? 'bg-gray-100 opacity-60 cursor-not-allowed' : ''">
                                @foreach(($tecnicos ?? []) as $t)
                                    <option value="{{ $t->id }}" @selected($oldTec->contains($t->id))>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1" x-show="!sinTecnico">Tip: Ctrl/⌘ para seleccionar varios.</p>
                            <p class="text-xs text-gray-500 mt-1" x-show="sinTecnico">Se omitirá la asignación de técnicos.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Técnico principal (opcional)</label>
                            <select name="id_tecnico"
                                    :disabled="sinTecnico"
                                    class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                                    :class="sinTecnico ? 'bg-gray-100 opacity-60 cursor-not-allowed' : ''">
                                <option value="">— No requiere técnico —</option>
                                @foreach(($tecnicos ?? []) as $t)
                                    <option value="{{ $t->id }}" @selected(old('id_tecnico', $orden->id_tecnico)==$t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Se usa para compatibilidad con vistas antiguas.</p>
                        </div>
                    </div>
                </div>

                {{-- Estado --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <input type="text" name="estado" value="{{ old('estado', $orden->estado ?? 'Pendiente') }}" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                </div>

                {{-- Moneda --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Moneda</label>
                        <div class="text-[11px] text-gray-500" x-show="usdToMxn && usdToMxn > 0">
                            <span>
                                1 MXN =
                                <span x-text="(1 / usdToMxn).toFixed(4)"></span>
                                USD
                            </span>
                            <span class="mx-1">•</span>
                            <span>
                                1 USD =
                                <span x-text="usdToMxn.toFixed(4)"></span>
                                MXN
                            </span>
                        </div>
                    </div>
                    <select name="moneda"
                            x-model="moneda"
                            @change="onChangeMoneda()"
                            class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
                        <option value="MXN">MXN</option>
                        <option value="USD">USD</option>
                    </select>
                </div>

                {{-- Tipo de pago --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de pago</label>
                    <select name="tipo_pago" x-model="tipoPago" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                        <option value="efectivo" @selected(old('tipo_pago')==='efectivo')>Efectivo</option>
                        <option value="transferencia" @selected(old('tipo_pago')==='transferencia')>Transferencia</option>
                        <option value="tarjeta" @selected(old('tipo_pago')==='tarjeta')>Tarjeta</option>
                        <option value="credito_cliente" @selected(old('tipo_pago')==='credito_cliente')>Crédito cliente</option>
                    </select>
                </div>

                {{-- Crédito --}}
                <div class="md:col-span-3" x-show="tipoPago === 'credito_cliente'">
                    <div class="rounded-xl border p-4"
                         :class="{
                           'border-emerald-200 bg-emerald-50': credito.exists && !creditoInsuf && !credito.loading && !credito.expired,
                           'border-rose-200 bg-rose-50': credito.exists && !credito.loading && credito.expired,
                           'border-amber-200 bg-amber-50': credito.exists && creditoInsuf && !credito.loading && !credito.expired,
                           'border-gray-200 bg-gray-50': !idCliente || credito.loading || !credito.exists
                         }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-gray-800">Crédito del cliente</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full"
                                          :class="{
                                            'bg-rose-100 text-rose-700': credito.expired || ( (credito.estatus||'').toLowerCase()==='vencido' ),
                                            'bg-emerald-100 text-emerald-700': !credito.expired && ( (credito.estatus||'').toLowerCase()==='activo' ),
                                            'bg-amber-100 text-amber-700': !credito.expired && ( (credito.estatus||'').toLowerCase()!=='activo' )
                                          }"
                                          x-text="credito.expired ? 'vencido' : (credito.estatus ? credito.estatus : '—')"></span>
                                </div>

                                <template x-if="!idCliente">
                                    <p class="text-sm text-gray-600">Selecciona un cliente para consultar su crédito.</p>
                                </template>

                                <template x-if="idCliente && credito.loading">
                                    <p class="text-sm text-gray-600">Consultando crédito…</p>
                                </template>

                                <template x-if="idCliente && !credito.loading && !credito.exists">
                                    <p class="text-sm text-gray-600">Este cliente no tiene línea de crédito asignada.</p>
                                </template>

                                <template x-if="idCliente && credito.exists && !credito.loading && !credito.expired">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <div class="text-gray-500">Máximo</div>
                                            <div class="font-semibold" x-text="formatCurrencyMXN(credito.monto_maximo || 0)"></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-500">Usado</div>
                                            <div class="font-semibold" x-text="formatCurrencyMXN(credito.monto_usado || 0)"></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-500">Disponible</div>
                                            <div class="font-semibold"
                                                 :class="(credito.disponible||0) > 0 ? 'text-emerald-700' : 'text-red-600'"
                                                 x-text="formatCurrencyMXN(credito.disponible || 0)"></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-500">A cargar (MXN)</div>
                                            <div class="font-semibold"
                                                 :class="creditoInsuf ? 'text-red-600' : 'text-gray-900'"
                                                 x-text="formatCurrencyMXN(importeParaCreditoMXN())"></div>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="idCliente && credito.exists && !credito.loading && !credito.expired && creditoInsuf">
                                    <p class="mt-2 text-sm text-amber-700">
                                        El crédito disponible no cubre el total de la orden (saldo pendiente). Ajusta el total, el anticipo o elige otro método de pago.
                                    </p>
                                </template>
                            </div>

                            <div class="flex-shrink-0">
                                <button type="button"
                                        class="px-3 py-1.5 rounded-md border text-sm"
                                        :class="idCliente ? 'hover:bg-gray-50' : 'opacity-50 cursor-not-allowed'"
                                        :disabled="!idCliente"
                                        @click="loadCredito()">
                                    Reconsultar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Costo del servicio --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Costo del servicio</label>
                    <input type="number" step="0.01" name="precio" x-model.number="costoServicio" value="{{ $costoServicioPrefill }}" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" @input="calc()">
                </div>

                {{-- Costo operativo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Costo operativo / envío</label>
                    <input type="number" step="0.01" name="costo_operativo" x-model.number="costoOperativo" value="{{ $costoOperativoPrefill }}" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" @input="calc()">
                </div>
            </div>

            {{-- Servicio --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Servicio (nombre o resumen)</label>
                <textarea name="servicio" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="Ej. Mantenimiento preventivo a sistema X">{{ old('servicio', $orden->servicio ?? '') }}</textarea>
            </div>

            {{-- Descripción específica --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción específica del servicio</label>
                <textarea name="descripcion_servicio" rows="4" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="Alcance, actividades, condiciones técnicas, etc.">{{ old('descripcion_servicio', $descripcionServicioPrefill) }}</textarea>
            </div>

            {{-- Descripción general --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción general</label>
                <textarea name="descripcion" rows="3" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="Notas generales de la orden">{{ old('descripcion', $orden->descripcion ?? '') }}</textarea>
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Notas internas (no se muestran en el PDF)
            </label>
            <textarea name="condiciones_generales" rows="3"
                      class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                      placeholder="Notas internas para el equipo">{{ old('condiciones_generales', $orden->condiciones_generales ?? '') }}</textarea>
        </div>

        {{-- Productos --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 mt-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Materiales / Productos</h2>
                <div class="flex gap-2">
                    <button type="button"
                            @click="productModal = true; $nextTick(() => { if (typeof annotateStockOnCatalog==='function') annotateStockOnCatalog(); })"
                            class="px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white">
                        Agregar producto
                    </button>
                    <button type="button" @click="clearProductos()" class="px-3 py-1.5 rounded-md border text-gray-700 hover:bg-gray-50">
                        Limpiar
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr class="text-xs font-semibold uppercase text-gray-600">
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Descripción</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">Precio</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                            <th class="px-3 py-2 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(p, idx) in productos" :key="idx">
                            <tr class="border-t text-sm align-top"
                                :class="
                                  p.stock_max === 0
                                    ? 'bg-rose-50'
                                    : (Number.isFinite(p.stock_max) && p.stock_max > 0 ? 'bg-emerald-50' : '')
                                ">
                                <td class="px-3 py-2">
                                    <input type="text" class="w-36 rounded-lg border-gray-300"
                                           :name="`productos[${idx}][codigo_producto]`"
                                           x-model="p.codigo_producto" readonly>
                                </td>

                                <td class="px-3 py-2">
                                    <div class="w-72 md:w-96">
                                        <div class="text-sm font-medium text-gray-900 truncate" x-text="p.nombre_producto || p.descripcion || 'Producto'"></div>
                                        <div class="text-xs text-gray-600 mt-0.5 whitespace-pre-line"
                                             x-text="(p.descripcion ? String(p.descripcion).replace(/\s*NS:\s*[\s\S]*$/mi, '').trim() : '') || '—'"></div>
                                        <div x-show="p.has_serial" x-cloak class="mt-1 text-[11px] text-gray-600 whitespace-pre-line">
                                            <template x-if="(p.ns_asignados || []).length">
                                                <div><span class="font-semibold">N/S:</span> <span x-text="(p.ns_asignados || []).join(', ')"></span></div>
                                            </template>
                                            <template x-if="!(p.ns_asignados || []).length">
                                                <div><span class="font-semibold">N/S:</span> —</div>
                                            </template>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2">
                                            <span x-show="Number.isFinite(p.stock_max)" class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px]">
                                              Stock: <b class="ml-1" x-text="p.stock_max"></b>
                                            </span>
                                            <span x-show="p.stock_max===0" class="inline-flex px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[11px] font-semibold">SIN STOCK</span>
                                            <span x-show="Number.isFinite(p.stock_max) && p.stock_max>0" class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold">EN STOCK</span>
                                        </div>
                                        <div x-show="p.ns_asignados.length" class="mt-2 text-[11px] text-indigo-700">
                                            = N/S seleccionados
                                        </div>
                                    </div>
                                    <input type="hidden" :name="`productos[${idx}][nombre_producto]`" :value="p.nombre_producto || p.descripcion || 'Producto'">
                                    <input type="hidden" :name="`productos[${idx}][descripcion]`" :value="p.descripcion || ''">
                                    <template x-for="(ns, sidx) in (p.ns_asignados || [])" :key="`ns-${idx}-${sidx}`">
                                        <input type="hidden" :name="`productos[${idx}][ns_asignados][]`" :value="ns">
                                    </template>
                                </td>

                                <td class="px-3 py-2 text-right">
                                    <template x-if="p.has_serial">
                                        <div>
                                            <input type="number" class="w-24 text-right rounded-lg border-gray-300 bg-gray-100"
                                                   :name="`productos[${idx}][cantidad]`"
                                                   :value="(p.ns_asignados || []).length"
                                                   readonly>
                                            <div class="text-[11px] text-gray-500">= N/S seleccionados</div>
                                        </div>
                                    </template>
                                    <template x-if="!p.has_serial">
                                        <input type="number" step="1" min="0" class="w-24 text-right rounded-lg border-gray-300"
                                               :class="(p.stock_max===0) ? 'bg-rose-50 border-rose-300 text-rose-700' : ''"
                                               :max="p.stock_max || null"
                                               :name="`productos[${idx}][cantidad]`"
                                               x-model.number="p.cantidad"
                                               @input="enforceMax(idx); calc()">
                                    </template>
                                </td>

                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" min="0" class="w-28 text-right rounded-lg border-gray-300"
                                           :name="`productos[${idx}][precio]`" x-model.number="p.precio" @input="calc()">
                                </td>

                                <td class="px-3 py-2 text-right">
                                    <span x-text="formatCurrency(lineImporte(p))"></span>
                                </td>

                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeProducto(idx)" class="px-2 py-1 rounded-md bg-red-600 hover:bg-red-700 text-white">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="productos.length === 0">
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">
                                No hay productos agregados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Totales --}}
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-start-3 bg-gray-50 rounded-xl border p-4">
                    <div class="flex justify-between text-sm mb-2">
                        <span>Subtotal material</span>
                        <span x-text="formatCurrency(totalMaterial)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Costo servicio</span>
                        <span x-text="formatCurrency(costoServicio)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Costo operativo</span>
                        <span x-text="formatCurrency(costoOperativo)"></span>
                    </div>

                    <div class="border-t my-2"></div>

                    <div class="flex justify-between text-sm mb-2">
                        <span>Base gravable (mat. + serv.)</span>
                        <span x-text="formatCurrency(baseGravable)"></span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span>Subtotal (base + costo operativo)</span>
                        <span x-text="formatCurrency(subtotal)"></span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span>IVA (16%)</span>
                        <span x-text="formatCurrency(totalImpuestos)"></span>
                    </div>

                    <div class="border-t my-2"></div>

                    <div class="flex justify-between text-base font-semibold">
                        <span>Total orden (con IVA)</span>
                        <span x-text="formatCurrency(totalOrden)"></span>
                    </div>

                    <div class="border-t my-2"></div>
                    <div class="flex justify-between text-sm mb-2">
                        <span>Anticipo</span>
                        <span class="font-semibold" x-text="formatCurrency(anticipoCalculado)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Saldo pendiente</span>
                        <span class="font-semibold" x-text="formatCurrency(saldoPendiente)"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Cantidad por escrito</h2>
                    <p class="text-sm text-gray-500">
                        Se usa en el PDF de la orden y como base del acta de conformidad del gerente.
                    </p>
                </div>

                <button type="button"
                        @click="recalcularPrecioEscrito()"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Recalcular con el total actual
                </button>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto en letra</label>
                <textarea name="precio_escrito"
                          x-model="precioEscrito"
                          @input="precioEscritoAuto = false"
                          rows="3"
                          class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                          placeholder="Ej. DOS MIL QUINIENTOS PESOS 00/100 M.N.">{{ $precioEscritoPrefill }}</textarea>
                <p class="mt-2 text-xs text-gray-500">
                    Puedes ajustarlo manualmente si necesitas una redacción distinta.
                </p>
            </div>

            <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Vista rápida</div>
                <div class="mt-1 text-sm font-medium text-gray-800 break-words" x-text="precioEscrito || '-'"></div>
            </div>
        </div>

        {{-- ✅ Anticipo --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Anticipo</h2>
                    <p class="text-xs text-gray-500">Captura por monto o por porcentaje. Afecta el saldo pendiente (y el crédito a cargar).</p>
                </div>

                <div class="text-right">
                    <div class="text-xs text-gray-500">Saldo pendiente</div>
                    <div class="text-base font-semibold text-gray-900" x-text="formatCurrency(saldoPendiente)"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Modo</label>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="anticipo_modo" value="monto" x-model="anticipoModo" class="rounded border-gray-300">
                            <span>Monto</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="anticipo_modo" value="porcentaje" x-model="anticipoModo" class="rounded border-gray-300">
                            <span>Porcentaje</span>
                        </label>
                    </div>
                </div>

                <div x-show="anticipoModo==='monto'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anticipo (monto)</label>
                    <input type="number" step="0.01" min="0"
                           name="anticipo_monto"
                           x-model.number="anticipoMonto"
                           :disabled="anticipoModo!=='monto'"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                           @input="calc()">
                    <p class="text-xs text-gray-500 mt-1">
                        Máximo: <span class="font-semibold" x-text="formatCurrency(totalOrden)"></span>
                    </p>
                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-3 py-2 text-sm"
                         x-show="anticipoMonto > totalOrden" x-cloak>
                        El anticipo no puede ser mayor al total.
                    </div>
                </div>

                <div x-show="anticipoModo==='porcentaje'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anticipo (%)</label>
                    <input type="number" step="0.01" min="0" max="100"
                           name="anticipo_porcentaje"
                           x-model.number="anticipoPorcentaje"
                           :disabled="anticipoModo!=='porcentaje'"
                           class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500"
                           @input="calc()">
                    <p class="text-xs text-gray-500 mt-1">
                        Calculado sobre el total con IVA.
                    </p>
                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-3 py-2 text-sm"
                         x-show="anticipoPorcentaje > 100" x-cloak>
                        El porcentaje no puede ser mayor a 100%.
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl border p-4">
                    <div class="text-sm font-semibold text-gray-800 mb-2">Resumen</div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>Total</span>
                        <span class="font-semibold" x-text="formatCurrency(totalOrden)"></span>
                    </div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>Anticipo</span>
                        <span class="font-semibold" x-text="formatCurrency(anticipoCalculado)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Saldo</span>
                        <span class="font-semibold" x-text="formatCurrency(saldoPendiente)"></span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        Porcentaje estimado:
                        <span class="font-semibold" x-text="anticipoPctEstimado.toFixed(2) + '%'"></span>
                    </div>
                </div>
            </div>
        </div>

        <x-firma-digital :firma="$firma ?? null" />

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('ordenes.index') }}" class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">Cancelar</a>

            <button type="button" id="btnPdfActual"
                    class="px-5 py-2 rounded-md border border-blue-300 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold">
                Ver PDF actual
            </button>

            <button type="button" id="btnPreview"
                    class="px-5 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-semibold"
                    :disabled="previewLoading">
                Previsualizar PDF
            </button>
        </div>
    </form>

    {{-- Modal catálogo productos --}}
    <div id="productModal"
         x-show="productModal"
         x-cloak
         @keydown.escape.window="productModal=false"
         class="fixed inset-0 flex items-center justify-center z-50">
        <div class="absolute inset-0 bg-black/50" @click="productModal=false"></div>
        <div class="relative max-w-4xl mx-auto bg-white rounded-lg shadow-lg w-full max-h-screen overflow-y-auto">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-800">Agregar producto</h2>
                    <button @click="productModal=false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <input id="productSearch" type="text" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                           placeholder="Buscar por nombre, descripción o número de parte">
                    <select id="productCategory" class="w-full sm:w-60 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Todas las categorías</option>
                        @foreach(($productos ?? collect())->pluck('categoria')->filter()->unique() as $categoria)
                            <option value="{{ strtolower((string)$categoria) }}">{{ $categoria }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hidden lg:block overflow-x-auto max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                    <table class="w-full">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Imagen</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Nombre</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Unidad</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Stock</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Precio</th>
                                <th class="text-left py-3 px-2 text-sm font-medium text-gray-600">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            @foreach(($productos ?? []) as $producto)
                                @php
                                    $invFirst = optional($producto->inventario->first());
                                    $tipoCtrl = (string)($invFirst->tipo_control ?? '');
                                    $hasSerial = $hasSerialDetect($tipoCtrl);
      $stockHint = (float) ($invFirst->stock_actual ?? $invFirst->cantidad_disponible ?? $invFirst->cantidad_actual ?? $invFirst->existencia ?? $invFirst->piezas_sueltas ?? $invFirst->cantidad ?? 0);
                                @endphp
                                <tr class="border-b border-gray-100 hover:bg-gray-50"
                                    data-name="{{ strtolower((string)$producto->nombre) }}"
                                    data-category="{{ strtolower((string)$producto->categoria) }}"
                                    data-code="{{ (string)$producto->codigo_producto }}">
                                    <td class="py-3 px-2">
                                        <div class="w-12 h-12 bg-blue-100 flex items-center justify-center rounded-full overflow-hidden">
                                            @if($producto->imagen_url ?? false)
                                                <img src="{{ $producto->imagen_url }}" alt="{{ $producto->nombre }}" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-blue-600 font-semibold">{{ substr((string)$producto->nombre, 0, 1) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 text-sm text-gray-800">{{ $producto->nombre }}</td>
                                    <td class="py-3 px-2 text-sm text-gray-600">{{ $producto->unidad }}</td>
                                    <td class="py-3 px-2 text-sm">
                                        <span class="stock-count" data-code="{{ (string)$producto->codigo_producto }}">—</span>
                                    </td>
                                    <td class="py-3 px-2 text-sm text-gray-600">${{ number_format(optional($producto->inventario->first())->precio ?? 0, 2) }}</td>
                                    <td class="py-3 px-2">
                                        <button type="button"
                                                onclick='event.stopPropagation(); openQuantityModal(@json((string)$producto->codigo_producto))'
                                                class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white hover:bg-green-600"
                                                data-add-btn
                                                data-code="{{ (string)$producto->codigo_producto }}"
                                                data-title-base="{{ $hasSerial ? 'Controlado por número de serie' : 'Sin serie' }}"
                                                title="{{ $hasSerial ? 'Controlado por número de serie' : 'Sin serie' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="lg:hidden space-y-4 max-h-96 overflow-y-auto" id="mobileProductList">
                    @foreach(($productos ?? []) as $producto)
                        @php
                            $invFirst = optional($producto->inventario->first());
                            $tipoCtrl = (string)($invFirst->tipo_control ?? '');
                            $hasSerial = $hasSerialDetect($tipoCtrl);
                        @endphp
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                             data-name="{{ strtolower((string)$producto->nombre) }}"
                             data-category="{{ strtolower((string)$producto->categoria) }}"
                             data-code="{{ (string)$producto->codigo_producto }}">
                            <div class="flex items-start space-x-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center overflow-hidden">
                                    @if($producto->imagen_url ?? false)
                                        <img src="{{ $producto->imagen_url }}" alt="{{ $producto->nombre }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-blue-600 font-semibold">{{ substr((string)$producto->nombre, 0, 1) }}</span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-sm font-medium text-gray-900 truncate">{{ $producto->nombre }}</h3>
                                        <button type="button"
                                                onclick="event.stopPropagation(); openQuantityModal(@json((string)$producto->codigo_producto))"
                                                class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white hover:bg-green-600 flex-shrink-0 ml-2"
                                                data-add-btn
                                                data-code="{{ (string)$producto->codigo_producto }}"
                                                data-title-base="{{ $hasSerial ? 'Controlado por número de serie' : 'Sin serie' }}"
                                                title="{{ $hasSerial ? 'Controlado por número de serie' : 'Sin serie' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-600 mb-1">Unidad: {{ $producto->unidad }}</p>
                                    <p class="text-xs text-gray-600 mb-1">Stock: <span class="stock-count" data-code="{{ (string)$producto->codigo_producto }}">—</span></p>
                                    <div class="text-xs">
                                        <span class="text-gray-900 font-medium">${{ number_format(optional($producto->inventario->first())->precio ?? 0, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>

    {{-- Modal cantidad / seriales --}}
    <div id="quantityModal" class="fixed inset-0 flex items-center justify-center z-[70] hidden">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="relative bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" id="productModalTitle"></h3>
                <button onclick="closeQuantityModal()" class="text-gray-400 hover:text-gray-600" type="button">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4" id="qmBody"></div>

            <div id="quantityError" class="text-red-600 text-sm mt-2 hidden"></div>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="closeQuantityModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancelar
                </button>
                <button type="button" id="qmAddBtn" onclick="addSelectedProduct()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-60">
                    Agregar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal PREVIEW / PDF ACTUAL --}}
    <div id="pdfPreviewModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/60" onclick="closePdfModal()"></div>
        <div class="relative mx-auto my-6 bg-white rounded-xl shadow-xl max-w-5xl w-[95vw] h-[85vh] flex flex-col">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 id="pdfModalTitle" class="font-semibold text-gray-800">Previsualización de la orden (PDF)</h3>
                <button class="text-gray-500 hover:text-gray-700" onclick="closePdfModal()">✕</button>
            </div>
            <div class="flex-1">
                <iframe id="pdfPreviewFrame" class="w-full h-full" src="about:blank"></iframe>
            </div>
            <div class="px-4 py-3 border-t flex items-center justify-end gap-2">
                <button type="button" class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50" onclick="closePdfModal()">Cerrar</button>

                <button type="button" id="btnDescargarActual" class="hidden px-4 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white">
                    Descargar actual
                </button>

                <button type="button" id="btnGuardar" class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white"
                        :disabled="tipoPago==='credito_cliente' && (credito.loading || !credito.exists || creditoInsuf || credito.expired)">
                    Guardar
                </button>
                <button type="button" id="btnGuardarDescargar" class="px-4 py-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white"
                        :disabled="tipoPago==='credito_cliente' && (credito.loading || !credito.exists || creditoInsuf || credito.expired)">
                    Guardar y descargar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal FALTANTES DE STOCK --}}
    <div id="stockShortageModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/60" onclick="closeStockShortageModal()"></div>
        <div class="relative mx-auto my-6 bg-white rounded-xl shadow-xl max-w-2xl w-[95vw]">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Productos sin stock suficiente</h3>
                <button class="text-gray-500 hover:text-gray-700" onclick="closeStockShortageModal()">✕</button>
            </div>
            <div class="p-4">
                <p class="text-sm text-gray-700 mb-3">
                    No es posible generar el PDF o guardar la orden porque algunos productos no tienen stock suficiente:
                </p>
                <ul id="shortageList" class="space-y-2 text-sm text-gray-800"></ul>
                <div class="mt-4 text-xs text-gray-500">
                    * Ajusta cantidades o elimina los artículos indicados, y vuelve a intentar.
                </div>
            </div>
            <div class="px-4 py-3 border-t flex items-center justify-end">
                <button type="button" class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white"
                        onclick="closeStockShortageModal()">Entendido</button>
            </div>
        </div>
    </div>

</div>

<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
function getCsrf() {
    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    return tokenEl ? tokenEl.getAttribute('content') : '';
}
window.getCsrf = getCsrf;
</script>

<script>
(function(){
  if (!window.CSS) window.CSS = {};
  if (!CSS.escape) CSS.escape = function (s) { try { return String(s); } catch { return s; } };
})();
</script>

<script>
  const SAVE_URL        = @json($saveUrl);
  const PREVIEW_URL     = @json(route('ordenes.preview'));
  const PDF_CURRENT_URL = @json($pdfUrl);
  const PEEK_SERIES_URL = @json(route('inventario.peekSeries'));
  const STOCK_URL       = @json(route('ordenes.api.producto.stock'));
  const CREDITO_URL     = @json(route('ordenes.api.credito'));
  const TIPO_CAMBIO_URL = @json(route('api.tipo-cambio'));

  const RESERVAR_SERIES_URL = @json(url('/api/inventario/reservar-series'));
  const LIBERAR_SERIES_URL  = @json(url('/api/inventario/liberar-series'));
</script>

<script>
const availableProducts = {
  @foreach(($productos ?? []) as $producto)
    @php
      $invFirst = optional($producto->inventario->first());
      $tipoCtrl = (string)($invFirst->tipo_control ?? '');
      $hasSerial = $hasSerialDetect($tipoCtrl);
      $stockHint = (float) ($invFirst->stock_actual ?? $invFirst->cantidad_disponible ?? $invFirst->cantidad_actual ?? $invFirst->existencia ?? $invFirst->piezas_sueltas ?? $invFirst->cantidad ?? 0);
    @endphp
    [@json((string)$producto->codigo_producto)]: {
      id: @json((string)$producto->codigo_producto),
      nombre: @json($producto->nombre),
      unidad: @json($producto->unidad),
      precio: {{ optional($producto->inventario->first())->precio ?? 0 }},
      imagen: @json($producto->imagen_url ?? ''),
      categoria: @json($producto->categoria ?? ''),
      descripcion: @json($producto->descripcion ?? ''),
      tipo_control: @json($tipoCtrl),
      has_serial: {{ $hasSerial ? 'true' : 'false' }},
    },
  @endforeach
};

const clientesCatalog = {
  @foreach(($clientes ?? []) as $c)
    [@json((string)$c->clave_cliente)]: @json(['ubicacion' => $c->ubicacion]),
  @endforeach
};
</script>

<script>
(function(){
  const quantityModal   = document.getElementById('quantityModal');
  const qmBody          = document.getElementById('qmBody');
  const qmAddBtn        = document.getElementById('qmAddBtn');
  const productSearch   = document.getElementById('productSearch');
  const productCategory = document.getElementById('productCategory');
  const quantityError   = document.getElementById('quantityError');

  let currentProduct    = null;
  let currentStock      = 0;
  let currentHasSerial  = false;
  let currentSerials    = [];
  let selectedSerials   = [];

  const stockCache = {};
  let annotateBusy = false;

  async function postJson(url, payload){
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrf(),
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload || {})
    });
    const data = await res.json().catch(()=>null);
    return { ok: res.ok, status: res.status, data };
  }

  function getFormComponent() {
    if (window.__osComp) return window.__osComp;

    const root = document.querySelector('[x-data^="formOrdenServicio"]');
    if (!root) return null;

    if (root.__x && root.__x.$data) {
      window.__osComp = root.__x.$data;
      return window.__osComp;
    }

    if (Array.isArray(root._x_dataStack) && root._x_dataStack[0]) {
      window.__osComp = root._x_dataStack[0];
      return window.__osComp;
    }

    return null;
  }

  function getSerialToken(){
    const comp = getFormComponent();
    return comp ? (comp.serialToken || '') : '';
  }

  function getUsedSerialsSet(){
    const comp = getFormComponent();
    const set = new Set();
    if (!comp || !Array.isArray(comp.productos)) return set;
    comp.productos.forEach(p=>{
      (p.ns_asignados || []).forEach(ns=>{
        if (ns) set.add(String(ns));
      });
    });
    return set;
  }

  function debounce(fn, wait=250){
    let t=null;
    return (...args)=>{
      clearTimeout(t);
      t=setTimeout(()=>fn(...args), wait);
    };
  }

  function filterProducts() {
    const search = (productSearch?.value || '').toLowerCase();
    const category = (productCategory?.value || '').toLowerCase();

    document.querySelectorAll('#productTableBody tr').forEach(row => {
      const name = row.dataset.name || '';
      const cat  = row.dataset.category || '';
      row.style.display = (name.includes(search) && (category === '' || cat.includes(category))) ? '' : 'none';
    });

    document.querySelectorAll('#mobileProductList > div').forEach(card => {
      const name = card.dataset.name || '';
      const cat  = card.dataset.category || '';
      card.style.display = (name.includes(search) && (category === '' || cat.includes(category))) ? '' : 'none';
    });

    annotateStockOnCatalog();
  }

  const filterProductsDebounced = debounce(filterProducts, 180);
  productSearch?.addEventListener('input', filterProductsDebounced);
  productCategory?.addEventListener('change', filterProductsDebounced);

  async function fetchStockOnce(code){
    const token = getSerialToken();
    const cacheKey = token ? `${code}::${token}` : code;
    if (stockCache.hasOwnProperty(cacheKey)) return stockCache[cacheKey];
    const fallbackStock = Math.max(parseInt((availableProducts[code]?.stock_hint ?? 0), 10) || 0, 0);

    try{
      const r = await fetch(`${STOCK_URL}?codigo=${encodeURIComponent(code)}&token=${encodeURIComponent(token)}&t=${Date.now()}`, { headers:{'Accept':'application/json'} });
      const j = await r.json();
      const rawStock = j ? (j.stock ?? j.disponible ?? j.cantidad ?? j.qty) : null;
      const st = Math.max(parseInt((rawStock ?? fallbackStock), 10) || 0, 0);
      stockCache[cacheKey] = { stock: st, has_serial: !!(j && j.has_serial) };
    } catch(e){
      stockCache[cacheKey] = { stock: fallbackStock, has_serial: !!(availableProducts[code]?.has_serial) };
    }
    return stockCache[cacheKey];
  }

  async function annotateStockOnCatalog(){
    if (annotateBusy) return;
    annotateBusy = true;

    try{
      const codes = new Set();
      document.querySelectorAll('#productTableBody tr[data-code], #mobileProductList [data-code]').forEach(el => {
        const c = el.getAttribute('data-code');
        if (c) codes.add(c);
      });

      for (const code of codes){
        const { stock } = await fetchStockOnce(code);
        const isZero = stock <= 0;

        document.querySelectorAll(`.stock-count[data-code="${CSS.escape(code)}"]`)
          .forEach(el => { el.textContent = String(stock); });

        document.querySelectorAll(`tr[data-code="${CSS.escape(code)}"]`).forEach(tr => {
          tr.classList.toggle('bg-rose-50', isZero);
          tr.classList.toggle('opacity-60', isZero);
        });

        document.querySelectorAll(`#mobileProductList [data-code="${CSS.escape(code)}"]`).forEach(card => {
          card.classList.toggle('bg-rose-50', isZero);
          card.classList.toggle('border-rose-300', isZero);
        });

        document.querySelectorAll(`[data-add-btn][data-code="${CSS.escape(code)}"]`).forEach(btn=>{
          const baseTitle = btn.getAttribute('data-title-base') || '';
          if (isZero){
            btn.disabled = true;
            btn.classList.remove('bg-green-500','hover:bg-green-600');
            btn.classList.add('bg-gray-300','cursor-not-allowed');
            btn.setAttribute('title', `${baseTitle}${baseTitle ? ' — ' : ''}Sin stock`);
          } else {
            btn.disabled = false;
            btn.classList.add('bg-green-500','hover:bg-green-600');
            btn.classList.remove('bg-gray-300','cursor-not-allowed');
            btn.setAttribute('title', baseTitle);
          }
        });
      }
    } finally {
      annotateBusy = false;
    }
  }

  async function openQuantityModal(productId){
    currentProduct = availableProducts[productId];
    if (!currentProduct) return;

    const token = getSerialToken();

    try {
      const sres = await fetch(`${STOCK_URL}?codigo=${encodeURIComponent(productId)}&token=${encodeURIComponent(token)}&t=${Date.now()}`, { headers:{'Accept':'application/json'} });
      const sj = await sres.json();
      const rawStock = sj ? (sj.stock ?? sj.disponible ?? sj.cantidad ?? sj.qty) : null;
      const fallbackStock = Math.max(parseInt((currentProduct && currentProduct.stock_hint) ? currentProduct.stock_hint : 0, 10) || 0, 0);
      currentStock = Math.max(parseInt((rawStock ?? fallbackStock), 10) || 0, 0);
      currentHasSerial = !!(sj && sj.has_serial);
    } catch(e) {
      currentStock = Math.max(parseInt((currentProduct && currentProduct.stock_hint) ? currentProduct.stock_hint : 0, 10) || 0, 0);
      currentHasSerial = !!(currentProduct && currentProduct.has_serial);
    }

    currentSerials = [];
    selectedSerials = [];

    if (currentHasSerial) {
       try {
          const r = await fetch(`${PEEK_SERIES_URL}?codigo=${encodeURIComponent(productId)}&token=${encodeURIComponent(token)}&t=${Date.now()}`, { headers:{'Accept':'application/json'} });
          const j = await r.json();
          currentSerials = Array.isArray(j.series) ? j.series : [];
       } catch(e) {
          currentSerials = [];
       }
    }

    buildQuantityModalContent();
    quantityModal.classList.remove('hidden');
  }

  function buildQuantityModalContent() {
      quantityError.classList.add('hidden');
      quantityError.textContent = '';
      if (!currentProduct) {
        qmBody.innerHTML = '<p class="text-sm text-gray-700">Producto no encontrado.</p>';
        return;
      }

      const comp = getFormComponent();
      let defaultPrecio = Number(currentProduct.precio || 0);
      if (comp && comp.moneda === 'USD' && comp.usdToMxn > 0) {
          defaultPrecio = defaultPrecio / comp.usdToMxn;
      }

      const usedSerials = getUsedSerialsSet();

      let html = '';
      html += `<p class="text-sm text-gray-700 mb-2"><strong>${escapeHtml(currentProduct.nombre)}</strong></p>`;
      html += `<p class="text-xs text-gray-500 mb-3">Stock disponible: <b>${currentStock}</b></p>`;

      if (currentHasSerial) {
         if (!currentSerials.length) {
            html += '<p class="text-sm text-gray-600">No hay números de serie disponibles para este producto.</p>';
         } else {
            html += '<div class="flex items-center justify-between gap-2 mb-2">';
            html += '  <p class="text-sm text-gray-700">Selecciona los números de serie a utilizar:</p>';
            html += '  <input id="qmNsSearch" type="text" class="w-44 border rounded px-2 py-1 text-xs" placeholder="Filtrar N/S...">';
            html += '</div>';
            html += '<div id="qmNsList" class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-52 overflow-y-auto">';
            currentSerials.forEach((ns) => {
                const raw = String(ns);
                const safe = escapeHtml(raw);
                const already = usedSerials.has(raw);
                html += `<label class="inline-flex items-center text-xs border rounded px-2 py-1 cursor-pointer ${already ? 'opacity-60' : ''}" data-ns="${safe.toLowerCase()}">
                          <input type="checkbox" class="mr-2 serial-checkbox" value="${safe}" ${already ? 'disabled' : ''}>
                          <span class="truncate">${safe}</span>
                          ${already ? '<span class="ml-2 text-[10px] text-amber-700">(ya usado)</span>' : ''}
                         </label>`;
            });
            html += '</div>';
            html += '<p class="mt-2 text-[11px] text-gray-500">Tip: evita repetir el mismo N/S en varias líneas.</p>';
         }
      } else {
         html += '<label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>';
         html += `<input type="number" id="qmCantidad" min="1" step="1" class="w-32 border rounded px-2 py-1" value="${currentStock > 0 ? 1 : 0}" ${currentStock>0 ? 'max="'+currentStock+'"' : ''}>`;
      }

      html += '<div class="mt-4">';
      html += '<label class="block text-sm font-medium text-gray-700 mb-1">Precio unitario</label>';
      html += `<input type="number" id="qmPrecio" step="0.01" min="0" class="w-40 border rounded px-2 py-1" value="${ defaultPrecio.toFixed(2) }">`;
      html += '</div>';

      qmBody.innerHTML = html;
      document.getElementById('productModalTitle').textContent = `Agregar producto #${currentProduct.id}`;

      const nsSearch = document.getElementById('qmNsSearch');
      if (nsSearch) {
        nsSearch.addEventListener('input', debounce(() => {
          const q = (nsSearch.value || '').toLowerCase().trim();
          document.querySelectorAll('#qmNsList [data-ns]').forEach(el => {
            const v = el.getAttribute('data-ns') || '';
            el.style.display = (!q || v.includes(q)) ? '' : 'none';
          });
        }, 120));
      }

      qmAddBtn.disabled = (currentHasSerial && !currentSerials.length) || (!currentHasSerial && currentStock <= 0);
  }

  function closeQuantityModal(){
     quantityModal.classList.add('hidden');
     currentProduct = null;
     currentStock = 0;
     currentHasSerial = false;
     currentSerials = [];
     selectedSerials = [];
     qmAddBtn.disabled = false;
  }

  async function reserveSelectedSerials(codigoProducto, series, token){
    const payload = { codigo_producto: Number(codigoProducto), token: String(token || ''), series: (series || []).slice() };
    const { ok, status, data } = await postJson(RESERVAR_SERIES_URL, payload);

    if (!ok) {
      if (status === 404 || status === 405) {
        return { ok:true, reserved: (series || []).slice(), taken: [], expires_at: null, bypass: true };
      }
      const msg = (data && data.message) ? data.message : 'No se pudieron reservar los números de serie.';
      return { ok:false, message: msg, taken: [] };
    }

    const taken = Array.isArray(data?.taken) ? data.taken : [];
    const reserved = Array.isArray(data?.reserved) ? data.reserved : [];
    return { ok: taken.length === 0, reserved, taken, expires_at: data?.expires_at || null };
  }

  async function releaseSerials(token, codigoProducto=null, series=null){
    const payload = { token: String(token || '') };
    if (codigoProducto) payload.codigo_producto = Number(codigoProducto);
    if (Array.isArray(series)) payload.series = series.slice();

    try { await postJson(LIBERAR_SERIES_URL, payload); } catch(e) {}
  }
  window.__releaseSerials = releaseSerials;

  async function addSelectedProduct() {
      if (!currentProduct) return;

      quantityError.classList.add('hidden');
      quantityError.textContent = '';

      let cantidad = 0;
      let precio   = 0;

      const token = getSerialToken();

      const precioInput = document.getElementById('qmPrecio');
      if (precioInput) {
         precio = parseFloat(precioInput.value || '0');
         if (!isFinite(precio) || precio < 0) precio = 0;
      }

      if (currentHasSerial) {
          const checks = Array.from(document.querySelectorAll('#qmBody .serial-checkbox'));
          selectedSerials = checks.filter(c => c.checked).map(c => c.value);

          if (!selectedSerials.length) {
             quantityError.textContent = 'Selecciona al menos un número de serie.';
             quantityError.classList.remove('hidden');
             return;
          }

          const used = getUsedSerialsSet();
          const dup = selectedSerials.find(ns => used.has(String(ns)));
          if (dup) {
            quantityError.textContent = `El N/S ${dup} ya está usado en otra línea del formulario.`;
            quantityError.classList.remove('hidden');
            return;
          }

          qmAddBtn.disabled = true;
          const res = await reserveSelectedSerials(currentProduct.id, selectedSerials, token);
          qmAddBtn.disabled = false;

          if (!res.ok) {
            const taken = (res.taken || []).join(', ');
            quantityError.textContent = taken
              ? `Algunos N/S ya no están disponibles: ${taken}. Vuelve a intentar.`
              : (res.message || 'No se pudieron reservar los N/S.');
            quantityError.classList.remove('hidden');

            try { await openQuantityModal(currentProduct.id); } catch(e){}
            return;
          }

          selectedSerials = (res.reserved || selectedSerials).slice();
          cantidad = selectedSerials.length;
      } else {
          const qtyInput = document.getElementById('qmCantidad');
          if (!qtyInput) return;

          cantidad = parseInt(qtyInput.value || '0', 10) || 0;
          if (cantidad <= 0) {
             quantityError.textContent = 'La cantidad debe ser mayor a cero.';
             quantityError.classList.remove('hidden');
             return;
          }
          if (currentStock > 0 && cantidad > currentStock) {
             quantityError.textContent = `La cantidad no puede exceder el stock disponible (${currentStock}).`;
             quantityError.classList.remove('hidden');
             return;
          }
      }

      const comp = getFormComponent();
      if (!comp) { closeQuantityModal(); return; }

      comp.addProductoDesdeCatalogo({
          codigo_producto: currentProduct.id,
          nombre_producto: currentProduct.nombre,
          descripcion: currentProduct.descripcion || '',
          cantidad: cantidad,
          precio: precio,
          ns_asignados: currentHasSerial ? selectedSerials.slice() : [],
          stock_max: currentStock,
          has_serial: currentHasSerial
      });

      closeQuantityModal();

      try { comp.productModal = false; } catch {}
      annotateStockOnCatalog();
  }

  function escapeHtml(str){
     return String(str)
       .replace(/&/g, '&amp;')
       .replace(/</g, '&lt;')
       .replace(/>/g, '&gt;')
       .replace(/"/g, '&quot;')
       .replace(/'/g, '&#039;');
  }

  document.addEventListener('alpine:init', () => {
     annotateStockOnCatalog();
  });

  window.addEventListener('beforeunload', ()=>{
    const comp = getFormComponent();
    if (!comp || comp.hasSaved) return;

    const token = getSerialToken();
    if (!token) return;

    try{
      const payload = JSON.stringify({ token });
      const blob = new Blob([payload], { type: 'application/json' });
      navigator.sendBeacon(LIBERAR_SERIES_URL, blob);
    } catch(err) {}
  });

  window.openQuantityModal = openQuantityModal;
  window.closeQuantityModal = closeQuantityModal;
  window.addSelectedProduct = addSelectedProduct;
  window.getFormComponent = getFormComponent;
  window.getSerialToken = getSerialToken;
})();
</script>

<script>
(function(){
  const OLD_TIPO_PAGO = @json(old('tipo_pago', $orden->tipo_pago ?? 'efectivo'));
  const OLD_ANTICIPO_MODO       = @json(old('anticipo_modo', ((float)($orden->anticipo_porcentaje ?? 0) > 0 ? 'porcentaje' : 'monto')));
  const OLD_ANTICIPO_MONTO      = @json(old('anticipo_monto', (float)($orden->anticipo ?? 0)));
  const OLD_ANTICIPO_PORCENTAJE = @json(old('anticipo_porcentaje', (float)($orden->anticipo_porcentaje ?? 0)));
  const OLD_PRECIO_ESCRITO      = @json($precioEscritoPrefill);
  const AUTO_PRECIO_ESCRITO     = @json($precioEscritoAutoPrefill);

  function genToken(){
    try {
      if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    } catch(e){}
    return 'tok_' + Math.random().toString(16).slice(2) + '_' + Date.now();
  }

  function formOrdenServicio(prefillProductos, monedaInicial, clienteInicial, tipoOrdenInicial, sinTecnicoInicial, clientesList) {
    return {
      productos: [],
      tipoOrden: tipoOrdenInicial || 'servicio_simple',
      moneda: monedaInicial || 'MXN',
      monedaBase: monedaInicial || 'MXN',
      idCliente: clienteInicial || '',
      ubicacionCliente: '',
      tipoPago: OLD_TIPO_PAGO || 'efectivo',
      sinTecnico: !!sinTecnicoInicial,
      productModal: false,

      serialToken: '',
      hasSaved: false,

      anticipoModo: (OLD_ANTICIPO_MODO || 'monto'),
      anticipoMonto: Number(OLD_ANTICIPO_MONTO || 0),
      anticipoPorcentaje: Number(OLD_ANTICIPO_PORCENTAJE || 0),
      anticipoCalculado: 0,
      saldoPendiente: 0,
      anticipoPctEstimado: 0,
      precioEscrito: OLD_PRECIO_ESCRITO || '',
      precioEscritoAuto: !!AUTO_PRECIO_ESCRITO,

      clientesAll: Array.isArray(clientesList) ? clientesList : [],
      clientesFiltrados: [],
      clienteSearch: '',
      showClienteList: false,

      costoServicio: 0,
      costoOperativo: 0,
      totalMaterial: 0,
      baseGravable: 0,
      subtotal: 0,
      totalImpuestos: 0,
      totalOrden: 0,
      usdToMxn: 16.95,

      credito: {
        loading: false,
        exists: false,
        expired: false,
        estatus: null,
        monto_maximo: 0,
        monto_usado: 0,
        disponible: 0,
        dias_credito: null,
        fecha_limite: null,
        dias_restantes: null,
      },
      creditoInsuf: false,

      previewLoading: false,

      init() {
        window.__osComp = this;

        if (!this.serialToken) this.serialToken = genToken();

        const inServ = document.querySelector('input[name="precio"]');
        const inOp   = document.querySelector('input[name="costo_operativo"]');
        this.costoServicio   = parseFloat(inServ?.value || '0') || 0;
        this.costoOperativo  = parseFloat(inOp?.value || '0') || 0;

        this.updateUbicacionCliente();

        if (Array.isArray(prefillProductos) && prefillProductos.length) {
          this.productos = prefillProductos.map(p => this.normalizarProducto(p));
        }

        this.syncClienteSearchFromId();

        if (this.tipoOrden === 'compra') {
          this.sinTecnico = true;
          this.clearTecnicos();
        }

        if (typeof this.$watch === 'function') {
          this.$watch('idCliente', () => {
            this.updateUbicacionCliente();
            this.syncClienteSearchFromId();
            if (this.tipoPago === 'credito_cliente') this.loadCredito();
          });

          this.$watch('tipoPago', () => {
            if (this.tipoPago === 'credito_cliente' && this.idCliente) {
              this.loadCredito();
            }
            this.updateCreditoInsuf();
          });

          this.$watch('moneda', () => {
            this.onChangeMoneda();
          });

          this.$watch('tipoOrden', (val) => {
            if (val === 'compra') {
              this.sinTecnico = true;
              this.clearTecnicos();
            }
          });

          this.$watch('anticipoModo', () => this.calc());
          this.$watch('anticipoMonto', () => this.calc());
          this.$watch('anticipoPorcentaje', () => this.calc());
        }

        this.calc();

        if (this.tipoPago === 'credito_cliente' && this.idCliente) {
          this.loadCredito();
        }

        this.loadExchangeRate();
      },

      filterClientes() {
        const q = (this.clienteSearch || '').toLowerCase().trim();
        if (!q) {
          this.clientesFiltrados = this.clientesAll.slice(0, 30);
          return;
        }
        this.clientesFiltrados = this.clientesAll
          .filter(c => (c.label || '').toLowerCase().includes(q))
          .slice(0, 30);
      },

      selectCliente(c) {
        if (!c) return;
        this.idCliente = String(c.clave_cliente || '');
        this.clienteSearch = c.label || '';
        this.showClienteList = false;
        this.updateUbicacionCliente();
        if (this.tipoPago === 'credito_cliente') this.loadCredito();
      },

      clearCliente() {
        this.idCliente = '';
        this.clienteSearch = '';
        this.ubicacionCliente = '';
        this.showClienteList = false;
        this.credito.exists = false;
        this.credito.disponible = 0;
        this.updateCreditoInsuf();
      },

      syncClienteSearchFromId() {
        if (!this.idCliente) return;
        const found = this.clientesAll.find(x => String(x.clave_cliente) === String(this.idCliente));
        if (found && !this.clienteSearch) {
          this.clienteSearch = found.label || '';
        }
      },

      async loadExchangeRate() {
        try {
          const res = await fetch(TIPO_CAMBIO_URL, { headers: { 'Accept': 'application/json' } });
          if (!res.ok) return;
          const data = await res.json();
          if (data && data.ok) {
            if (data.usd_mxn) this.usdToMxn = Number(data.usd_mxn) || this.usdToMxn;
            else if (data.mxn_usd && Number(data.mxn_usd) > 0) this.usdToMxn = 1 / Number(data.mxn_usd);
          }
        } catch (e) {
          console.error('Error tipo de cambio', e);
        } finally {
          this.calc();
        }
      },

      updateUbicacionCliente() {
        if (this.idCliente && clientesCatalog[this.idCliente]) {
          this.ubicacionCliente = clientesCatalog[this.idCliente].ubicacion || '';
        } else {
          this.ubicacionCliente = '';
        }
      },

      normalizarProducto(p) {
        const qty   = this.cantidadFrom(p);
        const price = this.precioFrom(p);

        let stockMax = null;
        if (typeof p.stock_max === 'number') stockMax = p.stock_max;
        else if (typeof p.stock_disponible === 'number') stockMax = p.stock_disponible;
        else if (typeof p.disponible === 'number') stockMax = p.disponible;

        return {
          codigo_producto: p.codigo_producto ?? p.codigo ?? null,
          nombre_producto: p.nombre_producto || p.nombre || p.descripcion || 'Producto',
          descripcion: p.descripcion || '',
          cantidad: qty,
          precio: price,
          ns_asignados: Array.isArray(p.ns_asignados) ? p.ns_asignados.slice() : [],
          stock_max: stockMax,
          has_serial: !!p.has_serial,
        };
      },

      cantidadFrom(p) {
        if (Array.isArray(p.ns_asignados) && p.ns_asignados.length) return p.ns_asignados.length;
        if (p.cantidad !== undefined && p.cantidad !== null && p.cantidad !== '') return parseFloat(p.cantidad) || 0;
        if (p.qty !== undefined) return parseFloat(p.qty) || 0;
        return 0;
      },

      precioFrom(p) {
        const keys = ['precio', 'precio_unitario', 'precioUnitario', 'price', 'unit_price'];
        for (let k of keys) {
          if (p[k] !== undefined && p[k] !== null && p[k] !== '') return parseFloat(p[k]) || 0;
        }
        return 0;
      },

      round2(v) {
        const n = Number(v) || 0;
        return Math.round(n * 100) / 100;
      },

      formatCurrency(v) {
        const n = Number(v) || 0;
        try {
          return new Intl.NumberFormat('es-MX', { style: 'currency', currency: this.moneda || 'MXN' }).format(n);
        } catch {
          return `$${n.toFixed(2)}`;
        }
      },

      formatCurrencyMXN(v) {
        const n = Number(v) || 0;
        try {
          return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n);
        } catch {
          return `$${n.toFixed(2)}`;
        }
      },

      ajustarNumeroParaMoneda(words) {
        return String(words || '')
          .replace(/VEINTIUNO$/, 'VEINTIUN')
          .replace(/ Y UNO$/, ' Y UN')
          .replace(/ UNO$/, ' UN');
      },

      numeroALetrasEntero(number) {
        const units = ['CERO', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        const specials = {
          10: 'DIEZ',
          11: 'ONCE',
          12: 'DOCE',
          13: 'TRECE',
          14: 'CATORCE',
          15: 'QUINCE',
          16: 'DIECISEIS',
          17: 'DIECISIETE',
          18: 'DIECIOCHO',
          19: 'DIECINUEVE',
          20: 'VEINTE',
          21: 'VEINTIUNO',
          22: 'VEINTIDOS',
          23: 'VEINTITRES',
          24: 'VEINTICUATRO',
          25: 'VEINTICINCO',
          26: 'VEINTISEIS',
          27: 'VEINTISIETE',
          28: 'VEINTIOCHO',
          29: 'VEINTINUEVE',
        };
        const tens = {
          3: 'TREINTA',
          4: 'CUARENTA',
          5: 'CINCUENTA',
          6: 'SESENTA',
          7: 'SETENTA',
          8: 'OCHENTA',
          9: 'NOVENTA',
        };
        const hundreds = {
          1: 'CIENTO',
          2: 'DOSCIENTOS',
          3: 'TRESCIENTOS',
          4: 'CUATROCIENTOS',
          5: 'QUINIENTOS',
          6: 'SEISCIENTOS',
          7: 'SETECIENTOS',
          8: 'OCHOCIENTOS',
          9: 'NOVECIENTOS',
        };

        if (number < 10) return units[number];
        if (number < 30) return specials[number];
        if (number < 100) {
          const ten = Math.floor(number / 10);
          const rest = number % 10;
          return tens[ten] + (rest ? ` Y ${this.numeroALetrasEntero(rest)}` : '');
        }
        if (number === 100) return 'CIEN';
        if (number < 1000) {
          const hundred = Math.floor(number / 100);
          const rest = number % 100;
          return hundreds[hundred] + (rest ? ` ${this.numeroALetrasEntero(rest)}` : '');
        }
        if (number < 2000) {
          return 'MIL' + (number % 1000 ? ` ${this.numeroALetrasEntero(number % 1000)}` : '');
        }
        if (number < 1000000) {
          const thousands = Math.floor(number / 1000);
          const rest = number % 1000;
          return `${this.numeroALetrasEntero(thousands)} MIL${rest ? ` ${this.numeroALetrasEntero(rest)}` : ''}`;
        }
        if (number < 2000000) {
          return `UN MILLON${number % 1000000 ? ` ${this.numeroALetrasEntero(number % 1000000)}` : ''}`;
        }
        if (number < 1000000000000) {
          const millions = Math.floor(number / 1000000);
          const rest = number % 1000000;
          return `${this.ajustarNumeroParaMoneda(this.numeroALetrasEntero(millions))} MILLONES${rest ? ` ${this.numeroALetrasEntero(rest)}` : ''}`;
        }

        return String(number);
      },

      numeroALetrasMoneda(num, moneda) {
        const amount = Number(num || 0);
        let integer = Math.floor(amount);
        let cents = Math.round((amount - integer) * 100);

        if (cents === 100) {
          integer += 1;
          cents = 0;
        }

        const words = this.ajustarNumeroParaMoneda(this.numeroALetrasEntero(integer));
        const noun = moneda === 'USD'
          ? (integer === 1 ? 'DOLAR' : 'DOLARES')
          : (integer === 1 ? 'PESO' : 'PESOS');
        const suffix = moneda === 'USD' ? 'USD' : 'M.N.';

        return `${words} ${noun} ${String(cents).padStart(2, '0')}/100 ${suffix}`;
      },

      syncPrecioEscrito(force = false) {
        if (!force && !this.precioEscritoAuto && String(this.precioEscrito || '').trim() !== '') {
          return;
        }

        this.precioEscrito = this.numeroALetrasMoneda(this.totalOrden, this.moneda || 'MXN');
      },

      recalcularPrecioEscrito() {
        this.precioEscritoAuto = true;
        this.syncPrecioEscrito(true);
      },

      lineImporte(p) {
        return this.round2(this.cantidadFrom(p) * this.precioFrom(p));
      },

      anticipoCalculadoMXN() {
        const a = Number(this.anticipoCalculado || 0);
        if (this.moneda === 'USD' && this.usdToMxn > 0) return this.round2(a * this.usdToMxn);
        return this.round2(a);
      },

      saldoPendienteMXN() {
        const s = Number(this.saldoPendiente || 0);
        if (this.moneda === 'USD' && this.usdToMxn > 0) return this.round2(s * this.usdToMxn);
        return this.round2(s);
      },

      addProductoDesdeCatalogo(obj) {
        this.productos.push(this.normalizarProducto(obj));
        this.calc();
      },

      async clearProductos() {
        const token = this.serialToken;
        if (token && typeof window.__releaseSerials === 'function') {
          await window.__releaseSerials(token);
        }
        this.productos = [];
        this.calc();
      },

      async removeProducto(idx) {
        if (idx >= 0 && idx < this.productos.length) {
          const p = this.productos[idx];
          if (p && p.has_serial && Array.isArray(p.ns_asignados) && p.ns_asignados.length) {
            if (this.serialToken && typeof window.__releaseSerials === 'function') {
              await window.__releaseSerials(this.serialToken, p.codigo_producto, p.ns_asignados);
            }
          }
          this.productos.splice(idx, 1);
          this.calc();
        }
      },

      enforceMax(idx) {
        const p = this.productos[idx];
        if (!p) return;
        if (typeof p.stock_max === 'number' && p.stock_max >= 0 && p.cantidad != null) {
          if (p.cantidad > p.stock_max) p.cantidad = p.stock_max;
          if (p.cantidad < 0) p.cantidad = 0;
        }
      },

      calc() {
        let material = 0;
        this.productos.forEach(p => { material += this.cantidadFrom(p) * this.precioFrom(p); });
        this.totalMaterial = this.round2(material);

        this.baseGravable = this.round2(this.totalMaterial + (this.costoServicio || 0));
        this.subtotal      = this.round2(this.baseGravable + (this.costoOperativo || 0));
        this.totalImpuestos= this.round2(this.baseGravable * 0.16);
        this.totalOrden    = this.round2(this.subtotal + this.totalImpuestos);

        let anticipo = 0;
        const total = Number(this.totalOrden || 0);

        if ((this.anticipoModo || 'monto') === 'porcentaje') {
          const pct = Math.min(Math.max(Number(this.anticipoPorcentaje || 0), 0), 100);
          anticipo = total > 0 ? (total * (pct / 100)) : 0;
        } else {
          const m = Math.max(Number(this.anticipoMonto || 0), 0);
          anticipo = Math.min(m, total);
        }

        this.anticipoCalculado = this.round2(anticipo);
        this.saldoPendiente = this.round2(Math.max(total - this.anticipoCalculado, 0));
        this.anticipoPctEstimado = total > 0 ? this.round2((this.anticipoCalculado / total) * 100) : 0;

        this.syncPrecioEscrito();
        this.updateCreditoInsuf();
      },

      onChangeMoneda() {
        if (!this.usdToMxn || this.usdToMxn <= 0) { this.calc(); return; }
        if (this.moneda === this.monedaBase) { this.calc(); return; }

        let factor = 1;

        if (this.monedaBase === 'MXN' && this.moneda === 'USD') factor = 1 / this.usdToMxn;
        else if (this.monedaBase === 'USD' && this.moneda === 'MXN') factor = this.usdToMxn;
        else { this.calc(); return; }

        this.costoServicio  = this.round2(this.costoServicio * factor);
        this.costoOperativo = this.round2(this.costoOperativo * factor);
        this.productos = this.productos.map(p => ({ ...p, precio: this.round2((p.precio || 0) * factor) }));

        if ((this.anticipoModo || 'monto') === 'monto') {
          this.anticipoMonto = this.round2((Number(this.anticipoMonto || 0)) * factor);
        }

        this.monedaBase = this.moneda;
        this.calc();
      },

      importeParaCreditoMXN() {
        const saldo = Number(this.saldoPendiente || 0);
        if (this.moneda === 'USD' && this.usdToMxn > 0) return this.round2(saldo * this.usdToMxn);
        return this.round2(saldo);
      },

      async loadCredito() {
        if (!this.idCliente) return;
        this.credito.loading = true;
        try {
          const r = await fetch(`${CREDITO_URL}?cliente=${encodeURIComponent(this.idCliente)}&t=${Date.now()}`, { headers: { 'Accept': 'application/json' } });
          const j = await r.json();
          if (j && j.ok) {
            this.credito.exists       = !!j.exists;
            this.credito.expired      = !!j.expired;
            this.credito.estatus      = j.estatus || null;
            this.credito.monto_maximo = Number(j.monto_maximo || 0);
            this.credito.monto_usado  = Number(j.monto_usado || 0);
            this.credito.disponible   = Number(j.disponible || 0);
            this.credito.dias_credito = j.dias_credito;
            this.credito.fecha_limite = j.fecha_limite;
            this.credito.dias_restantes = j.dias_restantes;
          } else {
            this.credito.exists = false;
            this.credito.disponible = 0;
          }
        } catch (e) {
          this.credito.exists = false;
          this.credito.disponible = 0;
        } finally {
          this.credito.loading = false;
          this.updateCreditoInsuf();
        }
      },

      updateCreditoInsuf() {
        this.creditoInsuf = false;
        if (this.tipoPago !== 'credito_cliente') return;
        if (!this.credito || !this.credito.exists || this.credito.expired) { this.creditoInsuf = true; return; }

        const importe = this.importeParaCreditoMXN();
        if (importe > (this.credito.disponible || 0)) this.creditoInsuf = true;
      },

      clearTecnicos() {
        const multi = document.querySelector('select[name="tecnicos_ids[]"]');
        if (multi) Array.from(multi.options).forEach(opt => opt.selected = false);
        const single = document.querySelector('select[name="id_tecnico"]');
        if (single) single.value = '';
      },

      async previewPdf() {
        const form = document.getElementById('ordenForm');
        if (!form) return;
        this.previewLoading = true;

        const fd = new FormData(form);
        fd.delete('_method'); // evitar method spoofing (PUT) en preview

        try {
          const r = await fetch(PREVIEW_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
            body: fd
          });

          const json = await r.json().catch(() => null);

          if (!r.ok) {
            if (json && json.shortages) {
              this.applyPreviewAnnotated(json.productos_preview || []);
              openStockShortageModal(json.shortages);
            } else if (json && json.message) alert(json.message);
            else alert('Error al generar la previsualización.');
            return;
          }

          if (!json || !json.ok) { if (json && json.message) alert(json.message); return; }

          this.applyPreviewAnnotated(json.productos_preview || []);
          if (json.pdf_base64) openPdfModalFromBase64(json.pdf_base64);
        } catch (e) {
          console.error(e);
          alert('Ocurrió un error al generar la previsualización.');
        } finally {
          this.previewLoading = false;
        }
      },

      applyPreviewAnnotated(items) {
        if (!Array.isArray(items) || !items.length) return;
        this.productos = items.map(i => this.normalizarProducto(i));
        this.calc();
      },

      async saveOrden(withDownload) {
        const form = document.getElementById('ordenForm');
        if (!form) return;

        const fd = new FormData(form);

        try {
          const r = await fetch(SAVE_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
            body: fd
          });

          const json = await r.json().catch(() => null);

          if (!r.ok) {
            if (r.status === 422 && json && json.shortages) {
              this.applyPreviewAnnotated(json.productos_preview || []);
              openStockShortageModal(json.shortages);
            } else if (r.status === 422 && json) {
              if (json.message) alert(json.message);
              else alert('Hay errores en el formulario. Revisa los campos obligatorios.');
            } else {
              alert('Error al guardar la orden.');
            }
            return;
          }

          if (!json || !json.ok) { if (json && json.message) alert(json.message); return; }

          this.hasSaved = true;

          const redirect    = json.redirect || null;
          const downloadUrl = withDownload ? (json.download_url || json.pdf_url || null) : null;

          if (downloadUrl) window.open(downloadUrl, '_blank');
          if (redirect) window.location.href = redirect;

        } catch (e) {
          console.error(e);
          alert('Ocurrió un error al guardar la orden.');
        }
      },
    };
  }

  const pdfPreviewModal    = document.getElementById('pdfPreviewModal');
  const pdfPreviewFrame    = document.getElementById('pdfPreviewFrame');
  const pdfModalTitle      = document.getElementById('pdfModalTitle');
  const btnDescargarActual = document.getElementById('btnDescargarActual');
  const btnGuardar         = document.getElementById('btnGuardar');
  const btnGuardarDescargar= document.getElementById('btnGuardarDescargar');

  const stockShortageModal = document.getElementById('stockShortageModal');
  const shortageListEl     = document.getElementById('shortageList');

  function setPdfModalMode(mode) {
    if (mode === 'current') {
      if (pdfModalTitle) pdfModalTitle.textContent = 'PDF actual de la orden';
      btnGuardar?.classList.add('hidden');
      btnGuardarDescargar?.classList.add('hidden');
      btnDescargarActual?.classList.remove('hidden');
    } else {
      if (pdfModalTitle) pdfModalTitle.textContent = 'Previsualización de la orden (PDF)';
      btnGuardar?.classList.remove('hidden');
      btnGuardarDescargar?.classList.remove('hidden');
      btnDescargarActual?.classList.add('hidden');
    }
  }

  function openPdfModalFromBase64(b64) {
    if (!b64) return;
    setPdfModalMode('preview');
    pdfPreviewFrame.src = 'data:application/pdf;base64,' + b64;
    pdfPreviewModal.classList.remove('hidden');
  }

  function openCurrentPdfModal() {
    setPdfModalMode('current');
    pdfPreviewFrame.src = PDF_CURRENT_URL;
    pdfPreviewModal.classList.remove('hidden');
  }

  function closePdfModal() {
    pdfPreviewModal.classList.add('hidden');
    pdfPreviewFrame.src = 'about:blank';
  }

  function openStockShortageModal(items) {
    shortageListEl.innerHTML = '';
    if (Array.isArray(items)) {
      items.forEach(it => {
        const li = document.createElement('li');
        li.textContent = `${it.nombre || ('Producto '+it.codigo_producto)} — requerido: ${it.requerido}, disponible: ${it.disponible}, faltante: ${it.faltante}`;
        shortageListEl.appendChild(li);
      });
    }
    stockShortageModal.classList.remove('hidden');
  }

  function closeStockShortageModal() {
    stockShortageModal.classList.add('hidden');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const btnPreview          = document.getElementById('btnPreview');
    const btnPdfActual        = document.getElementById('btnPdfActual');
    const btnGuardarLocal     = document.getElementById('btnGuardar');
    const btnGuardarDescLocal = document.getElementById('btnGuardarDescargar');
    const btnDescargarActualLocal = document.getElementById('btnDescargarActual');

    btnPreview?.addEventListener('click', (e) => {
      e.preventDefault();
      const comp = window.getFormComponent ? window.getFormComponent() : null;
      if (comp && typeof comp.previewPdf === 'function') comp.previewPdf();
    });

    btnPdfActual?.addEventListener('click', (e) => {
      e.preventDefault();
      openCurrentPdfModal();
    });

    btnGuardarLocal?.addEventListener('click', (e) => {
      e.preventDefault();
      const comp = window.getFormComponent ? window.getFormComponent() : null;
      if (comp && typeof comp.saveOrden === 'function') comp.saveOrden(false);
    });

    btnGuardarDescLocal?.addEventListener('click', (e) => {
      e.preventDefault();
      const comp = window.getFormComponent ? window.getFormComponent() : null;
      if (comp && typeof comp.saveOrden === 'function') comp.saveOrden(true);
    });

    btnDescargarActualLocal?.addEventListener('click', (e) => {
      e.preventDefault();
      window.open(PDF_CURRENT_URL + '?download=1', '_blank');
    });
  });

  window.formOrdenServicio = formOrdenServicio;
  window.closePdfModal = closePdfModal;
  window.closeStockShortageModal = closeStockShortageModal;
})();
</script>
@endsection
