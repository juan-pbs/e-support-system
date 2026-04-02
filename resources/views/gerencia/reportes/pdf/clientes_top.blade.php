{{-- resources/views/pdf/clientes.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Clientes' }}</title>
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
            line-height: 1.35;
        }

        .muted{ color:#555; }
        .small{ font-size: 11px; }
        .text-right{ text-align:right; }
        .num{ text-align:right; white-space: nowrap; }

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

        /* ===== PANEL GRIS (FIX DEFINITIVO DOMPDF) ===== */
        .panel-wrap{ margin-top: 12px; }

        .panel-table{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #e3e3e3;   /* ✅ el gris vive en la tabla (no en div) */
        }
        .panel-table td{
            border: none;
            vertical-align: top;
        }
        .panel-td-left{
            width: 78%;
            padding: 8px 8px 10px 8px; /* gutter 8 */
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
            margin: 0 0 2px 0;
        }
        .sub-doc{ font-size: 10px; margin-top: 2px; }

        /* Texto descriptivo alineado con tabla */
        .desc{
            padding: 0 8px;
            margin-top: 10px;
        }

        /* ===== Tabla clientes ===== */
        .table-bordered{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
            margin-top: 10px;
        }
        .table-bordered th,
        .table-bordered td{
            border: none;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .table-bordered thead th{
            border-bottom: 1px solid #ddd;
            background: #f5f5f5;
            color: #0072bc;
            font-weight: bold;
            text-align: left;
        }
        .table-bordered thead th.text-right{ text-align:right; }
        .table-bordered tbody tr:nth-child(odd) td{
            background: #fafafa;
        }

        /* ===== Totales: MISMA grilla que la tabla ===== */
        .totales{
            width: 100%;
            margin-top: 14px;
            background: #e3e3e3;
            page-break-inside: avoid;
        }
        .totales-inner{
            width: 100%;
            border-top: 3px solid #0072bc;
            border-bottom: 3px solid #0072bc;
            padding: 8px 0 10px 0;  /* sin gutter lateral, lo dan las celdas */
        }
        .totals-grid{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }
        .totals-grid td{
            border: none;
            padding: 6px 8px;
            vertical-align: top;
            background: transparent;
        }
        .totals-grid .label{ font-weight: 700; }
        .totals-grid .value{
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }
        .nota-tc{
            padding: 2px 8px 0 8px;
            margin-top: 4px;
            font-weight: normal;
            color:#555;
            font-size: 10px;
        }

        /* ===== Footer fijo ===== */
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

    $rangoTxt = $rango ?? 'Sin rango de fechas especificado';

    // Anchos sugeridos (si tus columnas se llaman igual)
    $w = [
        'Cliente'    => '38%',
        'Órdenes'    => '12%',
        'Monto MXN'  => '16%',
        'Monto USD'  => '16%',
        'Total MXN'  => '18%',
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

{{-- PANEL (YA NO SE SALE NUNCA) --}}
<div class="panel-wrap">
    <table class="panel-table">
        <tr>
            <td class="panel-td-left">
                <div class="titulo-doc">{{ $titulo ?? 'REPORTE DE CLIENTES' }}</div>

                <div class="sub-doc muted">
                    Clientes con compras o servicios en el período seleccionado. Muestra número de órdenes y montos MXN/USD con total convertido a MXN.
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
    Muestra el detalle por cliente: monto en MXN, monto en USD y el total estimado en MXN usando tipo de cambio.
</div>

{{-- TABLA --}}
@if(!empty($cols ?? []) && !empty($rows ?? []))
    <table class="table-bordered">
        <thead>
        <tr>
            @foreach($cols as $c)
                @php
                    $width = $w[$c] ?? null;
                    $isNum = in_array($c, ['Órdenes','Monto MXN','Monto USD','Total MXN'], true);
                @endphp
                <th @if($width) style="width:{{ $width }};" @endif class="{{ $isNum ? 'text-right' : '' }}">
                    {{ $c }}
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                @foreach($cols as $c)
                    @php
                        $isNum = in_array($c, ['Órdenes','Monto MXN','Monto USD','Total MXN'], true);
                        $val = $r[$c] ?? '';
                    @endphp
                    <td class="{{ $isNum ? 'num' : '' }}">{{ $val }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- TOTALES --}}
    @if(isset($meta['importe']) && is_array($meta['importe']))
        <div class="totales">
            <div class="totales-inner">
                <table class="totals-grid">
                    <colgroup>
                        <col style="width:{{ $w['Cliente'] ?? '38%' }};">
                        <col style="width:{{ $w['Órdenes'] ?? '12%' }};">
                        <col style="width:{{ $w['Monto MXN'] ?? '16%' }};">
                        <col style="width:{{ $w['Monto USD'] ?? '16%' }};">
                        <col style="width:{{ $w['Total MXN'] ?? '18%' }};">
                    </colgroup>

                    <tr>
                        <td class="label" colspan="4">Total MXN</td>
                        <td class="value">${{ number_format((float)($meta['importe']['mxn'] ?? 0), 2, '.', ',') }}</td>
                    </tr>
                    <tr>
                        <td class="label" colspan="4">Total USD</td>
                        <td class="value">${{ number_format((float)($meta['importe']['usd'] ?? 0), 2, '.', ',') }}</td>
                    </tr>
                    <tr>
                        <td class="label" colspan="4">Total estimado MXN</td>
                        <td class="value">${{ number_format((float)($meta['importe']['estimado_mxn'] ?? 0), 2, '.', ',') }}</td>
                    </tr>
                </table>

                @if(!empty($meta['importe']['tipo_cambio']))
                    <div class="nota-tc">
                        Nota: TC usado = {{ $meta['importe']['tipo_cambio'] }}
                    </div>
                @endif
            </div>
        </div>
    @endif
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
