{{-- resources/views/vistas-gerente/reportes/pdf/salidas.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Salidas de Inventario (Productos)' }}</title>
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
            line-height: 1.28;
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

        .container{ width: 100%; }

        /* ===== Panel gris ===== */
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
            margin: 0 0 3px 0;
        }
        .sub-doc{ font-size: 10px; margin-top: 2px; }

        .desc{
            padding: 0 8px;
            margin-top: 8px;
            font-size: 9.6px;
        }

        .table-wrap{
            padding: 0 8px;
            margin-top: 8px;
        }

        /* ===== Tabla ===== */
        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.1px;
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
            font-size: 9.1px;
            white-space: normal;
            overflow: visible;
            text-align: center;
            padding: 5px 6px;
        }

        .sep-r{ border-right: 1px solid #ededed; }
        .table-bordered tbody tr:nth-child(odd) td{ background: #fafafa; }

        /* ===== Totales ===== */
        .totales-wrap{
            padding: 0 8px;
            margin-top: 10px;
        }
        .totales-table{
            width: 42%;
            margin-left: auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.8px;
        }
        .totales-table td{
            padding: 4px 6px;
            border: 1px solid #e5e5e5;
        }
        .totales-table .lbl{
            background: #f5f5f5;
            color: #0f172a;
            font-weight: bold;
        }
        .totales-table .val{
            text-align: right;
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
    $tituloTxt = $titulo ?? 'Salidas de Inventario (Productos)';
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

    // Normalizador
    $norm = function($s){
        $s = mb_strtolower(trim((string)$s));
        $s = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $s);
        return $s;
    };

    // ✅ SOLO columnas de PRODUCTOS (con NÚMEROS DE SERIE)
    $allowed = [
        'id detalle',
        'fecha de salida',
        'hora de salida',
        'numero de parte',
        'nombre producto',
        'cantidad',
        'precio unitario',
        'total',
        'moneda',
        'numeros de serie',
    ];
    $allowedFlip = array_flip($allowed);

    // ✅ Filtrar + QUITAR DUPLICADAS (por normalizado)
    $seen = [];
    $colsPdf = [];
    foreach ($cols as $c) {
        $k = $norm($c);
        if (!isset($allowedFlip[$k])) continue;
        if (isset($seen[$k])) continue; // quita repetidas (ej. "Número de parte" al final)
        $seen[$k] = true;
        $colsPdf[] = $c;
    }

    $isNumericCol = function ($name) use ($norm) {
        $k = $norm($name);
        return in_array($k, ['cantidad','precio unitario','total'], true);
    };
    $isCenterCol = function ($name) use ($norm) {
        $k = $norm($name);
        return in_array($k, ['id detalle','fecha de salida','hora de salida','moneda'], true);
    };

    $headerPretty = function($c) use ($norm){
        $k = $norm($c);
        $map = [
            'id detalle'        => 'ID<br>detalle',
            'numero de parte'   => 'Número<br>de parte',
            'fecha de salida'   => 'Fecha<br>de salida',
            'hora de salida'    => 'Hora<br>de salida',
            'precio unitario'   => 'Precio<br>unitario',
            'numeros de serie'  => 'Números<br>de serie',
        ];
        return $map[$k] ?? e($c);
    };

    $widthMap = [
        'id detalle'       => 7,
        'fecha de salida'  => 9,
        'hora de salida'   => 8,
        'numero de parte'  => 12,
        'nombre producto'  => 18,
        'cantidad'         => 7,
        'precio unitario'  => 10,
        'total'            => 9,
        'moneda'           => 7,
        'numeros de serie' => 13,
    ];
    $defaultWidth = (count($colsPdf) > 0) ? max(7, floor(100 / count($colsPdf))) : 10;

    $toNumber = function($val) {
        if ($val === null) return 0.0;
        $s = trim((string)$val);
        if ($s === '' || $s === '—' || $s === '-') return 0.0;
        $s = str_replace(['US$', '$', ',', ' ', 'MXN', 'USD'], '', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    };

    // ✅ TOTALES
    $totalCantidad = 0;
    $totalMXN = 0.0;
    $totalUSD = 0.0;

    foreach ($rows as $r) {
        $qty = (int)($r['Cantidad'] ?? 0);
        $totalCantidad += $qty;

        $mon = strtoupper(trim((string)($r['Moneda'] ?? 'MXN')));
        $tot = $toNumber($r['Total'] ?? 0);

        if ($mon === 'USD') $totalUSD += $tot;
        else $totalMXN += $tot;
    }

    $money = fn($n) => number_format((float)$n, 2, '.', ',');
@endphp

<div class="header">
    @if(!empty($barraBase64))
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    @else
        <table class="barra-fallback" role="presentation"><tr><td></td></tr></table>
    @endif

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                @if(!empty($logoBase64))
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

<div class="container">

    <div class="panel-wrap">
        <table class="panel-table">
            <tr>
                <td class="panel-td-left">
                    <div class="titulo-doc">{{ $tituloTxt }}</div>

                    <div class="sub-doc muted">
                        Detalle de productos que han salido de inventario (cantidad, precio, total, moneda y series).
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
        Reporte enfocado únicamente en productos.
    </div>

    @if(!empty($colsPdf) && !empty($rows))
        <div class="table-wrap">
            <table class="table-bordered">
                <colgroup>
                    @foreach($colsPdf as $c)
                        @php
                            $k = $norm($c);
                            $w = $widthMap[$k] ?? $defaultWidth;
                        @endphp
                        <col style="width: {{ $w }}%;">
                    @endforeach
                </colgroup>

                <thead>
                <tr>
                    @foreach($colsPdf as $i => $c)
                        @php
                            $isLast = ($i === count($colsPdf) - 1);

                            $thClass = '';
                            if ($isNumericCol($c)) $thClass = 'text-right';
                            elseif ($isCenterCol($c)) $thClass = 'center';

                            if (!$isLast) $thClass .= ' sep-r';
                        @endphp
                        <th class="{{ trim($thClass) }}">{!! $headerPretty($c) !!}</th>
                    @endforeach
                </tr>
                </thead>

                <tbody>
                @foreach($rows as $r)
                    <tr>
                        @foreach($colsPdf as $i => $c)
                            @php
                                $isLast = ($i === count($colsPdf) - 1);
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

        {{-- ✅ TOTALES --}}
        <div class="totales-wrap">
            <table class="totales-table">
                <tr>
                    <td class="lbl">Total cantidad</td>
                    <td class="val">{{ number_format($totalCantidad, 0, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total MXN</td>
                    <td class="val">$ {{ $money($totalMXN) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total USD</td>
                    <td class="val">US$ {{ $money($totalUSD) }}</td>
                </tr>
            </table>
        </div>
    @else
        <div class="desc muted">
            No se encontraron registros para los filtros seleccionados.
        </div>
    @endif

</div>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema E-Support
</div>

</body>
</html>

