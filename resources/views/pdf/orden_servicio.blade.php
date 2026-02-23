<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden • PDF</title>
    <style>
        /* ===== Layout general ===== */
        body{
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            margin: 20px 35px;
            line-height: 1.35;
        }

        /* ===== Encabezado (igual estilo que cotización) ===== */
        .header{ width:100%; margin-bottom:10px; }
        .barra-superior{ width:100%; height:8px; margin-bottom:4px; }

        .tabla-header{ width:100%; border-collapse:collapse; }
        .tabla-header td{ border:none; vertical-align:middle; }
        .td-logo{ width:50%; }
        .td-info{ width:50%; text-align:right; }
        .logo{ height:52px; }

        .info-empresa{ line-height:1.25; }
        .info-empresa strong{ font-size:13px; font-weight:700; }
        .info-empresa span{ display:block; font-size:10px; }

        /* ===== Panel gris con datos de la orden ===== */
        .panel-orden{
            margin-top: 12px;
            background-color:#e3e3e3;
            padding: 8px 12px 10px 12px;
        }

        .tabla-orden{
            width:100%;
            border-collapse:collapse;
            font-size:10px;
        }
        .tabla-orden th, .tabla-orden td{
            padding:3px 4px;
            border:none;
            vertical-align:top;
        }
        .enc-titulo, .enc-numero{
            background-color:#cfcfcf;
            font-weight:bold;
            font-size:11px;
            padding-top:4px;
            padding-bottom:4px;
        }
        .enc-titulo{ color:#0072bc; text-align:left; }
        .enc-numero{ text-align:left; }

        .enc-label{
            font-weight:bold;
            color:#555;
            width:90px;
            white-space:nowrap;
        }
        .enc-valor{ color:#000; }

        /* ===== Tablas secciones ===== */
        .tabla-seccion{
            width:100%;
            border-collapse:collapse;
            margin-top:14px;
            table-layout:fixed;
            font-size:10px;
        }
        .tabla-seccion th, .tabla-seccion td{
            padding:6px;
            text-align:left;
            vertical-align:top;
            border:1px solid #ffffff; /* bordes blancos (sin contorno visible) */
        }
        .tabla-seccion th{
            background-color:#e3e3e3;
            color:#0072bc;
            font-weight:bold;
        }
        .tabla-seccion td{ background-color:#ffffff; }
        .tabla-seccion th.right, .tabla-seccion td.right{ text-align:right; }
        .tabla-seccion td.center{ text-align:center; }

        /* ===== Utilidades ===== */
        .muted{ color:#555; font-size:9px; }
        .wrap{ word-break:break-word; white-space:normal; }

        /* ===== Tabla de productos ===== */
        .w-desc  { width:55%; }
        .w-cant  { width:15%; }
        .w-pre   { width:15%; }
        .w-total { width:15%; }

        /* ===== Totales ===== */
        .tabla-totales{
            width:100%;
            border-collapse:collapse;
            margin-top:4px;
            font-size:10px;
        }
        .tabla-totales th, .tabla-totales td{
            padding:4px 6px;
            vertical-align:top;
            border:none;
            background-color:transparent;
        }
        .tabla-totales th{ text-align:right; font-weight:bold; }
        .tabla-totales td{ text-align:right; }
        .tabla-totales .muted{ text-align:right; }

        .totales-panel{
            width:100%;
            background-color:#e3e3e3;
            border-top:3px solid #0072bc;
            border-bottom:3px solid #0072bc;
            padding:8px 14px 10px 14px;
        }

        .totales-firma-block{
            margin-top:14px;
            page-break-inside: avoid;
        }

        /* ===== Firma ===== */
        .firma-layout{
            width:100%;
            margin-top:20px;
            border-collapse:collapse;
        }
        .firma-layout td{
            border:none;
            vertical-align:top;
            background-color:transparent;
        }
        .firma-col-text{ width:55%; padding-right:10px; }
        .firma-col-sign{ width:45%; text-align:center; }

        .firma-texto{ font-size:10px; text-align:justify; }

        .firma-section{ font-size:10px; text-align:center; }
        .firma-section p{ margin:0 0 8px 0; }

        .firma-img{
            margin-top:6px;
            max-height:80px;
            display:inline-block;
        }
        .firma-line{
            margin-top:30px;
            width:220px;
            border-top:1px solid #000;
            margin-left:auto;
            margin-right:auto;
        }
        .firma-nombre{ margin-top:6px; font-weight:bold; }
        .firma-puesto, .firma-empresa{ margin-top:2px; }

        /* dompdf */
        thead{ display:table-header-group; }
        tfoot{ display:table-footer-group; }
        tr, img{ page-break-inside:avoid; }
    </style>
</head>
<body>

@php
    use Illuminate\Support\Str;

    // ===== LOGO / BARRA =====
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');
    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
    if (file_exists($barraPath)) {
        $barraBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($barraPath));
    }

    // ===== Productos a renderizar =====
    if (isset($productos)) {
        $productosRender = collect($productos)->map(fn($p) => is_array($p) ? (object)$p : $p);
    } else {
        $productosRender = collect($orden->productos ?? []);
    }

    $clienteRender = isset($cliente) ? $cliente : ($orden->cliente ?? null);

    // ===== Moneda y fecha =====
    $moneda = strtoupper((string)($orden->moneda ?? 'MXN'));
    $simboloMoneda = ($moneda === 'USD') ? 'USD $' : 'MXN $';
    $fechaMostrar = \Carbon\Carbon::parse($orden->fecha_orden ?? $orden->created_at ?? now())->format('d/m/Y');

    // ===== Título dinámico =====
    $tipo = (string)($orden->tipo_orden ?? '');
    $isCompra = $tipo === 'compra';
    $tituloDocumento = $isCompra ? 'ORDEN DE COMPRA' : 'ORDEN DE SERVICIO';
    $prefijo = $isCompra ? 'OC' : 'OS';

    // Folio
    $folio = $orden->folio ?? $orden->id_orden_servicio ?? $orden->id ?? '—';

    // ===== Técnicos =====
    $tecnicosLista = [];
    if (optional($orden->tecnico)->name) $tecnicosLista[] = $orden->tecnico->name;

    foreach (['tecnicosAsignados','tecnicos','users','tecnicos_multiples','tecnicosVinculados'] as $rel) {
        if (isset($orden->$rel)) {
            $col = $orden->$rel;
            if ($col instanceof \Illuminate\Support\Collection) {
                foreach ($col as $t) {
                    $nm = $t->name ?? null;
                    if ($nm && !in_array($nm, $tecnicosLista, true)) $tecnicosLista[] = $nm;
                }
            }
        }
    }
    $tecnicosTexto = count($tecnicosLista) ? implode(', ', $tecnicosLista) : '—';

    // Quitar NS: del texto para no duplicar (los N/S se muestran aparte)
    $descripcionSinNS = function (?string $texto) {
        if (!$texto) return '';
        $limpio = preg_replace('/\s*NS:\s*.+$/mi', '', $texto);
        return trim((string)($limpio ?? ''));
    };


    // Extraer N/S desde texto (fallback legacy: cuando vienen embebidos como "NS: 123, 456")
    $extraerNS = function (?string $texto) {
        if (!$texto) return [];
        if (!preg_match('/NS:\s*(.+)$/mi', (string)$texto, $m)) return [];
        $list = array_map('trim', explode(',', $m[1] ?? ''));
        return array_values(array_filter($list, function ($s) {
            return $s !== '';
        }));
    };

    // ===== Firma digital =====
    $firmaData    = $firma ?? null;
    $firmaNombre  = is_array($firmaData) ? ($firmaData['nombre'] ?? null)  : ($firmaData->nombre  ?? null);
    $firmaPuesto  = is_array($firmaData) ? ($firmaData['puesto'] ?? null)  : ($firmaData->puesto  ?? null);
    $firmaEmpresa = is_array($firmaData) ? ($firmaData['empresa'] ?? null) : ($firmaData->empresa ?? null);
    $firmaImagen  = is_array($firmaData) ? ($firmaData['image'] ?? null)   : ($firmaData->image   ?? null);

    // ===== Materiales extra (no previstos) =====
    $extras = collect();
    try {
        $extras = collect($orden->materialesExtras ?? []);
    } catch (\Throwable $e) {
        $extras = collect();
    }

    // total adicional en moneda de la orden
    $totalAdicional = 0.0;
    try {
        $totalAdicional = (float) ($orden->total_adicional ?? 0);
    } catch (\Throwable $e) {
        $mxn = (float) ($orden->total_adicional_mxn ?? 0);
        $tc  = (float) ($orden->tasa_cambio ?? 1);
        $totalAdicional = ($moneda === 'USD' && $tc > 0) ? round($mxn / $tc, 2) : round($mxn, 2);
    }
@endphp

{{-- ===== ENCABEZADO ===== --}}
<div class="header">
    @if($barraBase64)
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    @endif

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo" alt="">
                @endif
            </td>
            <td class="td-info">
                <div class="info-empresa">
                    <strong>E-SUPPORT QUERÉTARO</strong>
                    <span>Jose Alberto Rivera Rodríguez</span>
                    <span>RFC: RIRA781030RI8</span>
                    <span>Av. Emeterio González No. 27 int. 2</span>
                    <span>Hércules, Querétaro, Qro. C.P. 76069</span>
                    <span>Cel: 442-169-7094</span>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- PANEL GRIS: DATOS DE LA ORDEN --}}
<div class="panel-orden">
    <table class="tabla-orden">
        <tr>
            <th class="enc-titulo" colspan="3">{{ $tituloDocumento }} NO.</th>
            <th class="enc-numero" colspan="3">{{ $prefijo }}-{{ $folio }}</th>
        </tr>
        <tr>
            <td class="enc-label">Fecha:</td>
            <td class="enc-valor">{{ $fechaMostrar }}</td>
            <td class="enc-label">Tipo de Orden:</td>
            <td class="enc-valor">{{ ucfirst(str_replace('_',' ',$orden->tipo_orden)) }}</td>
            <td class="enc-label">Prioridad:</td>
            <td class="enc-valor">{{ $orden->prioridad }}</td>
        </tr>
        <tr>
            <td class="enc-label">Técnicos:</td>
            <td class="enc-valor wrap" colspan="3">{{ $tecnicosTexto }}</td>
            <td class="enc-label">Estado:</td>
            <td class="enc-valor">{{ $orden->estado }}</td>
        </tr>
        <tr>
            <td class="enc-label">Moneda:</td>
            <td class="enc-valor">
                {{ $moneda }}
                @if($moneda === 'USD' && !empty($orden->tasa_cambio))
                    <span class="muted"> | TC: 1 USD = {{ number_format((float)$orden->tasa_cambio, 4) }} MXN</span>
                @endif
            </td>
            <td class="enc-label">Forma de pago:</td>
            <td class="enc-valor">
                {{ $orden->tipo_pago ? ucfirst(str_replace('_',' ', $orden->tipo_pago)) : '—' }}
            </td>
            <td class="enc-label">Folio interno:</td>
            <td class="enc-valor">{{ $prefijo }}-{{ $folio }}</td>
        </tr>
    </table>
</div>

{{-- DATOS DEL CLIENTE --}}
<table class="tabla-seccion">
    <thead>
        <tr><th colspan="2">Datos del Cliente</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Nombre:</strong> {{ $clienteRender->nombre ?? '—' }}</td>
            <td><strong>Empresa:</strong> {{ $clienteRender->nombre_empresa ?? '—' }}</td>
        </tr>
        <tr>
            <td>
                <strong>Dirección Fiscal:</strong>
                {!! isset($clienteRender->direccion_fiscal) ? nl2br(e($clienteRender->direccion_fiscal)) : '—' !!}
            </td>
            <td><strong>Correo:</strong> {{ $clienteRender->correo_electronico ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Teléfono:</strong> {{ $clienteRender->telefono ?? '—' }}</td>
            <td><strong>Ubicación:</strong> {{ $clienteRender->ubicacion ?? '—' }}</td>
        </tr>
    </tbody>
</table>

{{-- SERVICIO --}}
<table class="tabla-seccion">
    <thead>
        <tr>
            <th style="width:75%;">Servicio</th>
            <th class="right" style="width:25%;">Precio</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="wrap">{!! nl2br(e($orden->servicio ?: '—')) !!}</td>
            <td class="right">{{ $simboloMoneda }}{{ number_format((float)($orden->precio ?? 0), 2) }}</td>
        </tr>
    </tbody>
</table>

@if(!empty($orden->descripcion_servicio))
    <table class="tabla-seccion">
        <thead><tr><th>Descripción del servicio</th></tr></thead>
        <tbody><tr><td class="wrap">{!! nl2br(e($orden->descripcion_servicio)) !!}</td></tr></tbody>
    </table>
@endif

@if(!empty($orden->descripcion))
    <table class="tabla-seccion">
        <thead><tr><th>Notas / Descripción general</th></tr></thead>
        <tbody><tr><td class="wrap">{!! nl2br(e($orden->descripcion)) !!}</td></tr></tbody>
    </table>
@endif

{{-- MATERIALES / PRODUCTOS --}}
<table class="tabla-seccion">
    <thead>
        <tr>
            <th class="w-desc">Descripción</th>
            <th class="w-cant right">Cantidad</th>
            <th class="w-pre right">Precio Unit</th>
            <th class="w-total right">Total Línea</th>
        </tr>
    </thead>
    <tbody>
        @php $materialBruto = 0.0; @endphp
        @forelse ($productosRender as $producto)
            @php
                $qty       = (float)($producto->cantidad ?? 0);
                $pu        = (float)($producto->precio_unitario ?? ($producto->precio ?? 0));
                $lineBruto = $qty * $pu;
                $materialBruto += $lineBruto;

                $nombreMostrar = $producto->nombre_producto ?? ($producto->descripcion ?? 'Producto');
                $descMostrar   = $descripcionSinNS($producto->descripcion ?? null);
                $serialsMostrar = [];
                if (isset($producto->ns_asignados)) {
                    if (is_array($producto->ns_asignados)) {
                        $serialsMostrar = $producto->ns_asignados;
                    } elseif (is_string($producto->ns_asignados) && $producto->ns_asignados !== '') {
                        $serialsMostrar = array_map('trim', explode(',', $producto->ns_asignados));
                    }
                }
                if (empty($serialsMostrar)) {
                    $serialsMostrar = $extraerNS($producto->descripcion ?? '');
                }
                $serialsMostrar = array_values(array_filter(array_map('trim', (array)$serialsMostrar), function ($s) {
                    return $s !== '';
                }));

            @endphp
            <tr>
                <td class="wrap">
                    <strong>{{ $nombreMostrar }}</strong>
                    @if($descMostrar !== '')
                        <div class="muted">{!! nl2br(e($descMostrar)) !!}</div>
                    @endif
                    @if(!empty($serialsMostrar))
                        <div class="muted wrap"><strong>N/S:</strong> {{ implode(', ', $serialsMostrar) }}</div>
                    @endif
                </td>
                <td class="right">{{ number_format($qty, 2) }}</td>
                <td class="right">{{ $simboloMoneda }}{{ number_format($pu, 2) }}</td>
                <td class="right">{{ $simboloMoneda }}{{ number_format($lineBruto, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No se registraron materiales/productos.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- MATERIALES EXTRA / NO PREVISTOS --}}
@php
    $extrasTotal = 0.0;
    $extrasRows = collect();

    if ($extras instanceof \Illuminate\Support\Collection && $extras->count()) {
        $extrasRows = $extras;
        foreach ($extrasRows as $x) {
            $c = (float)($x->cantidad ?? 0);
            $pu = (float)($x->precio_unitario ?? ($x->precio ?? 0));
            $extrasTotal += ($c * $pu);
        }
    } else {
        $extrasTotal = (float) $totalAdicional;
    }
@endphp

@if($extrasTotal > 0 || ($extrasRows instanceof \Illuminate\Support\Collection && $extrasRows->count()))
    <table class="tabla-seccion">
        <thead>
            <tr>
                <th class="w-desc">Materiales extra / No previstos</th>
                <th class="w-cant right">Cantidad</th>
                <th class="w-pre right">Precio Unit</th>
                <th class="w-total right">Total Línea</th>
            </tr>
        </thead>
        <tbody>
            @if($extrasRows->count())
                @foreach($extrasRows as $x)
                    @php
                        $xDesc = (string)($x->descripcion ?? $x->nombre ?? 'Material extra');
                        $xQty  = (float)($x->cantidad ?? 0);
                        $xPU   = (float)($x->precio_unitario ?? ($x->precio ?? 0));
                        $xTot  = $xQty * $xPU;
                    @endphp
                    <tr>
                        <td class="wrap"><strong>{{ $xDesc }}</strong></td>
                        <td class="right">{{ number_format($xQty, 2) }}</td>
                        <td class="right">{{ $simboloMoneda }}{{ number_format($xPU, 2) }}</td>
                        <td class="right">{{ $simboloMoneda }}{{ number_format($xTot, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td class="wrap"><strong>Total adicional</strong></td>
                    <td class="right">—</td>
                    <td class="right">—</td>
                    <td class="right">{{ $simboloMoneda }}{{ number_format((float)$extrasTotal, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endif

@php
    // ===== TOTALES (incluyen material + servicio + extras + IVA + operativo) =====
    $costoServicio = (float)($orden->precio ?? 0);
    $costoEnvio    = (float)($orden->costo_operativo ?? 0);

    $baseGravable  = (float)$materialBruto + (float)$costoServicio + (float)$extrasTotal;

    $ivaCalc = round($baseGravable * 0.16, 2);

    $ivaGuardado = (float)($orden->impuestos ?? 0);
    $iva = (abs($ivaGuardado - $ivaCalc) < 0.02) ? $ivaGuardado : $ivaCalc;

    $subtotal = $baseGravable + $costoEnvio;
    $totalFinal = round($subtotal + $iva, 2);

    // ===== Anticipo / saldo (en moneda de la orden) =====
    $anticipoOrden = 0.0;
    try {
        $anticipoOrden = (float)($orden->anticipo ?? 0);
    } catch (\Throwable $e) {
        $mxn = (float)($orden->anticipo_mxn ?? 0);
        $tc  = (float)($orden->tasa_cambio ?? 1);
        $anticipoOrden = ($moneda === 'USD' && $tc > 0) ? round($mxn / $tc, 2) : round($mxn, 2);
    }

    $anticipoOrden = max(round($anticipoOrden, 2), 0);
    if ($anticipoOrden > $totalFinal) $anticipoOrden = $totalFinal;

    $saldoPendiente = max(round($totalFinal - $anticipoOrden, 2), 0);

    $pctGuardado = (float)($orden->anticipo_porcentaje ?? 0);
    $pctCalc = ($totalFinal > 0) ? round(($anticipoOrden / $totalFinal) * 100, 2) : 0;
    $pctMostrar = ($pctGuardado > 0) ? round($pctGuardado, 2) : $pctCalc;

    $tcInfo = (float)($orden->tasa_cambio ?? 0);
    $totalFinalMXN = ($moneda === 'USD' && $tcInfo > 0) ? round($totalFinal * $tcInfo, 2) : null;
    $saldoMXN      = ($moneda === 'USD' && $tcInfo > 0) ? round($saldoPendiente * $tcInfo, 2) : null;
@endphp

{{-- BLOQUE: TOTALES + FIRMA --}}
<div class="totales-firma-block">
    <div class="totales-panel">
        <table class="tabla-totales">
            <tr>
                <th style="width:84%;">Costo operativo / envío:</th>
                <td style="width:16%;">{{ $simboloMoneda }}{{ number_format($costoEnvio, 2) }}</td>
            </tr>

            <tr>
                <th>Total materiales:</th>
                <td>{{ $simboloMoneda }}{{ number_format($materialBruto, 2) }}</td>
            </tr>

            @if($extrasTotal > 0)
                <tr>
                    <th>Total materiales extra:</th>
                    <td>{{ $simboloMoneda }}{{ number_format((float)$extrasTotal, 2) }}</td>
                </tr>
            @endif

            <tr>
                <th>Costo del servicio:</th>
                <td>{{ $simboloMoneda }}{{ number_format($costoServicio, 2) }}</td>
            </tr>

            <tr>
                <th>Base gravable (Mat. + Extra + Serv.):</th>
                <td>{{ $simboloMoneda }}{{ number_format($baseGravable, 2) }}</td>
            </tr>

            <tr>
                <th>IVA (16%):</th>
                <td>{{ $simboloMoneda }}{{ number_format($iva, 2) }}</td>
            </tr>

            <tr>
                <th>Total final (incluye envío):</th>
                <td><strong>{{ $simboloMoneda }}{{ number_format($totalFinal, 2) }}</strong></td>
            </tr>

            @if($anticipoOrden > 0 || $pctMostrar > 0)
                <tr>
                    <th>Anticipo ({{ number_format($pctMostrar, 2) }}%):</th>
                    <td><strong>{{ $simboloMoneda }}{{ number_format($anticipoOrden, 2) }}</strong></td>
                </tr>
                <tr>
                    <th>Saldo pendiente:</th>
                    <td><strong>{{ $simboloMoneda }}{{ number_format($saldoPendiente, 2) }}</strong></td>
                </tr>
            @endif

            @if($moneda === 'USD' && $tcInfo > 0)
                <tr>
                    <td colspan="2" class="muted">
                        Nota: TC al registrar: 1 USD = {{ number_format($tcInfo, 4) }} MXN
                        @if($totalFinalMXN !== null)
                            | Total aprox. MXN: <strong>MXN ${{ number_format($totalFinalMXN, 2) }}</strong>
                            @if($saldoMXN !== null && ($anticipoOrden > 0 || $pctMostrar > 0))
                                | Saldo aprox. MXN: <strong>MXN ${{ number_format($saldoMXN, 2) }}</strong>
                            @endif
                        @endif
                    </td>
                </tr>
            @endif

            @if(abs(((float)($orden->impuestos ?? 0)) - $ivaCalc) >= 0.02)
                <tr>
                    <td colspan="2" class="muted">
                        * IVA recalculado en PDF (incluye materiales extra). IVA guardado en BD: {{ $simboloMoneda }}{{ number_format((float)($orden->impuestos ?? 0), 2) }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Texto + firma --}}
    <table class="firma-layout">
        <tr>
            <td class="firma-col-text">
                <div class="firma-texto">
                    <p>
                        La presente orden ampara los servicios y/o materiales descritos.
                        Cualquier cambio deberá ser notificado y autorizado por escrito.
                    </p>

                    @if(($orden->tipo_pago ?? null) === 'credito_cliente')
                        <p class="muted" style="margin-top:8px;">
                            Forma de pago: Crédito cliente. El cargo al crédito corresponde al saldo pendiente.
                        </p>
                    @endif
                </div>
            </td>
            <td class="firma-col-sign">
                @php
                    $firmaBase64 = $firma_base64 ?? ($orden->firma_base64 ?? null);

                    if ($firmaBase64 && strpos($firmaBase64, 'data:image/') !== 0) {
                        if (strpos($firmaBase64, 'base64,') === false) {
                            $firmaBase64 = 'data:image/png;base64,' . $firmaBase64;
                        }
                    }

                    $firmaFromModel = null;
                    if (!empty($orden->firma_resp_data)) $firmaFromModel = 'data:image/png;base64,' . $orden->firma_resp_data;
                    elseif (!empty($orden->firma_emp_data)) $firmaFromModel = 'data:image/png;base64,' . $orden->firma_emp_data;

                    $firmaRel  = $orden->firma_conformidad ?? $orden->firma_path ?? $orden->firma_resp_path ?? $orden->firma_emp_path ?? null;
                    $firmaPath = $firmaRel ? storage_path('app/' . ltrim($firmaRel, '/')) : null;
                    $firmaFileSrc = ($firmaPath && file_exists($firmaPath)) ? ('file://' . $firmaPath) : null;
                @endphp

                <div class="firma-section">
                    <p>Firma de autorización</p>

                    @if($firmaImagen)
                        <img src="{{ $firmaImagen }}" class="firma-img" alt="Firma digital">
                    @elseif($firmaBase64)
                        <img src="{{ $firmaBase64 }}" class="firma-img" alt="Firma">
                    @elseif($firmaFromModel)
                        <img src="{{ $firmaFromModel }}" class="firma-img" alt="Firma">
                    @elseif($firmaFileSrc)
                        <img src="{{ $firmaFileSrc }}" class="firma-img" alt="Firma">
                    @else
                        <div class="firma-line"></div>
                    @endif

                    @if($firmaNombre)
                        <div class="firma-nombre">{{ $firmaNombre }}</div>
                    @endif
                    @if($firmaPuesto)
                        <div class="firma-puesto muted">{{ $firmaPuesto }}</div>
                    @endif
                    @if($firmaEmpresa)
                        <div class="firma-empresa muted">{{ $firmaEmpresa }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
