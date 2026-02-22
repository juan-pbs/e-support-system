{{-- resources/views/vistas-gerente/reportes/pdf/tecnicos_top.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Técnicos con Más Órdenes' }}</title>
    <style>
        @page { margin: 30px 35px; }

        * { box-sizing: border-box; }
        html, body { width: 100%; }

        body{
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #111;
            line-height: 1.30;
        }

        .muted{ color:#555; }
        .small{ font-size: 10px; }
        .text-right{ text-align:right; }
        .center{ text-align:center; }
        .num{ text-align:right; white-space: nowrap; }
        .nowrap{ white-space: nowrap; }
        .wrap{ white-space: normal; word-break: break-word; }

        /* ===== Encabezado tipo E-SUPPORT ===== */
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

        /* ✅ Contenedor global SIN padding (para alinear perfecto con header) */
        .container{
            width: 100%;
        }

        /* ===== Panel gris (dompdf safe: gris en tabla) ===== */
        .panel-wrap{ margin-top: 12px; }
        .panel-table{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #e3e3e3;
        }
        .panel-table td{ border: none; vertical-align: top; }
        .panel-td-left{
            width: 78%;
            padding: 8px 8px 10px 8px; /* gutter interno */
        }
        .panel-td-right{
            width: 22%;
            padding: 8px 8px 10px 8px; /* gutter interno */
            text-align: right;
            white-space: nowrap;
            font-size: 10px;
        }

        .titulo-doc{
            font-size: 14px;
            font-weight: bold;
            color: #0072bc;
            margin: 0 0 3px 0;
        }
        .sub-doc{ font-size: 10px; margin-top: 2px; }

        /* Texto descriptivo alineado con la tabla (gutter interno) */
        .desc{
            padding: 0 8px;
            margin-top: 8px;
            font-size: 9.6px;
            color:#555;
        }

        /* ===== Tabla principal (compacta y alineada) ===== */
        .table-wrap{
            padding: 0 8px;      /* ✅ gutter aquí, NO en container */
            margin-top: 8px;
        }

        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.2px;
        }

        .table-bordered th,
        .table-bordered td{
            border: none;
            padding: 4px 6px;
            vertical-align: top;
            line-height: 1.15;
        }

        .table-bordered thead th{
            border-bottom: 1px solid #d9d9d9;
            background: #f5f5f5;
            color: #0072bc;
            font-weight: bold;
            font-size: 9.2px;
            white-space: normal;
            overflow: visible;
            text-align: center;
            padding: 5px 6px;
        }

        .sep-r{ border-right: 1px solid #ededed; }
        .table-bordered tbody tr:nth-child(odd) td{ background: #fafafa; }

        /* ===== Totales (alineados al mismo ancho que panel/tabla) ===== */
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
            padding: 6px 0 8px 0; /* sin gutter lateral, lo dan las celdas */
        }
        .tabla-totales{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.5px;
        }
        .tabla-totales th,
        .tabla-totales td{
            border: none;
            padding: 3px 8px;   /* ✅ mismo gutter 8 */
            background: transparent;
            line-height: 1.15;
        }
        .tabla-totales th{ text-align:left; font-weight: 700; }
        .tabla-totales td{ text-align:right; font-weight: 700; white-space: nowrap; }

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
    </style>
</head>
<body>
@php
    $tituloTxt = $titulo ?? 'Técnicos con Más Órdenes';
    $rangoTxt  = $rango ?? 'Sin rango de fechas especificado';

    $cols = $cols ?? [];
    $rows = $rows ?? [];

    // ===== Logo + barra =====
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath  = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');
    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath))  $logoBase64  = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    if (file_exists($barraPath)) $barraBase64 = 'data:image/png;base64,'  . base64_encode(file_get_contents($barraPath));

    // Helpers
    $norm = function($s){
        $s = mb_strtolower(trim((string)$s));
        $s = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $s);
        return $s;
    };

    $isNumericCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['total','importe','monto','mxn','usd','ordenes','órdenes','cantidad'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };
    $isCenterCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['id','folio'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };

    // ===== Anchos sugeridos =====
    $widthMap = [
        'tecnico'     => 34,
        'técnico'     => 34,
        'ordenes'     => 12,
        'órdenes'     => 12,
        'importe mxn' => 14,
        'mxn'         => 10,
        'importe usd' => 14,
        'usd'         => 10,
        'total'       => 16,
        'moneda'      => 10,
    ];

    $defaultWidth = (count($cols) > 0) ? max(8, floor(100 / count($cols))) : 12;
    $tight = count($cols) >= 8;

    // Totales meta
    $metaImporte = $meta['importe'] ?? null;
    $totalMXN = (float)($metaImporte['mxn'] ?? 0);
    $totalUSD = (float)($metaImporte['usd'] ?? 0);
@endphp

{{-- ENCABEZADO --}}
<div class="header">
    @if(!empty($barraBase64))
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    @endif

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                @if(!empty($logoBase64))
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

<div class="container">

    {{-- PANEL --}}
    <div class="panel-wrap">
        <table class="panel-table">
            <tr>
                <td class="panel-td-left">
                    <div class="titulo-doc">{{ $tituloTxt }}</div>

                    <div class="sub-doc muted">
                        Listado de técnicos con órdenes asignadas y el importe generado en el período.
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

    <div class="desc small muted">
        Este reporte permite evaluar el desempeño de cada técnico según el número de órdenes atendidas y el importe total asociado.
    </div>

    @if(!empty($cols) && !empty($rows))
        <div class="table-wrap">
            <table class="table-bordered" @if($tight) style="font-size:9px;" @endif>
                <colgroup>
                    @foreach($cols as $c)
                        @php
                            $k = $norm($c);
                            $w = $widthMap[$k] ?? $defaultWidth;
                        @endphp
                        <col style="width: {{ $w }}%;">
                    @endforeach
                </colgroup>

                <thead>
                <tr>
                    @foreach($cols as $i => $c)
                        @php
                            $isLast = ($i === count($cols) - 1);

                            $thClass = '';
                            if ($isNumericCol($c)) $thClass = 'text-right';
                            elseif ($isCenterCol($c)) $thClass = 'center';

                            if (!$isLast) $thClass .= ' sep-r';
                        @endphp
                        <th class="{{ trim($thClass) }}">{{ $c }}</th>
                    @endforeach
                </tr>
                </thead>

                <tbody>
                @foreach($rows as $r)
                    <tr>
                        @foreach($cols as $i => $c)
                            @php
                                $isLast = ($i === count($cols) - 1);
                                $val = $r[$c] ?? '';

                                $tdClass = '';
                                if ($isNumericCol($c)) $tdClass = 'num';
                                elseif ($isCenterCol($c)) $tdClass = 'center nowrap';
                                else $tdClass = 'wrap';

                                if (!$isLast) $tdClass .= ' sep-r';
                            @endphp
                            <td class="{{ trim($tdClass) }}">{{ $val }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if(!empty($metaImporte))
            <div class="totales">
                <div class="totales-inner">
                    <table class="tabla-totales">
                        <tr>
                            <th>Total importe en MXN</th>
                            <td>${{ number_format($totalMXN, 2, '.', ',') }} MXN</td>
                        </tr>
                        <tr>
                            <th>Total importe en USD</th>
                            <td>${{ number_format($totalUSD, 2, '.', ',') }} USD</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif
    @else
        <div class="desc muted">
            No se encontraron técnicos con órdenes para los filtros seleccionados.
        </div>
    @endif

</div>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema E-Support
</div>

</body>
</html>
