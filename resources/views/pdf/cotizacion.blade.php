<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización</title>

    <style>
        /* ===== Layout general ===== */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            margin: 20px 35px;
            padding: 0;
            background-color: #ffffff;
        }

        .page-wrap {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ===== Encabezado ===== */
        .header {
            width: 100%;
            margin-bottom: 6px;
        }

        .barra-superior {
            width: 100%;
            height: 8px;
            margin-bottom: 4px;
        }

        .tabla-header {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-header td {
            border: none;
            vertical-align: middle;
            padding: 0;
        }

        .td-logo {
            width: 50%;
        }

        .td-info {
            width: 50%;
            text-align: right;
        }

        .logo {
            height: 52px;
        }

        .info-empresa {
            line-height: 1.25;
        }

        .info-empresa strong {
            font-size: 13px;
            font-weight: 700;
        }

        .info-empresa span {
            display: block;
            font-size: 10px;
        }

        /* ===== Separador ===== */
        .separador-azul {
            width: 100%;
            height: 4px;
            background-color: #0072bc;
            margin: 0;
            padding: 0;
        }

        /* ===== Panel gris superior ===== */
        .panel-encabezado {
            width: 100%;
            margin-top: 14px;
            background-color: #e3e3e3;
            padding: 0; /* IMPORTANTE: sin padding para evitar desfase */
            border: none;
        }

        .tabla-encabezado {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }

        .tabla-encabezado th,
        .tabla-encabezado td {
            padding: 4px 12px;
            border: none;
            vertical-align: top;
        }

        .enc-titulo,
        .enc-numero {
            background-color: #cfcfcf;
            font-weight: bold;
            font-size: 11px;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        .enc-titulo {
            color: #0072bc;
            text-align: left;
        }

        .enc-numero {
            text-align: left;
            color: #222222;
        }

        .enc-label {
            font-weight: bold;
            color: #555555;
            white-space: nowrap;
        }

        .enc-valor {
            color: #000000;
        }

        .enc-bloque {
            font-weight: bold;
            color: #0072bc;
            padding-top: 8px;
            padding-bottom: 2px;
        }

        .enc-solicita-label {
            font-weight: bold;
            color: #0072bc;
            text-align: center;
            white-space: nowrap;
        }

        .enc-solicita-valor {
            text-align: right;
            font-weight: bold;
            color: #222222;
        }

        /* ===== Tabla de productos SIN bordes ===== */
        .tabla-productos {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 12px;
            font-size: 9.6px;
        }

        .tabla-productos th,
        .tabla-productos td {
            border: none; /* sin bordes */
            padding: 4px 6px;
            vertical-align: top;
            background-color: #ffffff;
        }

        .tabla-productos th {
            color: #0072bc;
            font-weight: bold;
            text-align: left;
        }

        .tabla-productos th.cant,
        .tabla-productos td.cant {
            width: 11%;
            text-align: center;
            white-space: nowrap;
        }

        .tabla-productos th.descripcion-col,
        .tabla-productos td.descripcion-col {
            width: 57%;
            text-align: left;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            line-height: 1.25;
        }

        .tabla-productos th.unit-price,
        .tabla-productos td.unit-price {
            width: 16%;
            text-align: right;
            white-space: nowrap;
            font-size: 8.8px;
        }

        .tabla-productos th.total-col,
        .tabla-productos td.total-col {
            width: 16%;
            text-align: right;
            white-space: nowrap;
            font-size: 8.8px;
        }

        .tabla-productos th.unit-price,
        .tabla-productos th.total-col {
            line-height: 1.1;
        }

        .descripcion-producto {
            font-size: 9px;
            color: #333333;
            margin-top: 2px;
            line-height: 1.25;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        /* ===== Panel gris inferior ===== */
        .panel-inferior {
            width: 100%;
            margin-top: 14px;
            background-color: #e3e3e3;
            padding: 0; /* IMPORTANTE: sin padding para evitar desfase */
            font-size: 9.8px;
            border-top: 3px solid #0072bc;
            border-bottom: 3px solid #0072bc;
        }

        .tabla-inferior {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .tabla-inferior td {
            padding: 4px 12px;
            border: none;
            vertical-align: top;
        }

        .inf-label {
            font-weight: bold;
            color: #555555;
            white-space: nowrap;
        }

        .inf-valor {
            color: #222222;
        }

        .inf-valor a,
        .inf-valor-link {
            color: #0072bc;
            text-decoration: underline;
            font-weight: bold;
        }

        .tot-label {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
            color: #222222;
        }

        .tot-valor {
            text-align: right;
            white-space: nowrap;
            color: #222222;
        }

        /* ===== Pie / observaciones ===== */
        .footer-text {
            font-size: 10px;
            text-align: left;
        }

        .footer-text p {
            margin: 0;
        }

        .footer-observaciones {
            font-size: 9.5px;
            margin-bottom: 18px;
            color: #222222;
            white-space: pre-line;
        }

        .footer-copy {
            padding-top: 24px;
        }

        /* ===== Firma ===== */
        .firma-layout {
            width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .firma-layout td {
            border: none;
            vertical-align: bottom;
            padding: 0;
        }

        .firma-col-text {
            width: 55%;
            padding-right: 10px;
        }

        .firma-col-sign {
            width: 45%;
            text-align: center;
        }

        .firma-section {
            font-size: 10px;
            text-align: center;
        }

        .firma-section p {
            margin: 0 0 10px 0;
        }

        .firma-img {
            height: 70px;
            display: inline-block;
            margin-bottom: 4px;
        }

        .firma-line {
            width: 200px;
            border-top: 1px solid #000000;
            margin: 0 auto 4px auto;
        }

        .firma-nombre {
            font-weight: bold;
        }

        .firma-puesto,
        .firma-empresa,
        .firma-telefono {
            margin-top: 2px;
        }

        @include('pdf.partials.corporate-theme')
    </style>
</head>
<body>
<div class="page-wrap">
@php
    use Carbon\Carbon;

    /* ============= Moneda y formateo ============= */
    $MONEDA_RAW = $cotizacion->moneda ?? 'MXN';
    $MONEDA = strtoupper(trim((string) $MONEDA_RAW));

    $fmt = function($v) use ($MONEDA) {
        return '$' . number_format((float)$v, 2) . ' ' . $MONEDA;
    };

    /* ============= Funciones para evitar ruptura de tabla ============= */
    $splitLongWord = function ($word, $limit = 38) {
        $word = (string) $word;

        if (function_exists('mb_str_split')) {
            return implode("\n", mb_str_split($word, $limit, 'UTF-8'));
        }

        return trim(chunk_split($word, $limit, "\n"));
    };

    $wrapText = function ($text, $limit = 38) use ($splitLongWord) {
        $text = (string) $text;

        $text = preg_replace_callback('/[^\s]{' . $limit . ',}/u', function ($matches) use ($splitLongWord, $limit) {
            return $splitLongWord($matches[0], $limit);
        }, $text);

        return nl2br(e($text));
    };

    /* ============= Firma ============= */
    $firmaData    = $firma ?? null;
    $firmaNombre  = $firmaData['nombre']  ?? ($firmaData->nombre  ?? null);
    $firmaPuesto  = $firmaData['puesto']  ?? ($firmaData->puesto  ?? null);
    $firmaEmpresa = $firmaData['empresa'] ?? ($firmaData->empresa ?? null);
    $firmaImagen  = $firmaData['image']   ?? ($firmaData->image   ?? null);

    /* ============= Cliente / solicita ============= */
    $solicita = $cotizacion->solicita ?? null;
    if (!$solicita && !empty($cliente)) {
        $solicita = $cliente->nombre;
    }

    $condicionesPagoMap = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'credito_cliente' => 'Credito cliente',
        'credito' => 'Credito cliente',
        'crédito' => 'Credito cliente',
        'contado' => 'Efectivo',
    ];

    $condicionesPagoKey = strtolower(trim((string) ($cotizacion->condiciones_pago ?? 'efectivo')));
    $condicionesPago = $condicionesPagoMap[$condicionesPagoKey] ?? ucfirst($condicionesPagoKey);

    $tiempoEntrega = $cotizacion->tiempo_entrega ?? null;
    $notaFija = $cotizacion->nota_fija ?? 'PRECIOS SUJETOS A CAMBIO SIN PREVIO AVISO';

    $codigoCliente = trim((string) ($cliente->codigo_cliente ?? ''));

    $folioCotizacion = $codigoCliente !== ''
        ? $codigoCliente . ' ' . (string) ($cotizacion->id_cotizacion ?? 'PREVIEW')
        : 'SET-' . (string) ($cotizacion->id_cotizacion ?? 'PREVIEW');

    $fechaCot = $cotizacion->fecha
        ? Carbon::parse($cotizacion->fecha)->format('d/m/Y')
        : Carbon::parse($cotizacion->created_at)->format('d/m/Y');

    $vigenciaFecha = !empty($cotizacion->vigencia)
        ? Carbon::parse($cotizacion->vigencia)->format('d/m/Y')
        : '';

    /* ============= Totales ============= */
    $subtotalMaterial = 0.0;
    foreach ($productos as $p) {
        $qty = (float)($p->cantidad ?? 0);
        $pu  = (float)($p->precio_unitario ?? 0);
        $subtotalMaterial += $qty * $pu;
    }

    $costoServicio = (!empty($servicio) && isset($servicio->precio))
        ? (float)$servicio->precio
        : 0.0;

    $costoOperativo = (float)($cotizacion->costo_operativo ?? 0);

    $subtotalBruto = $subtotalMaterial + $costoServicio;

    $ivaDB = null;
    if (isset($cotizacion->impuestos)) {
        $ivaDB = (float)$cotizacion->impuestos;
    } elseif (isset($cotizacion->iva)) {
        $ivaDB = (float)$cotizacion->iva;
    }

    $ivaCalculado = round($subtotalBruto * 0.16, 2);
    $iva = $ivaDB !== null ? $ivaDB : $ivaCalculado;

    $totalDB = $cotizacion->total ?? null;
    if ($totalDB !== null && $totalDB > 0) {
        $total = (float)$totalDB;
        $subtotalBruto = $total - $iva - $costoOperativo;
    } else {
        $total = $subtotalBruto + $iva + $costoOperativo;
    }

    $cantidadEscrita = $cotizacion->cantidad_escrita ?? '';
    $observacionesPdf = trim((string) ($cotizacion->observaciones_pdf ?? ''));

    $tasaCambioRaw = $cotizacion->tasa_cambio
        ?? $cotizacion->tipo_cambio
        ?? null;

    $tasaCambio = $tasaCambioRaw !== null ? (float)$tasaCambioRaw : null;
    if ($tasaCambio !== null && $tasaCambio <= 0) {
        $tasaCambio = null;
    }

    /* ============= Imágenes ============= */
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath  = public_path('images/logo3.jpg');
    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    }

    if (file_exists($barraPath)) {
        $barraBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($barraPath));
    }
@endphp

{{-- ===== Encabezado: barra azul + logo + datos ===== --}}
@include('pdf.partials.document-header')

{{-- ===== Panel gris de cabecera ===== --}}
<div class="panel-encabezado">
    <table class="tabla-encabezado">
        <colgroup>
            <col style="width: 10%;">
            <col style="width: 28%;">
            <col style="width: 17%;">
            <col style="width: 45%;">
        </colgroup>

        <tr>
            <th class="enc-titulo" colspan="2">
                {{ $pdfTheme['document_title'] ?? 'COTIZACIÓN NO.' }}
            </th>
            <th class="enc-numero" colspan="2">
                {{ $folioCotizacion }}
            </th>
        </tr>

        <tr>
            <td class="enc-label">Fecha:</td>
            <td class="enc-valor">{{ $fechaCot }}</td>
            <td class="enc-solicita-label">SOLICITA</td>
            <td class="enc-solicita-valor">{{ $solicita ?: '-' }}</td>
        </tr>

        @if(!empty($cliente))
            <tr>
                <td class="enc-bloque" colspan="2">EMPRESA</td>
                <td></td>
                <td></td>
            </tr>

            <tr>
                <td class="enc-label">Nombre:</td>
                <td class="enc-valor" colspan="3">
                    {{ $cliente->nombre_empresa ?: ($cliente->nombre ?? '-') }}
                </td>
            </tr>

            <tr>
                <td class="enc-label">Domicilio:</td>
                <td class="enc-valor" colspan="3">
                    {{ $cliente->direccion_fiscal ?? '-' }}
                </td>
            </tr>
        @endif
    </table>
</div>

<div class="separador-azul"></div>

{{-- ===== Tabla de productos SIN bordes ===== --}}
<table class="tabla-productos" cellpadding="0" cellspacing="0">
    <colgroup>
        <col style="width: 11%;">
        <col style="width: 57%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
    </colgroup>

    <thead>
        <tr>
            <th class="cant">Cantidad</th>
            <th class="descripcion-col">Descripción</th>
            <th class="unit-price">Precio<br>unit</th>
            <th class="total-col">Total</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($productos as $producto)
            @php
                $desc = isset($producto->descripcion_item) && trim($producto->descripcion_item) !== ''
                    ? $producto->descripcion_item
                    : '';

                $totalProducto = isset($producto->total)
                    ? $producto->total
                    : ((float)($producto->cantidad ?? 0) * (float)($producto->precio_unitario ?? 0));
            @endphp

            <tr>
                <td class="cant">
                    {{ $producto->cantidad }}
                </td>

                <td class="descripcion-col">
                    {!! $wrapText($producto->nombre_producto ?? '', 38) !!}

                    @if($desc)
                        <div class="descripcion-producto">
                            {!! $wrapText($desc, 48) !!}
                        </div>
                    @endif
                </td>

                <td class="unit-price">
                    {{ $fmt($producto->precio_unitario ?? 0) }}
                </td>

                <td class="total-col">
                    {{ $fmt($totalProducto) }}
                </td>
            </tr>
        @endforeach

        @if(!empty($servicio))
            <tr>
                <td class="cant">1</td>

                <td class="descripcion-col">
                    <strong>Servicio:</strong>

                    @if(!empty($servicio->descripcion))
                        <div class="descripcion-producto">
                            {!! $wrapText($servicio->descripcion, 48) !!}
                        </div>
                    @endif
                </td>

                <td class="unit-price">
                    {{ $fmt($servicio->precio ?? 0) }}
                </td>

                <td class="total-col">
                    {{ $fmt($servicio->precio ?? 0) }}
                </td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ===== Panel inferior ===== --}}
<div class="panel-inferior">
    <table class="tabla-inferior">
        <colgroup>
            <col style="width: 24%;">
            <col style="width: 36%;">
            <col style="width: 20%;">
            <col style="width: 20%;">
        </colgroup>

        <tr>
            <td class="inf-label">Vigencia:</td>
            <td class="inf-valor">{{ $vigenciaFecha ?: '-' }}</td>
            <td class="tot-label">Sub-total</td>
            <td class="tot-valor">{{ $fmt($subtotalBruto) }}</td>
        </tr>

        <tr>
            <td class="inf-label">Moneda:</td>
            <td class="inf-valor">{{ $MONEDA }}</td>
            <td class="tot-label">IVA (16%)</td>
            <td class="tot-valor">{{ $fmt($iva) }}</td>
        </tr>

        <tr>
            <td class="inf-label">Tiempo de entrega:</td>
            <td class="inf-valor">{{ $tiempoEntrega ?: '-' }}</td>
            <td class="tot-label">Total</td>
            <td class="tot-valor"><strong>{{ $fmt($total) }}</strong></td>
        </tr>

        <tr>
            <td class="inf-label">Condiciones de pago:</td>
            <td class="inf-valor" colspan="3">
                <span class="inf-valor-link">{{ $condicionesPago }}</span>
            </td>
        </tr>

        <tr>
            <td class="inf-label">Cantidad en letra:</td>
            <td class="inf-valor" colspan="3">
                <strong>{{ $cantidadEscrita }}</strong>
            </td>
        </tr>

        <tr>
            <td class="inf-label">Nota:</td>
            <td class="inf-valor" colspan="3">
                <strong>{{ $pdfTheme['fixed_note_text'] ?? $notaFija }}</strong>
            </td>
        </tr>

        @if($MONEDA === 'USD' && $tasaCambio)
            <tr>
                <td colspan="4" style="padding-top:6px; font-size:9px; color:#555555;">
                    Nota: TC al registrar: 1 USD = {{ number_format($tasaCambio, 4, '.', ',') }} MXN
                </td>
            </tr>
        @endif
    </table>
</div>

{{-- ===== Texto + firma ===== --}}
<table class="firma-layout">
    <tr>
        <td class="firma-col-text">
            @if($observacionesPdf !== '')
                <div class="footer-observaciones">
                    <strong>{{ $pdfTheme['observations_label'] ?? 'Observaciones' }}:</strong>
                    <div>{!! nl2br(e($observacionesPdf)) !!}</div>
                </div>
            @endif

            <div class="footer-text footer-copy">
                <p>
                    {{ $pdfTheme['closing_text'] ?? 'Sin más por el momento y esperando escuchar pronto de usted, quedo a sus órdenes para cualquier duda o aclaración respecto a esta cotización.' }}
                </p>
            </div>
        </td>

        <td class="firma-col-sign">
            @if($firmaNombre || $firmaPuesto || $firmaEmpresa || $firmaImagen)
                <div class="firma-section">
                    <p>{{ $pdfTheme['signature_heading'] ?? 'Atentamente' }}</p>

                    @if($firmaImagen)
                        <img src="{{ $firmaImagen }}" alt="Firma" class="firma-img">
                    @else
                        <div class="firma-line"></div>
                    @endif

                    @if($firmaNombre)
                        <div class="firma-nombre">{{ $firmaNombre }}</div>
                    @endif

                    @if($firmaPuesto)
                        <div class="firma-puesto">{{ $firmaPuesto }}</div>
                    @endif

                    @if($firmaEmpresa)
                        <div class="firma-empresa">{{ $firmaEmpresa }}</div>
                    @endif

                    <div class="firma-telefono">
                        {{ $pdfTheme['signature_phone'] ?? 'Cel: 442-169-7094' }}
                    </div>
                </div>
            @endif
        </td>
    </tr>
</table>
</div>
</body>
</html>
