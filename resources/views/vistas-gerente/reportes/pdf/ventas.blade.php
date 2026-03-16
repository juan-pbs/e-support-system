{{-- resources/views/vistas-gerente/reportes/pdf/ventas.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Ventas' }}</title>
    <style>
        @page { margin: 30px 35px; }

        * { box-sizing: border-box; }
        html, body { width: 100%; }

        body{
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color:#111827;
            line-height: 1.35;
        }

        .muted{ color:#6b7280; }
        .small{ font-size: 10px; }
        .text-right{ text-align:right; }
        .text-center{ text-align:center; }
        .num{ text-align:right; white-space: nowrap; }

        .header{ width: 100%; margin: 0 0 10px 0; }
        .barra-superior{ width: 100%; height: 8px; margin: 0 0 4px 0; display:block; }

        .tabla-header{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .tabla-header td{
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .td-logo{ width: 52%; }
        .td-info{ width: 48%; text-align: right; }
        .logo{ height: 52px; display:block; }

        .info-empresa{ line-height: 1.25; }
        .info-empresa strong{ font-size: 13px; font-weight: 700; }
        .info-empresa span{ display:block; font-size:10px; }

        .panel-wrap{ margin-top: 12px; }
        .panel-table{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #e3e3e3;
        }
        .panel-table td{
            border: none;
            vertical-align: top;
        }
        .panel-td-left{
            width: 78%;
            padding: 8px 8px 10px 8px;
        }
        .panel-td-right{
            width: 22%;
            padding: 8px 8px 10px 8px;
            text-align: right;
            white-space: nowrap;
            font-size: 10px;
        }

        .titulo-doc{
            font-size: 14px;
            font-weight: bold;
            color: #0072bc;
            margin: 0 0 2px 0;
        }
        .sub-doc{ font-size: 10px; margin-top: 2px; }

        .desc{
            padding: 0 8px;
            margin-top: 8px;
            font-size: 9.6px;
            color:#4b5563;
        }

        .table-wrap{
            padding: 0 8px;
            margin-top: 8px;
        }

        .data{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.8px;
            margin: 0 auto;
        }
        .data th, .data td{
            border: none;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
            line-height: 1.15;
        }
        .data thead th{
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
            color: #0072bc;
            font-weight: bold;
            text-align: center;
            padding: 5px 6px;
        }
        .data tbody tr:nth-child(odd) td{ background:#fafafa; }

        .td-left{ text-align:left; }
        .td-center{ text-align:center; }
        .td-right{ text-align:right; white-space:nowrap; }

        .totales{
            width: 100%;
            margin-top: 10px;
            background: #e3e3e3;
            page-break-inside: avoid;
        }
        .totales-inner{
            width: 100%;
            border-top: 3px solid #0072bc;
            border-bottom: 3px solid #0072bc;
            padding: 6px 0 8px 0;
        }
        .totals-grid{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9px;
        }
        .totals-grid td{
            border: none;
            padding: 3px 6px;
            vertical-align: top;
            background: transparent;
            line-height: 1.15;
        }
        .totals-grid .group{
            color:#0072bc;
            font-weight:800;
            padding-top: 8px;
        }
        .totals-grid .label{ font-weight:700; color:#111827; }
        .totals-grid .value{
            font-weight:700;
            text-align:right;
            white-space: nowrap;
        }

        .footer{
            position: fixed;
            bottom: 18px; left: 35px; right: 35px;
            font-size:10px;
            color:#666;
            text-align: right;
        }

        thead{ display: table-header-group; }
        tfoot{ display: table-footer-group; }
        tr, img{ page-break-inside: avoid; }
        @include('pdf.partials.corporate-theme')
</style>
</head>
<body>
@php
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath  = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');

    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath))  $logoBase64  = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    if (file_exists($barraPath)) $barraBase64 = 'data:image/png;base64,'  . base64_encode(file_get_contents($barraPath));

    $metaTotales = $meta['totales'] ?? [];
    $tc = (float)($tipo_cambio ?? 0);

    // ===== Totales por categoría (MXN/USD) =====
    $prodMXN = (float)($metaTotales['productos']['mxn'] ?? 0);
    $prodUSD = (float)($metaTotales['productos']['usd'] ?? 0);

    $servMXN = (float)($metaTotales['servicios']['mxn'] ?? 0);
    $servUSD = (float)($metaTotales['servicios']['usd'] ?? 0);

    $matMXN  = (float)($metaTotales['materiales_no_previstos']['mxn'] ?? 0);
    $matUSD  = (float)($metaTotales['materiales_no_previstos']['usd'] ?? 0);

    $genMXN  = (float)($metaTotales['general']['mxn'] ?? 0);
    $genUSD  = (float)($metaTotales['general']['usd'] ?? 0);

    // Total pagado (anticipos)
    $antiMXN = (float)($metaTotales['anticipo']['mxn'] ?? 0);
    $antiUSD = (float)($metaTotales['anticipo']['usd'] ?? 0);

    $toMXN = fn($usd) => $tc > 0 ? ($usd * $tc) : 0.0;

    // ===== Estimados a MXN =====
    $prodTotalMXN = $prodMXN + $toMXN($prodUSD);
    $servTotalMXN = $servMXN + $toMXN($servUSD);
    $matTotalMXN  = $matMXN  + $toMXN($matUSD);
    $genTotalMXN  = $genMXN  + $toMXN($genUSD);
    $antiTotalMXN = $antiMXN + $toMXN($antiUSD);

    $numRegistros = is_countable($rows ?? []) ? count($rows) : 0;
    $rangoTxt = $rango ?? 'Sin rango de fechas especificado';

    // ===== Columnas EXACTAS del PDF (solo las que pediste) =====
    $colsPdf = [
        'Fecha',
        'Orden',
        'Cliente',
        'Tipo de pago',
        'Estado',
        'Total orden',
        'Total pagado',
    ];

    // Alineación
    $rightCols  = ['Total orden','Total pagado'];
    $centerCols = ['Fecha','Orden','Tipo de pago','Estado'];

    // Anchos
    $w = [
        'Fecha'       => '10%',
        'Orden'       => '8%',
        'Cliente'     => '28%',
        'Tipo de pago'=> '14%',
        'Estado'      => '12%',
        'Total orden' => '14%',
        'Total pagado'=> '14%',
    ];
@endphp

{{-- ENCABEZADO --}}
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
                    <span>Cel: 442-169-7094</span>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- PANEL --}}
<div class="panel-wrap">
    <table class="panel-table">
        <tr>
            <td class="panel-td-left">
                <div class="titulo-doc">{{ $titulo ?? 'Reporte de Ventas' }}</div>

                <div class="sub-doc muted">
                    Reporte de órdenes de servicio <strong>finalizadas</strong>.
                </div>

                <div class="sub-doc muted">
                    Rango: <strong>{{ $rangoTxt }}</strong>
                </div>

                <div class="sub-doc muted">
                    Fecha de generación: {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
            <td class="panel-td-right muted">
                Sistema E-Support
            </td>
        </tr>
    </table>
</div>

<div class="desc muted">
    <strong>Total orden</strong> = Productos + Servicios + Materiales extra.
    <strong>Total pagado</strong> = Anticipo.
</div>

{{-- TABLA --}}
@if(!empty($rows ?? []))
    <div class="table-wrap">
        <table class="data">
            <colgroup>
                @foreach($colsPdf as $c)
                    <col style="width: {{ $w[$c] ?? 'auto' }};">
                @endforeach
            </colgroup>

            <thead>
            <tr>
                @foreach($colsPdf as $c)
                    @php
                        $thCls = 'td-left';
                        if (in_array($c, $rightCols, true)) $thCls = 'td-right';
                        elseif (in_array($c, $centerCols, true)) $thCls = 'td-center';
                    @endphp
                    <th class="{{ $thCls }}">{{ $c }}</th>
                @endforeach
            </tr>
            </thead>

            <tbody>
            @foreach($rows as $r)
                <tr>
                    @foreach($colsPdf as $c)
                        @php
                            $val = (string)($r[$c] ?? '');

                            $tdCls = 'td-left';
                            if (in_array($c, $rightCols, true)) $tdCls = 'td-right';
                            elseif (in_array($c, $centerCols, true)) $tdCls = 'td-center';
                        @endphp

                        <td class="{{ trim($tdCls) }}">{{ $val }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- TOTALES --}}
    <div class="totales">
        <div class="totales-inner">
            <table class="totals-grid">
                <tr>
                    <td class="label">Cantidad de órdenes finalizadas</td>
                    <td class="value">{{ $numRegistros }}</td>
                </tr>

                <tr><td class="group" colspan="2">Desglose de ventas</td></tr>

                <tr>
                    <td class="label">Total productos</td>
                    <td class="value">
                        ${{ number_format($prodMXN, 2, '.', ',') }}
                        <span class="muted">|</span>
                        US${{ number_format($prodUSD, 2, '.', ',') }}
                    </td>
                </tr>

                <tr>
                    <td class="label">Total servicios</td>
                    <td class="value">
                        ${{ number_format($servMXN, 2, '.', ',') }}
                        <span class="muted">|</span>
                        US${{ number_format($servUSD, 2, '.', ',') }}
                    </td>
                </tr>

                <tr>
                    <td class="label">Materiales extra</td>
                    <td class="value">
                        ${{ number_format($matMXN, 2, '.', ',') }}
                        <span class="muted">|</span>
                        US${{ number_format($matUSD, 2, '.', ',') }}
                    </td>
                </tr>

                <tr>
                    <td class="label"><strong>Total general</strong></td>
                    <td class="value">
                        <strong>${{ number_format($genMXN, 2, '.', ',') }}</strong>
                        <span class="muted">|</span>
                        <strong>US${{ number_format($genUSD, 2, '.', ',') }}</strong>
                    </td>
                </tr>

                <tr><td class="group" colspan="2">Totales estimados a MXN</td></tr>

                <tr>
                    <td class="label">Productos (≈ MXN)</td>
                    <td class="value">${{ number_format($prodTotalMXN, 2, '.', ',') }}</td>
                </tr>

                <tr>
                    <td class="label">Servicios (≈ MXN)</td>
                    <td class="value">${{ number_format($servTotalMXN, 2, '.', ',') }}</td>
                </tr>

                <tr>
                    <td class="label">Materiales extra (≈ MXN)</td>
                    <td class="value">${{ number_format($matTotalMXN, 2, '.', ',') }}</td>
                </tr>

                <tr>
                    <td class="label"><strong>Total general (≈ MXN)</strong></td>
                    <td class="value">
                        <strong>${{ number_format($genTotalMXN, 2, '.', ',') }}</strong>
                        @if($tc > 0)
                            <span class="muted">(TC: {{ number_format($tc, 4, '.', ',') }})</span>
                        @endif
                    </td>
                </tr>

                <tr><td class="group" colspan="2">Total pagado (Anticipos)</td></tr>
                <tr>
                    <td class="label">Anticipos</td>
                    <td class="value">
                        ${{ number_format($antiMXN, 2, '.', ',') }}
                        <span class="muted">|</span>
                        US${{ number_format($antiUSD, 2, '.', ',') }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Anticipos (≈ MXN)</td>
                    <td class="value">
                        ${{ number_format($antiTotalMXN, 2, '.', ',') }}
                        @if($tc > 0)
                            <span class="muted">(TC: {{ number_format($tc, 4, '.', ',') }})</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
@else
    <div class="desc muted">
        No se encontraron registros para los filtros seleccionados.
    </div>
@endif

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema E-Support
</div>

</body>
</html>

