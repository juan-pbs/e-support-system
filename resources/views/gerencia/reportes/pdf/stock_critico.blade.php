{{-- resources/views/vistas-gerente/reportes/pdf/stock_critico.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Listado de productos' }}</title>
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

        /* âœ… Contenedor global SIN padding (alineaciÃ³n perfecta) */
        .container{ width: 100%; }

        /* ===== Panel gris (dompdf safe) ===== */
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
            padding: 8px 8px 10px 8px; /* âœ… gutter interno */
        }
        .panel-td-right{
            width: 22%;
            padding: 8px 8px 10px 8px; /* âœ… gutter interno */
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

        /* DescripciÃ³n alineada con tabla */
        .desc{
            padding: 0 8px;        /* âœ… gutter aquÃ­ */
            margin-top: 8px;
            font-size: 9.6px;
        }

        /* ===== Tabla (alineada) ===== */
        .table-wrap{
            padding: 0 8px;        /* âœ… gutter aquÃ­ */
            margin-top: 8px;
        }

        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.2px;      /* âœ… mas compacto */
        }

        .table-bordered th,
        .table-bordered td{
            border: none;
            padding: 4px 6px;      /* âœ… compacto */
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

        /* separadores suaves */
        .sep-r{ border-right: 1px solid #ededed; }
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
            @include('pdf.partials.corporate-theme')

        @page { margin: 20px 35px; }

        .container,
        .desc,
        .table-wrap,
        .totales,
        .gutter {
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
    </style>
</head>
<body>
@php
    $tituloTxt = $titulo ?? 'Listado de productos';
    $rangoTxt  = $rango ?? 'Este reporte no depende de rangos de fechas.';

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
        $s = str_replace(['Ã¡','Ã©','Ã­','Ã³','Ãº','Ã±'], ['a','e','i','o','u','n'], $s);
        return $s;
    };

    // AlineaciÃ³n por tipo de columna
    $isNumericCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['stock','existencia','costo','precio','importe','total','mxn','usd','cantidad'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };
    $isCenterCol = function ($name) {
        $lc = mb_strtolower(trim((string)$name));
        foreach (['id','codigo','clave','unidad','tipo'] as $k) {
            if (strpos($lc, $k) !== false) return true;
        }
        return false;
    };

    // ===== Anchos sugeridos =====
    $widthMap = [
        'id'              => 5,
        'codigo'          => 10,
        'codigo producto' => 10,
        'numero parte'    => 12,
        'clave'           => 10,
        'producto'        => 22,
        'nombre'          => 22,
        'descripcion'     => 26,
        'categoria'       => 14,
        'proveedor'       => 16,
        'stock'           => 9,
        'stock total'     => 9,
        'stock seguridad' => 10,
        'precio'          => 10,
        'costo'           => 10,
        'unidad'          => 9,
    ];

    $defaultWidth = (count($cols) > 0) ? max(8, floor(100 / count($cols))) : 12;
    $tight = count($cols) >= 9;
@endphp

{{-- ENCABEZADO --}}
@include('pdf.partials.document-header')

<div class="container">

    {{-- PANEL --}}
    <div class="panel-wrap">
        <table class="panel-table">
            <tr>
                <td class="panel-td-left">
                    <div class="titulo-doc">{{ $tituloTxt }}</div>

                    @if(!empty($pdfTheme['intro_text']))
                        <div class="sub-doc muted">{{ $pdfTheme['intro_text'] }}</div>
                    @endif

                    <div class="sub-doc muted">
                        Productos con su stock actual y el precio obtenido de la ultima entrada registrada en inventario.
                    </div>

                    <div class="sub-doc muted">
                        Rango: <strong>{{ $rangoTxt }}</strong>
                    </div>

                    <div class="sub-doc muted">
                        Fecha de generacion: {{ now()->format('d/m/Y H:i') }}
                    </div>
                </td>

                <td class="panel-td-right muted">
                    {{ $pdfTheme['company_name'] ?? 'Sistema E-Support' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="desc small muted">
        Este reporte permite consultar rapidamente la existencia de cada producto y el precio mas reciente registrado en inventario.
        Es util para validar costos, planear compras y revisar disponibilidad para proyectos y servicios.
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
    @else
        <div class="desc muted">
            No se encontraron productos para mostrar en este reporte.
        </div>
    @endif

</div>

@include('pdf.partials.report-footer')

</body>
</html>
