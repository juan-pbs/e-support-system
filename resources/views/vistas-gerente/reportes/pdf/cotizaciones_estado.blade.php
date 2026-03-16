{{-- resources/views/vistas-gerente/reportes/pdf/cotizaciones_estado.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Reporte de Cotizaciones' }}</title>
    <style>
        @page { margin: 30px 35px; }

        * { box-sizing: border-box; }
        html, body { width: 100%; }

        body{
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;          /* ✅ más compacto */
            color: #111;
            line-height: 1.22;        /* ✅ más compacto */
        }

        .muted{ color:#555; }
        .small{ font-size: 10px; }   /* ✅ más compacto */
        .text-right{ text-align:right; }
        .center{ text-align:center; }
        .num{ text-align:right; white-space: nowrap; }
        .nowrap{ white-space: nowrap; }

        /* ===== Encabezado tipo E-SUPPORT ===== */
        .header{ width: 100%; margin: 0 0 8px 0; } /* ✅ menos espacio */
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
        .logo{ height: 50px; display:block; } /* ✅ un pelín más chico */

        .info-empresa{ line-height: 1.18; } /* ✅ más compacto */
        .info-empresa strong{ font-size: 12.5px; font-weight: 700; }
        .info-empresa span{ display:block; font-size:9.6px; }

        /* ===== Panel gris (compacto) ===== */
        .panel-wrap{ margin-top: 10px; }
        .panel-table{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #e3e3e3;
        }
        .panel-table td{ border: none; vertical-align: top; }
        .panel-td-left{
            width: 78%;
            padding: 8px 10px 9px 10px; /* ✅ más compacto */
        }
        .panel-td-right{
            width: 22%;
            padding: 8px 10px 9px 10px; /* ✅ más compacto */
            text-align: right;
            white-space: nowrap;
            font-size: 9.6px;
        }

        .titulo-doc{
            font-size: 13px;      /* ✅ más compacto */
            font-weight: bold;
            color: #0072bc;
            margin: 0 0 3px 0;
        }
        .sub-doc{ font-size: 9.6px; margin-top: 2px; }

        .desc{
            padding: 0 10px;
            margin-top: 8px;      /* ✅ menos espacio */
        }

        /* ===== Tabla (compacta) ===== */
        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.6px;     /* ✅ más compacto */
            margin-top: 8px;      /* ✅ menos espacio */
        }
        .table-bordered th,
        .table-bordered td{
            border: none;
            padding: 4px 6px;     /* ✅ más compacto */
            vertical-align: top;
            line-height: 1.15;    /* ✅ más compacto */
        }

        /* Encabezados: NO se parten en vertical */
        .table-bordered thead th{
            border-bottom: 1px solid #ddd;
            background: #f5f5f5;
            color: #0072bc;
            font-weight: bold;
            text-align: left;

            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
            hyphens: none;
            font-size: 9.2px;     /* ✅ más compacto */
            padding: 4px 6px;     /* ✅ más compacto */
        }
        .table-bordered thead th.center{ text-align:center; }
        .table-bordered thead th.text-right{ text-align:right; }

        .table-bordered tbody tr:nth-child(odd) td{ background: #fafafa; }

        .td-tight{ white-space: nowrap; }

        /* ===== Footer fijo ===== */
        .footer{
            position: fixed;
            bottom: 18px; left: 35px; right: 35px;
            font-size:9.6px;      /* ✅ más compacto */
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
<?php
    // ====== Normalizar datos ======
    $cols = $cols ?? [];
    $rows = $rows ?? [];
    $tituloTxt = $titulo ?? 'Reporte de Cotizaciones';
    $rangoTxt  = $rango ?? 'Sin rango de fechas especificado';

    // ====== Logo + barra ======
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath  = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');

    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath))  $logoBase64  = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    if (file_exists($barraPath)) $barraBase64 = 'data:image/png;base64,'  . base64_encode(file_get_contents($barraPath));

    // ====== Anchos fijos (100%) para que NO se rompan headers ======
    $widthMap = [
        'folio'      => '6%',
        'cliente'    => '20%',
        'empresa'    => '12%',
        'fecha'      => '10%',
        'vigencia'   => '10%',
        'estado'     => '12%',
        'proces'     => '8%',
        'edit'       => '8%',
        'moneda'     => '6%',
        'total'      => '8%',
    ];

    $labelMap = [
        'proces' => 'Procesos',
        'edit'   => 'Ediciones',
    ];

    $metaCols = [];
    foreach ($cols as $c) {
        $lc = mb_strtolower(trim((string)$c));
        $key = null;

        if (strpos($lc, 'folio') !== false) $key = 'folio';
        elseif (strpos($lc, 'cliente') !== false) $key = 'cliente';
        elseif (strpos($lc, 'empresa') !== false) $key = 'empresa';
        elseif (strpos($lc, 'fecha') !== false) $key = 'fecha';
        elseif (strpos($lc, 'vigenc') !== false) $key = 'vigencia';
        elseif (strpos($lc, 'estado') !== false) $key = 'estado';
        elseif (strpos($lc, 'proces') !== false) $key = 'proces';
        elseif (strpos($lc, 'edit') !== false) $key = 'edit';
        elseif (strpos($lc, 'moneda') !== false) $key = 'moneda';
        elseif (strpos($lc, 'total') !== false) $key = 'total';

        $metaCols[$c] = [
            'w'     => $key ? ($widthMap[$key] ?? null) : null,
            'label' => $key ? ($labelMap[$key] ?? $c) : $c,
            'th'    => in_array($key, ['folio','fecha','vigencia','proces','edit','moneda'], true) ? 'center' : ($key === 'total' ? 'text-right' : ''),
            'td'    => ($key === 'total') ? 'num'
                    : (in_array($key, ['folio','fecha','vigencia','proces','edit','moneda'], true) ? 'center td-tight' : ''),
        ];
    }
?>

{{-- ENCABEZADO --}}
<div class="header">
    <?php if (!empty($barraBase64)) : ?>
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    <?php else : ?>
        <table class="barra-fallback" role="presentation"><tr><td></td></tr></table>
    <?php endif; ?>

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                <?php if (!empty($logoBase64)) : ?>
                    <img src="{{ $logoBase64 }}" class="logo" alt="">
                <?php else : ?>
                    <div class="logo-fallback">
                        <strong>E-SUPPORT QUERETARO</strong>
                        <span>Soporte y servicio tecnico</span>
                    </div>
                <?php endif; ?>
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
                <div class="titulo-doc">{{ $tituloTxt }}</div>

                <div class="sub-doc muted">
                    Listado de cotizaciones generadas en el rango seleccionado, con cliente, vigencia, moneda, total y contadores de acciones.
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
    Este reporte permite analizar las cotizaciones generadas por cliente, ver cuántas veces se han procesado (PDF / envío) y cuántas veces se han editado, así como revisar su vigencia, moneda y total.
</div>

<?php if (!empty($cols) && !empty($rows)) : ?>
    <table class="table-bordered">
        <colgroup>
            <?php foreach ($cols as $c) : ?>
                <?php $w = $metaCols[$c]['w'] ?? null; ?>
                <col style="width: {{ $w ?: 'auto' }};">
            <?php endforeach; ?>
        </colgroup>

        <thead>
        <tr>
            <?php foreach ($cols as $c) : ?>
                <?php $m = $metaCols[$c] ?? ['label'=>$c,'th'=>'']; ?>
                <th class="{{ $m['th'] ?? '' }}">{{ $m['label'] ?? $c }}</th>
            <?php endforeach; ?>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($rows as $r) : ?>
            <tr>
                <?php foreach ($cols as $c) : ?>
                    <?php
                        $m = $metaCols[$c] ?? ['td'=>''];
                        $val = $r[$c] ?? '';
                    ?>
                    <td class="{{ $m['td'] ?? '' }}">{{ $val }}</td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else : ?>
    <div class="desc muted">
        No se encontraron cotizaciones para los filtros seleccionados.
    </div>
<?php endif; ?>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema E-Support
</div>

</body>
</html>
