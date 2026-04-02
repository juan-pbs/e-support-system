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

        /* Encabezado */
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

        /* Segunda barra azul bajo el bloque gris de cabecera */
        .separador-azul {
            margin-top: 0;
            width: 100%;
            height: 4px;
            background-color: #0072bc;
        }

        /* Bloque gris de cabecera (cotización / empresa / solicita) */
        .panel-encabezado {
            margin-top: 14px;
            background-color: #e3e3e3;
            border: none;
            padding: 8px 12px 10px 12px;
        }

        .tabla-encabezado {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .tabla-encabezado th,
        .tabla-encabezado td {
            padding: 3px 4px;
            border: none;
            vertical-align: top;
        }

        .enc-titulo,
        .enc-numero {
            background-color: #cfcfcf;
            font-weight: bold;
            font-size: 11px;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .enc-titulo {
            color: #0072bc;
            text-align: left;
        }

        .enc-numero {
            text-align: left;
        }

        .enc-label {
            font-weight: bold;
            color: #555555;
            width: 70px;
            white-space: nowrap;
        }

        .enc-valor {
            color: #000000;
        }

        .enc-bloque {
            font-weight: bold;
            color: #0072bc;
            padding-top: 6px;
            padding-bottom: 2px;
        }

        .enc-solicita-label {
            font-weight: bold;
            color: #0072bc;
            text-align: right;
        }

        .enc-solicita-valor {
            text-align: right;
            font-weight: bold;
        }

        /* ===== Tabla de productos SIN contorno ni fondo ===== */
        .tabla-productos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 9.8px;
        }

        .tabla-productos th,
        .tabla-productos td {
            border: none;
            padding: 4px 5px;
            vertical-align: top;
            background-color: #ffffff;
        }

        .tabla-productos th {
            color: #0072bc;
            font-weight: bold;
            text-transform: uppercase;
        }

        .tabla-productos th.cant,
        .tabla-productos th.num {
            text-align: center;
        }

        .tabla-productos td.cant,
        .tabla-productos td.num {
            text-align: center;
        }

        .tabla-productos td.monto {
            text-align: right;
        }

        .descripcion-producto {
            font-size: 9px;
            color: #333333;
            margin-top: 2px;
        }

        /* Bloque gris inferior: vigencia / moneda / condiciones / totales */
        .panel-inferior {
            margin-top: 18px;
            background-color: #e3e3e3;
            padding: 6px 12px 8px 12px;
            font-size: 9.8px;
            border-top: 3px solid #0072bc;     /* borde azul al LÍMITE superior */
            border-bottom: 3px solid #0072bc;  /* borde azul al LÍMITE inferior */
        }

        .tabla-inferior {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-inferior td {
            padding: 3px 4px;
            border: none;
            vertical-align: top;
        }

        .inf-label {
            font-weight: bold;
            color: #555555;
            width: 110px;
            white-space: nowrap;
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
        }

        .tot-valor {
            text-align: right;
        }

        /* Texto de cierre */
        .footer-text {
            font-size: 10px;
            text-align: center;
        }

        .footer-text p {
            margin: 0;
        }

        /* Layout de texto + firma al final */
        .firma-layout {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }

        .firma-layout td {
            border: none;
            vertical-align: top;
        }

        .firma-col-text {
            width: 55%;
            padding-right: 10px;
        }

        .firma-col-sign {
            width: 45%;
            text-align: center;
        }

        /* Bloque de firma dentro de la columna derecha */
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
            width: 220px;
            border-top: 1px solid #000;
            margin: 0 auto 3px auto;
        }

        .firma-nombre {
            font-weight: bold;
        }

        .firma-puesto,
        .firma-empresa,
        .firma-telefono {
            margin-top: 1px;
        }
        @include('pdf.partials.corporate-theme')
</style>
</head>
<body>
@php
    use Carbon\Carbon;

    /* ============= Moneda y formateo ============= */
    $MONEDA_RAW = $cotizacion->moneda ?? 'MXN';
    $MONEDA = strtoupper(trim((string) $MONEDA_RAW));

    // Formato: $18.09 USD
    $fmt = function($v) use ($MONEDA) {
        return '$' . number_format((float)$v, 2) . ' ' . $MONEDA;
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

    $condicionesPago = $cotizacion->condiciones_pago ?? 'CRÉDITO';

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

    $fechaCot = $cotizacion->fecha
        ? Carbon::parse($cotizacion->fecha)->format('d/m/Y')
        : Carbon::parse($cotizacion->created_at)->format('d/m/Y');

    $vigenciaFecha = !empty($cotizacion->vigencia)
        ? Carbon::parse($cotizacion->vigencia)->format('d/m/Y')
        : '';

    /* ============= Totales (como en orden de servicio) ============= */

    // Subtotal de productos
    $subtotalMaterial = 0.0;
    foreach ($productos as $p) {
        $qty = (float)($p->cantidad ?? 0);
        $pu  = (float)($p->precio_unitario ?? 0);
        $subtotalMaterial += $qty * $pu;
    }

    // Servicio (si existe)
    $costoServicio = (!empty($servicio) && isset($servicio->precio))
        ? (float)$servicio->precio
        : 0.0;

    // Costo operativo / envío
    $costoOperativo = (float)($cotizacion->costo_operativo ?? 0);

    // Subtotal gravable = productos + servicio
    $subtotalBruto = $subtotalMaterial + $costoServicio;

    // IVA: usamos lo almacenado si existe, si no lo calculamos
    $ivaDB = null;
    if (isset($cotizacion->impuestos)) {
        $ivaDB = (float)$cotizacion->impuestos;
    } elseif (isset($cotizacion->iva)) {
        $ivaDB = (float)$cotizacion->iva;
    }

    $ivaCalculado = round($subtotalBruto * 0.16, 2);
    $iva = $ivaDB !== null ? $ivaDB : $ivaCalculado;

    // Total: usamos el de BD si está, si no lo calculamos
    $totalDB = $cotizacion->total ?? null;
    if ($totalDB !== null && $totalDB > 0) {
        $total = (float)$totalDB;
        // Ajustamos el subtotal para que cuadre con lo guardado
        $subtotalBruto = $total - $iva - $costoOperativo;
    } else {
        $total = $subtotalBruto + $iva + $costoOperativo;
    }

    $cantidadEscrita = $cotizacion->cantidad_escrita ?? '';

    // Tasa de cambio (aceptamos tasa_cambio o tipo_cambio)
    $tasaCambioRaw = $cotizacion->tasa_cambio
        ?? $cotizacion->tipo_cambio
        ?? null;

    $tasaCambio = $tasaCambioRaw !== null ? (float)$tasaCambioRaw : null;
    if ($tasaCambio !== null && $tasaCambio <= 0) {
        $tasaCambio = null;
    }

    /* ============= Imágenes (logo / barra) ============= */
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

{{-- ===== Encabezado: barra azul + logo + datos en tabla ===== --}}
<div class="header">
    @if($barraBase64)
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    @else
        <table class="barra-fallback" role="presentation"><tr><td></td></tr></table>
    @endif

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo" alt="">
                @else
                    <div class="logo-fallback">
                        <strong>E-SUPPORT QUERETARO</strong>
                        <span>Soporte y servicio tecnico</span>
                    </div>
                @endif
            </td>
            <td class="td-info">
                <div class="info-empresa">
                    <strong>E-SUPPORT QUERÉTARO</strong>
                    <span>Jose Alberto Rivera Rodríguez</span>
                    <span>RFC: RIRA781030RI8</span>
                    <span>Av. Emeterio González No. 27 int. 2</span>
                    <span>Hércules, Querétaro, Qro. C.P. 76069</span>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ===== Panel gris de cabecera (COTIZACIÓN / EMPRESA / SOLICITA) ===== --}}
<div class="panel-encabezado">
    <table class="tabla-encabezado">
        <tr>
            <th class="enc-titulo" colspan="2">COTIZACIÓN NO.</th>
            <th class="enc-numero" colspan="2">SET-{{ $cotizacion->id_cotizacion }}</th>
        </tr>
        <tr>
            <td class="enc-label">Fecha:</td>
            <td class="enc-valor">{{ $fechaCot }}</td>
            <td class="enc-solicita-label">SOLICITA</td>
            <td class="enc-solicita-valor">
                {{ $solicita ?: '-' }}
            </td>
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

{{-- ===== Tabla de productos ===== --}}
<table class="tabla-productos">
    <thead>
        <tr>
            <th class="cant" style="width: 70px;">CANTIDAD</th>
            <th>DESCRIPCIÓN</th>
            <th class="num" style="width: 95px;">PRECIO UNT</th>
            <th class="num" style="width: 95px;">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($productos as $producto)
            <tr>
                <td class="cant">{{ $producto->cantidad }}</td>
                <td>
                    {{ $producto->nombre_producto }}
                    @php
                        $desc = isset($producto->descripcion_item) && trim($producto->descripcion_item) !== ''
                            ? $producto->descripcion_item
                            : '';
                    @endphp
                    @if($desc)
                        <div class="descripcion-producto">{{ $desc }}</div>
                    @endif
                </td>
                <td class="monto">{{ $fmt($producto->precio_unitario) }}</td>
                <td class="monto">{{ $fmt($producto->total) }}</td>
            </tr>
        @endforeach

        @if(!empty($servicio))
            <tr>
                <td class="cant">1</td>
                <td>
                    <strong>Servicio:</strong>
                    @if(!empty($servicio->descripcion))
                        <div class="descripcion-producto">
                            {{ $servicio->descripcion }}
                        </div>
                    @endif
                </td>
                <td class="monto">{{ $fmt($servicio->precio) }}</td>
                <td class="monto">{{ $fmt($servicio->precio) }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ===== Panel inferior: vigencia / moneda / condiciones / totales ===== --}}
<div class="panel-inferior">
    <table class="tabla-inferior">
        <tr>
            <td class="inf-label">Vigencia:</td>
            <td class="inf-valor">
                {{ $vigenciaFecha ?: '-' }}
            </td>
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
                {{ $cantidadEscrita }}
            </td>
        </tr>
        <tr>
            <td class="inf-label">Nota:</td>
            <td class="inf-valor" colspan="3">
                <strong>{{ $notaFija }}</strong>
            </td>
        </tr>

        {{-- Nota de tipo de cambio SOLO cuando la cotización está en USD y existe una tasa válida --}}
        @if($MONEDA === 'USD' && $tasaCambio)
            <tr>
                <td colspan="4" style="padding-top:6px; font-size:9px; color:#555;">
                    Nota: TC al registrar: 1 USD = {{ number_format($tasaCambio, 4, '.', ',') }} MXN
                </td>
            </tr>
        @endif
    </table>
</div>

{{-- ===== Texto + firma con mejor distribución ===== --}}
<table class="firma-layout">
    <tr>
        <td class="firma-col-text">
            <div class="footer-text">
                <p>
                    Sin más por el momento y esperando escuchar pronto de usted, quedo
                    a sus órdenes para cualquier duda o aclaración respecto a esta
                    cotización.
                </p>
            </div>
        </td>

        <td class="firma-col-sign">
            @if($firmaNombre || $firmaPuesto || $firmaEmpresa || $firmaImagen)
                <div class="firma-section">
                    <p>Atentamente</p>

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
                    <div class="firma-telefono">Cel: 442-169-7094</div>
                </div>
            @endif
        </td>
    </tr>
</table>
</body>
</html>

