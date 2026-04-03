<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Salidas de Inventario (Productos)' }}</title>
    <style>
        @page { margin: 30px 35px; }

        * { box-sizing: border-box; }
        html, body { width: 100%; }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.2px;
            color: #111;
            line-height: 1.28;
        }

        .muted { color: #555; }
        .small { font-size: 9.5px; }
        .text-right { text-align: right; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .nowrap { white-space: nowrap; }
        .wrap { white-space: normal; word-break: break-word; }

        .header { width: 100%; margin: 0 0 10px 0; }
        .barra-superior { width: 100%; height: 8px; margin: 0 0 4px 0; display: block; }

        .tabla-header {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .tabla-header td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .td-logo { width: 52%; }
        .td-info { width: 48%; text-align: right; }
        .logo { height: 52px; display: block; }

        .info-empresa { line-height: 1.25; }
        .info-empresa strong { font-size: 13px; font-weight: 700; }
        .info-empresa span { display: block; font-size: 10px; }

        .container { width: 100%; }

        .panel-wrap { margin-top: 12px; }
        .panel-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #e3e3e3;
        }

        .panel-table td { border: none; vertical-align: top; }

        .panel-td-left {
            width: 78%;
            padding: 8px 8px 10px 8px;
        }

        .panel-td-right {
            width: 22%;
            padding: 8px 8px 10px 8px;
            text-align: right;
            white-space: nowrap;
            font-size: 10px;
        }

        .titulo-doc {
            font-size: 14px;
            font-weight: bold;
            color: #0072bc;
            margin: 0 0 3px 0;
        }

        .sub-doc { font-size: 10px; margin-top: 2px; }
        .desc { padding: 0 8px; margin-top: 8px; font-size: 9.4px; }
        .table-wrap { padding: 0 8px; margin-top: 8px; }

        .table-bordered {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8.7px;
        }

        .table-bordered th,
        .table-bordered td {
            border: none;
            padding: 4px 6px;
            vertical-align: top;
            line-height: 1.16;
        }

        .table-bordered thead th {
            border-bottom: 1px solid #d9d9d9;
            background: #f5f5f5;
            color: #0072bc;
            font-weight: bold;
            font-size: 8.5px;
            text-align: center;
            white-space: normal;
            word-break: normal;
            padding: 5px 4px;
        }

        .sep-r { border-right: 1px solid #ededed; }
        .table-bordered tbody tr:nth-child(odd) td { background: #fafafa; }

        .meta-cell strong,
        .finance-cell strong {
            display: block;
            font-weight: 700;
        }

        .meta-cell span,
        .finance-cell span {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 8px;
        }

        .totales-wrap {
            padding: 0 8px;
            margin-top: 10px;
        }

        .totales-table {
            width: 42%;
            margin-left: auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.5px;
        }

        .totales-table td {
            padding: 4px 6px;
            border: 1px solid #e5e5e5;
        }

        .totales-table .lbl {
            background: #f5f5f5;
            color: #0f172a;
            font-weight: bold;
        }

        .totales-table .val {
            text-align: right;
            white-space: nowrap;
        }

        .footer {
            position: fixed;
            bottom: 18px;
            left: 35px;
            right: 35px;
            font-size: 10px;
            color: #666;
            text-align: right;
        }

        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr, img { page-break-inside: avoid; }

        @include('pdf.partials.corporate-theme')
    </style>
</head>
<body>
@php
    $tituloTxt = $titulo ?? 'Salidas de Inventario (Productos)';
    $rangoTxt = $rango ?? 'Sin rango de fechas especificado';
    $rows = $rows ?? [];

    $logoBase64 = null;
    $barraBase64 = null;

    $logoPath = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) {
        $logoPath = public_path('images/logo.png');
    }

    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath)) {
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    }

    if (file_exists($barraPath)) {
        $barraBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($barraPath));
    }

    $norm = function ($value) {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'Ã¡' => 'a',
            'Ã©' => 'e',
            'Ã­' => 'i',
            'Ã³' => 'o',
            'Ãº' => 'u',
            'Ã±' => 'n',
            'ÃƒÂ¡' => 'a',
            'ÃƒÂ©' => 'e',
            'ÃƒÂ­' => 'i',
            'ÃƒÂ³' => 'o',
            'ÃƒÂº' => 'u',
            'ÃƒÂ±' => 'n',
            'ÃƒÆ’Ã‚Â¡' => 'a',
            'ÃƒÆ’Ã‚Â©' => 'e',
            'ÃƒÆ’Ã‚Â­' => 'i',
            'ÃƒÆ’Ã‚Â³' => 'o',
            'ÃƒÆ’Ã‚Âº' => 'u',
            'ÃƒÆ’Ã‚Â±' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    };

    $get = function (array $row, string $column) use ($norm) {
        $target = $norm($column);

        foreach ($row as $key => $value) {
            if ($norm($key) === $target) {
                return $value;
            }
        }

        return '';
    };

    $columns = [
        ['key' => 'salida', 'label' => 'Salida', 'width' => 19, 'align' => 'wrap'],
        ['key' => 'numero_parte', 'label' => 'No. parte', 'width' => 17, 'align' => 'wrap'],
        ['key' => 'producto', 'label' => 'Producto', 'width' => 31, 'align' => 'wrap'],
        ['key' => 'finanzas', 'label' => 'Finanzas', 'width' => 21, 'align' => 'wrap'],
        ['key' => 'series', 'label' => 'Series', 'width' => 12, 'align' => 'wrap'],
    ];

    $toNumber = function ($value) {
        if ($value === null) {
            return 0.0;
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $value = str_replace(['US$', '$', ',', ' ', 'MXN', 'USD'], '', $value);
        return is_numeric($value) ? (float) $value : 0.0;
    };

    $totalCantidad = 0;
    $totalMXN = 0.0;
    $totalUSD = 0.0;

    foreach ($rows as $row) {
        $totalCantidad += (int) ($get($row, 'Cantidad') ?: 0);

        $moneda = strtoupper(trim((string) ($get($row, 'Moneda') ?: 'MXN')));
        $total = $toNumber($get($row, 'Total'));

        if ($moneda === 'USD') {
            $totalUSD += $total;
        } else {
            $totalMXN += $total;
        }
    }

    $money = fn ($value) => number_format((float) $value, 2, '.', ',');
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
                    <strong>E-SUPPORT QUERETARO</strong>
                    <span>Jose Alberto Rivera Rodriguez</span>
                    <span>RFC: RIRA781030RI8</span>
                    <span>Av. Emeterio Gonzalez No. 27 int. 2</span>
                    <span>Hercules, Queretaro, Qro. C.P. 76069</span>
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
                        Detalle de productos que han salido de inventario con cantidad, precio, total, moneda y series.
                    </div>

                    <div class="sub-doc muted">
                        Rango: <strong>{{ $rangoTxt }}</strong>
                    </div>

                    <div class="sub-doc muted">
                        Fecha de generacion: {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>

                <td class="panel-td-right muted">
                    Sistema E-Support
                </td>
            </tr>
        </table>
    </div>

    <div class="desc small muted">
        Reporte enfocado unicamente en productos con salida de inventario.
    </div>

    @if(!empty($rows))
        <div class="table-wrap">
            <table class="table-bordered">
                <colgroup>
                    @foreach($columns as $column)
                        <col style="width: {{ $column['width'] }}%;">
                    @endforeach
                </colgroup>

                <thead>
                <tr>
                    @foreach($columns as $index => $column)
                        @php
                            $thClass = '';
                            if ($column['align'] === 'num') {
                                $thClass = 'text-right';
                            } elseif ($column['align'] === 'center') {
                                $thClass = 'center';
                            }

                            if ($index !== count($columns) - 1) {
                                $thClass .= ' sep-r';
                            }
                        @endphp
                        <th class="{{ trim($thClass) }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
                </thead>

                <tbody>
                @foreach($rows as $row)
                    @php
                        $fecha = $get($row, 'Fecha de salida') ?: '-';
                        $hora = $get($row, 'Hora de salida') ?: '-';
                        $id = $get($row, 'ID detalle') ?: '-';
                        $numeroParte = $get($row, 'Numero de parte') ?: '-';
                        $producto = $get($row, 'Nombre producto') ?: '-';
                        $cantidad = $get($row, 'Cantidad') ?: '0';
                        $precio = $get($row, 'Precio unitario') ?: '0.00';
                        $total = $get($row, 'Total') ?: '0.00';
                        $moneda = strtoupper(trim((string) ($get($row, 'Moneda') ?: 'MXN')));
                        $series = $get($row, 'Numeros de serie') ?: '-';
                    @endphp
                    <tr>
                        @foreach($columns as $index => $column)
                            @php
                                $tdClass = 'wrap';
                                if ($column['align'] === 'center') {
                                    $tdClass = 'center nowrap';
                                }

                                if ($index !== count($columns) - 1) {
                                    $tdClass .= ' sep-r';
                                }
                            @endphp
                            <td class="{{ trim($tdClass) }}">
                                @switch($column['key'])
                                    @case('salida')
                                        <div class="meta-cell">
                                            <strong>{{ $fecha }}</strong>
                                            <span>{{ $hora }} · #{{ $id }}</span>
                                        </div>
                                        @break
                                    @case('numero_parte')
                                        {{ $numeroParte }}
                                        @break
                                    @case('producto')
                                        {{ $producto }}
                                        @break
                                    @case('finanzas')
                                        <div class="finance-cell">
                                            <strong>Total: {{ $total }} {{ $moneda }}</strong>
                                            <span>Cantidad: {{ $cantidad }}</span>
                                            <span>Precio unit.: {{ $precio }}</span>
                                        </div>
                                        @break
                                    @case('series')
                                        {{ $series }}
                                        @break
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

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
    Generado el {{ now()->format('d/m/Y H:i') }} - Sistema E-Support
</div>
</body>
</html>
