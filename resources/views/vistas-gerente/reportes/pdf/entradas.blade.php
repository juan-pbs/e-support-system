{{-- resources/views/vistas-gerente/reportes/pdf/entradas.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Entradas de Inventario' }}</title>
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

        /* ===== CONTENEDOR UNIFORME (MISMO BORDE PARA TODO) ===== */
        .container{ width: 100%; }
        .gutter{ padding: 0 8px; } /* <- el “margen visual” común */

        /* ===== Panel gris (gris vive en tabla, no en div) ===== */
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
            padding: 8px 8px 10px 8px; /* ✅ mismo gutter */
        }
        .panel-td-right{
            width: 22%;
            padding: 8px 8px 10px 8px; /* ✅ mismo gutter */
            text-align: right;
            white-space: nowrap;
            font-size: 10px;
        }

        .titulo-doc{
            font-size: 14px;
            font-weight: bold;
            color: #0072bc;
            margin: 0 0 4px 0;
        }
        .sub-doc{ font-size: 10px; margin-top: 2px; }

        .desc{ margin-top: 8px; } /* más compacto */

        /* ===== Tabla ===== */
        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.8px;         /* un pelín más compacto */
            margin-top: 8px;
        }

        .table-bordered th,
        .table-bordered td{
            border: none;
            padding: 5px 6px;         /* ✅ compacto */
            vertical-align: top;
            line-height: 1.18;        /* ✅ compacto */
        }

        .table-bordered thead th{
            border-bottom: 1px solid #d9d9d9;
            background: #f5f5f5;
            color: #0072bc;
            font-weight: bold;
            font-size: 9.4px;
            white-space: normal;
            overflow: visible;
            padding: 5px 6px;         /* ✅ compacto */
        }

        /* separadores suaves */
        .sep-r{ border-right: 1px solid #ededed; }

        /* ===== Ajustes por columna ===== */
        .col-id, .col-cantidad{
            padding-left: 3px !important;
            padding-right: 3px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .col-proveedor{
            font-size: 9.4px;
            line-height: 1.14;
        }

        .table-bordered tbody tr:nth-child(odd) td{ background: #fafafa; }

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
    $cols = $cols ?? [];
    $rows = $rows ?? [];
    $tituloTxt = $titulo ?? 'Entradas de Inventario';
    $rangoTxt  = $rango ?? 'Sin rango de fechas especificado';

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

    /* ===== ANCHOS (ID y Cantidad MÁS estrechos, Proveedor MÁS ancho) ===== */
    $widthMap = [
        'id' => 2,
        'costo' => 11,
        'tipo de control' => 12,
        'cantidad' => 3,
        'numero de serie' => 18,
        'fecha de entrada' => 12,
        'hora de entrada' => 8,
        'proveedor' => 34,
    ];

    $defaultWidth = (count($cols) > 0) ? max(8, floor(100 / count($cols))) : 12;

    // Encabezado con saltos para evitar recorte
    $headerPretty = function($c) use ($norm){
        $k = $norm($c);
        $map = [
            'tipo de control'  => 'Tipo de<br>control',
            'numero de serie'  => 'Número de<br>serie',
            'fecha de entrada' => 'Fecha de<br>entrada',
            'hora de entrada'  => 'Hora de<br>entrada',
        ];
        return $map[$k] ?? e($c);
    };

    $isNumericCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['costo','precio','cantidad','importe','total','mxn','usd'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };

    $isCenterCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['id','tipo','control','fecha','hora','moneda','folio'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };
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

    {{-- PANEL (con gutter uniforme) --}}
    <div class="panel-wrap">
        <table class="panel-table">
            <tr>
                <td class="panel-td-left">
                    <div class="titulo-doc">{{ $tituloTxt }}</div>

                    <div class="sub-doc muted">
                        Detalle de entradas a inventario registradas en el rango seleccionado.
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

    <div class="gutter">
        <div class="desc small muted">
            Incluye ID de movimiento, costo, tipo de control, cantidad, número de serie del producto, así como la fecha y hora de registro de cada entrada.
        </div>

        @if(!empty($cols) && !empty($rows))
            <table class="table-bordered">
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
                            $k = $norm($c);

                            $thClass = '';
                            if ($isNumericCol($c)) $thClass = 'text-right';
                            elseif ($isCenterCol($c)) $thClass = 'center';

                            if ($k === 'id') $thClass .= ' col-id';
                            if ($k === 'cantidad') $thClass .= ' col-cantidad';
                            if ($k === 'proveedor') $thClass .= ' col-proveedor';

                            if (!$isLast) $thClass .= ' sep-r';
                        @endphp
                        <th class="{{ trim($thClass) }}">{!! $headerPretty($c) !!}</th>
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
                                $k = $norm($c);

                                $tdClass = '';
                                if ($isNumericCol($c)) $tdClass = 'num';
                                elseif ($isCenterCol($c)) $tdClass = 'center nowrap';
                                else $tdClass = 'wrap';

                                if ($k === 'proveedor') $tdClass .= ' col-proveedor';
                                if ($k === 'id') $tdClass .= ' col-id';
                                if ($k === 'cantidad') $tdClass .= ' col-cantidad';

                                if (!$isLast) $tdClass .= ' sep-r';
                            @endphp
                            <td class="{{ trim($tdClass) }}">{{ $val }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="desc muted">
                No se encontraron registros de entradas de inventario para los filtros seleccionados.
            </div>
        @endif
    </div>
</div>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema E-Support
</div>

</body>
</html>
