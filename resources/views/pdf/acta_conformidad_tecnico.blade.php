{{-- resources/views/pdf/acta_conformidad.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Acta de conformidad</title>
  <style>
    @page { margin: 30px 35px; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        color: #111;
        line-height: 1.35;
    }

    h1, h2, h3 { margin: 0 0 6px 0; }

    .muted { color: #555; }
    .small { font-size: 11px; }

    .chip {
        display:inline-block;
        padding:2px 8px;
        border-radius:999px;
        font-size:9px;
        font-weight:bold;
        border:1px solid #0072bc;
        color:#0072bc;
        background:#fff;
    }

    .section {
        margin-top: 14px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .grid { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { padding: 6px 8px; vertical-align: top; }
    .grid.striped tr:nth-child(odd) td { background: #fafafa; }

    .box {
        border:1px solid #e5e5e5;
        padding:10px;
        border-radius:4px;
        background:#fff;
    }

    .flex { display:flex; gap:10px; }
    .col  { flex:1; }

    .text-right { text-align:right; }

    .table-bordered {
        border-collapse: collapse;
        width: 100%;
        font-size: 10px;
    }
    .table-bordered th,
    .table-bordered td {
        border: none;
        padding: 4px 6px;
    }
    .table-bordered thead th {
        border-bottom: 1px solid #ddd;
        background: #f5f5f5;
        color: #0072bc;
        font-weight: bold;
    }
    .table-bordered tbody tr:nth-child(odd) td {
        background: #fafafa;
    }

    .header { width: 100%; margin-bottom: 10px; }

    .barra-superior {
        width: 100%;
        height: 8px;
        margin-bottom: 4px;
    }

    .tabla-header { width: 100%; border-collapse: collapse; }
    .tabla-header td { border: none; vertical-align: middle; }

    .td-logo { width: 50%; }
    .td-info { width: 50%; text-align: right; }

    .logo { height: 52px; }

    .info-empresa { line-height: 1.25; }
    .info-empresa strong { font-size: 13px; font-weight: 700; }
    .info-empresa span { display: block; font-size: 10px; }

    .panel-acta {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        margin-top: 12px;
        background-color: #e3e3e3;
        padding: 8px 12px 10px 12px;
    }

    .tabla-acta {
        width: 100%;
        max-width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10px;
    }

    .tabla-acta th,
    .tabla-acta td {
        padding: 3px 4px;
        border: none;
        vertical-align: middle;
    }

    .acta-titulo { font-size: 14px; font-weight:bold; color:#0072bc; }
    .acta-sub { font-size:10px; }

    .tabla-seccion {
        width: 100%;
        max-width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        table-layout: fixed;
        box-sizing: border-box;
        font-size: 10px;
    }

    .tabla-seccion th,
    .tabla-seccion td {
        padding: 6px;
        text-align: left;
        vertical-align: top;
        border: 1px solid #ffffff;
        overflow-wrap: break-word;
        word-break: break-word;
    }

    .tabla-seccion th {
        background-color: #e3e3e3;
        color: #0072bc;
        font-weight: bold;
    }

    .tabla-seccion td { background-color: #ffffff; }
    .tabla-seccion td.text-right { text-align:right; }

    .totales-panel {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        background-color: #e3e3e3;
        border-top: 3px solid #0072bc;
        border-bottom: 3px solid #0072bc;
        padding: 8px 14px 10px 14px;
    }

    .tabla-totales {
        width: 100%;
        max-width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10px;
    }
    .tabla-totales th,
    .tabla-totales td {
        padding: 4px 6px;
        border:none;
        background:transparent;
    }
    .tabla-totales th { width: 76%; text-align:left; font-weight:normal; }
    .tabla-totales td { width: 24%; text-align:right; white-space: nowrap; }
    .tabla-totales td.note-tc {
        width: auto;
        text-align: left !important;
        white-space: normal !important;
        word-break: break-word;
    }

    .totales-firmas-block {
        margin-top:16px;
        page-break-inside: avoid;
    }

    .signs {
        margin-top: 18px;
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .sign {
        display: table-cell;
        width: 50%;
        box-sizing: border-box;
        padding: 0 8px;
        text-align: center;
        vertical-align: top;
    }

    .sign-area { margin: 0; text-align: center; }

    .sign-box {
        height: 80px;
        display: block;
    }

    .sign-img {
        max-height: 70px;
        max-width: 100%;
        display: block;
        margin: 0 auto;
    }

    .sign-line {
        margin: 8px auto 4px auto;
        width: 220px;
        border-top: 1px solid #333;
    }

    .watermark {
      position: fixed;
      top: 40%; left: 8%; right: 8%;
      font-size: 72px; opacity: 0.11; transform: rotate(-20deg);
      border: 6px solid #000; padding: 20px;
      text-align:center; letter-spacing:3px;
    }

    .footer {
      position: fixed;
      bottom: 18px; left: 35px; right: 35px;
      font-size:10px; color:#666;
    }

    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr, img { page-break-inside: avoid; }
    .totales-panel,.tabla-totales,.totales-firmas-block > .section:first-child,.table-bordered th:nth-child(2),.table-bordered td:nth-child(2),.table-bordered th:nth-child(3),.table-bordered td:nth-child(3),.tabla-seccion th:nth-child(3),.tabla-seccion td:nth-child(3),.tabla-seccion th:nth-child(4),.tabla-seccion td:nth-child(4){display:none;}
      @include('pdf.partials.corporate-theme')
</style>
</head>
<body>
  @if(!empty($draft))
    <div class="watermark">BORRADOR</div>
  @endif

  @php
    $actaData = $acta ?? [];

    $folioOs = $orden->folio
        ?? ('OS-' . str_pad((string)($orden->id_orden_servicio ?? $orden->getKey()), 5, '0', STR_PAD_LEFT));

    $folioCot = null;
    if (!empty($orden->id_cotizacion)) {
        $folioCot = $orden->id_cotizacion;
    } elseif (!empty($cotizacion)) {
        $folioCot = optional($cotizacion)->folio;
    }

    $conformeTxt = (($actaData['conforme'] ?? 'si') === 'no') ? 'No' : 'Sí';

    $firmaCliente = $firma_cliente_src ?? null;
    $firmaEmpresa = $firma_empresa_src ?? null;

    $clienteNombre   = optional($cliente)->nombre ?? optional($cliente)->nombre_empresa;
    $clienteContacto = $orden->contacto ?? optional($cliente)->contacto;

    // Encabezado gráfico
    $logoBase64  = null;
    $barraBase64 = null;

    $logoPath  = public_path('images/logo3.jpg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');

    $barraPath = public_path('images/barra_superior.png');

    if (file_exists($logoPath))  $logoBase64  = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    if (file_exists($barraPath)) $barraBase64 = 'data:image/png;base64,'  . base64_encode(file_get_contents($barraPath));

    /* =====================================================
     * MONEDA / CONVERSIÓN
     * - Detalles y costos están en moneda de la ORDEN
     * - Extras se CAPTURAN SIEMPRE EN MXN (precio_unitario)
     * ===================================================== */

    $monedaOrden = strtoupper(trim((string)($orden->moneda ?? 'MXN')));
    if ($monedaOrden === '') $monedaOrden = 'MXN';

    $simboloMoneda = ($monedaOrden === 'USD') ? 'USD $' : 'MXN $';

    $tasaCambio = (float)($orden->tasa_cambio ?? 1.0);
    if ($tasaCambio <= 0) $tasaCambio = 1.0;

    $fmt = function($n) use ($simboloMoneda) {
      return $simboloMoneda . number_format((float)$n, 2, '.', ',');
    };

    // ===== 1) Materiales (detalles) en moneda de la orden
    $materialesOrden = 0.0;
    if (!empty($detalles)) {
      foreach ($detalles as $dRow) {
        $cantD  = (float)($dRow->cantidad ?? 0);
        $puD    = (float)($dRow->precio_unitario ?? $dRow->precio ?? 0);
        $totalD = $dRow->total ?? ($dRow->subtotal ?? ($cantD * $puD));
        $materialesOrden += (float)$totalD;
      }
    }

    // ===== 2) Servicio (precio) en moneda de la orden
    $servicioOrden = (float)($orden->precio ?? 0);

    // ===== 3) Base gravable (materiales + servicio)
    $baseGravable = $materialesOrden + $servicioOrden;

    // ===== 4) IVA / impuestos (usar el guardado; si no hay, calcular)
    $impuestos = $orden->impuestos;
    if ($impuestos === null) {
      $impuestos = round($baseGravable * 0.16, 2);
    } else {
      $impuestos = (float)$impuestos;
    }

    // ===== 5) Costo operativo (en moneda de la orden)
    $costoOperativo = (float)($orden->costo_operativo ?? 0);

    // ===== 6) Extras (capturados en MXN) => usar campo total_adicional_mxn si existe
    $extrasTotalMxn = (float)($orden->total_adicional_mxn ?? 0);

    // fallback: si no viene el campo o viene 0 pero sí hay extras, sumar SOLO con precio_unitario asignado
    if ($extrasTotalMxn <= 0 && !empty($extras)) {
      $tmp = 0.0;
      foreach ($extras as $eRow) {
        $pu = $eRow->precio_unitario ?? $eRow->precio ?? null;
        if ($pu === null || $pu === '') continue; // pendiente => no suma
        $tmp += (float)($eRow->cantidad ?? 0) * (float)$pu; // MXN
      }
      $extrasTotalMxn = $tmp;
    }

    // Convertir extras a moneda de la orden
    $extrasDisplay = ($monedaOrden === 'USD')
      ? ($tasaCambio > 0 ? ($extrasTotalMxn / $tasaCambio) : $extrasTotalMxn)
      : $extrasTotalMxn;

    // ===== 7) TOTAL GENERAL (como tu total final): base gravable + IVA + operativo + extras
    $totalGeneral = $baseGravable + $impuestos + $costoOperativo + $extrasDisplay;

    // Para la tabla resumen
    $subtotal_productos = $materialesOrden;
    $subtotal_servicio  = $servicioOrden;
    $subtotal_gravable  = $baseGravable;
    $total_extras       = $extrasDisplay;
    $otros_costos       = $costoOperativo;
    $total_general      = $totalGeneral;

    // Conteo de extras pendientes (solo informativo)
    $extrasPendientes = 0;
    if (!empty($extras)) {
      foreach ($extras as $eRow) {
        $pu = $eRow->precio_unitario ?? $eRow->precio ?? null;
        if ($pu === null || $pu === '') $extrasPendientes++;
      }
    }
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

  {{-- PANEL ENCABEZADO ACTA --}}
  <div class="panel-acta">
      <table class="tabla-acta">
          <tr>
              <td style="width:70%;">
                  <div class="acta-titulo">ACTA DE CONFORMIDAD</div>
                  <div class="acta-sub muted small">
                      Folio OS: <strong>{{ $folioOs }}</strong>
                      @if($folioCot)
                        &nbsp; | &nbsp; Cotización: <strong>{{ $folioCot }}</strong>
                      @endif
                  </div>
                  <div class="acta-sub muted small">
                      Moneda: <strong>{{ $monedaOrden }}</strong>
                      @if($monedaOrden === 'USD')
                        &nbsp; | &nbsp; TC: <strong>1 USD = {{ number_format($tasaCambio, 4, '.', ',') }} MXN</strong>
                      @endif
                  </div>
                  <div class="acta-sub muted small">
                      Fecha de generación: {{ now()->format('d/m/Y H:i') }}
                  </div>
              </td>
              <td style="text-align:right; width:30%;">
                  @if(!empty($draft))
                    <span class="chip">BORRADOR</span>
                  @else
                    <span class="chip">DEFINITIVO</span>
                  @endif
              </td>
          </tr>
      </table>
  </div>

  {{-- DATOS GENERALES --}}
  <table class="tabla-seccion">
      <thead>
          <tr>
              <th colspan="2">Datos generales de la orden</th>
          </tr>
      </thead>
      <tbody>
          <tr>
              <td><strong>Cliente:</strong><br>{{ $clienteNombre ?? '—' }}</td>
              <td><strong>Servicio:</strong><br>{{ $orden->servicio ?? '—' }}</td>
          </tr>
          <tr>
              <td>
                <strong>Fecha de la Orden:</strong><br>
                @if($orden->fecha_orden)
                  {{ \Illuminate\Support\Carbon::parse($orden->fecha_orden)->format('d/m/Y') }}
                @else
                  —
                @endif
              </td>
              <td>
                <strong>Fecha de creación OS:</strong><br>
                {{ optional($orden->created_at)->format('d/m/Y H:i') ?: '—' }}
              </td>
          </tr>
          <tr>
              <td>
                <strong>Dirección / ubicación del servicio:</strong><br>
                {{ $orden->direccion ?? $orden->ubicacion ?? '—' }}
              </td>
              <td>
                <strong>Contacto:</strong><br>
                {{ $clienteContacto ?? '—' }}
              </td>
          </tr>
      </tbody>
  </table>

  {{-- TÉCNICOS --}}
  @if(!empty($tecnicos) && count($tecnicos))
    <div class="section">
      <strong>Técnicos que realizaron el trabajo</strong>
      <div class="box" style="margin-top:6px;">
        <ul class="small" style="margin:0; padding-left:18px;">
          @foreach($tecnicos as $tec)
            <li>
              {{ $tec->name ?? $tec->nombre ?? ('Técnico '.$loop->iteration) }}
              @if(!empty($tec->telefono))
                — Tel: {{ $tec->telefono }}
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  {{-- RESPONSABLE / FECHA CONFIRMACIÓN --}}
  <div class="section">
    <table class="grid striped">
      <tr>
        <td style="width:45%;"><strong>Responsable que recibe</strong><br>{{ $actaData['responsable'] ?? '—' }}</td>
        <td style="width:25%;"><strong>Puesto</strong><br>{{ $actaData['puesto'] ?? '—' }}</td>
        <td style="width:30%;"><strong>Fecha y hora</strong><br>
          @if(!empty($actaData['fecha']))
            {{ \Illuminate\Support\Carbon::parse($actaData['fecha'])->format('d/m/Y') }}
          @else
            —
          @endif
          {{ !empty($actaData['hora']) ? $actaData['hora'] : '' }}
        </td>
      </tr>
    </table>
  </div>

  {{-- TRABAJO REALIZADO --}}
  <div class="section">
    <strong>Trabajo realizado</strong>
    <div class="box" style="margin-top:6px; white-space: pre-line;">
      {{ $actaData['trabajo_realizado'] ?? ($orden->descripcion_servicio ?? $orden->servicio ?? '—') }}
    </div>
  </div>

  {{-- RESULTADO / OBSERVACIONES --}}
  <div class="section flex">
    <div class="col">
      <strong>Resultado de conformidad</strong>
      <div class="box" style="margin-top:6px;">
        Cliente conforme: <strong>{{ $conformeTxt }}</strong>
      </div>
    </div>
    <div class="col">
      <strong>Observaciones</strong>
      <div class="box" style="margin-top:6px; white-space: pre-line;">
        {{ $actaData['observaciones'] ?? '—' }}
      </div>
    </div>
  </div>

  {{-- DETALLE DE PRODUCTOS --}}
  @if(!empty($detalles) && count($detalles))
    <div class="section">
      <strong>Materiales de la orden</strong>
      <table class="table-bordered small" style="margin-top:6px;">
        <thead>
          <tr>
            <th style="width:54%;">Producto</th>
            <th style="width:18%;" class="text-right">P. unitario</th>
            <th style="width:18%;" class="text-right">Importe</th>
            <th style="width:10%;" class="text-right">Cant.</th>
          </tr>
        </thead>
        <tbody>
          @foreach($detalles as $d)
            @php
              $cant    = (float)($d->cantidad ?? 0);
              $pu      = (float)($d->precio_unitario ?? $d->precio ?? 0);
              $importe = (float)($d->total ?? ($d->subtotal ?? ($cant * $pu)));
            @endphp
            <tr>
              <td>
                <strong>{{ $d->nombre_producto ?? 'Producto' }}</strong>
                @php $seriesTexto = (isset($d->series) && $d->series) ? $d->series->pluck('numero_serie')->filter()->implode(', ') : ''; @endphp
                @if($seriesTexto !== '')
                  <div class="muted"><strong>N/S:</strong> {{ $seriesTexto }}</div>
                @endif
              </td>
              <td class="text-right">{{ $fmt($pu) }}</td>
              <td class="text-right">{{ $fmt($importe) }}</td>
              <td class="text-right">{{ number_format($cant, 2, '.', ',') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  {{-- MATERIAL EXTRA --}}
  @if(!empty($extras) && count($extras))
    <div class="section">
      <strong>Materiales / gastos extra</strong>

      <table class="table-bordered small" style="margin-top:6px;">
        <thead>
          <tr>
            <th style="width:54%;">Material extra</th>
            <th style="width:18%;" class="text-right">P. unitario</th>
            <th style="width:18%;" class="text-right">Importe</th>
            <th style="width:10%;" class="text-right">Cant.</th>
          </tr>
        </thead>
        <tbody>
          @foreach($extras as $e)
            @php
              $cant = (float)($e->cantidad ?? 0);
              $nombreExtra = trim((string)($e->descripcion ?? $e->concepto ?? $e->nombre ?? 'Material extra'));
              if ($nombreExtra === '') $nombreExtra = 'Material extra';
              $puMxn = $e->precio_unitario ?? $e->precio ?? null; // MXN (puede ser null)

              $pendiente = ($puMxn === null || $puMxn === '');
              $puMxn = $pendiente ? 0.0 : (float)$puMxn;

              $importeMxn = $pendiente ? 0.0 : ($cant * $puMxn);

              if ($monedaOrden === 'USD') {
                  $puDisplay      = $tasaCambio > 0 ? ($puMxn / $tasaCambio) : $puMxn;
                  $importeDisplay = $tasaCambio > 0 ? ($importeMxn / $tasaCambio) : $importeMxn;
              } else {
                  $puDisplay      = $puMxn;
                  $importeDisplay = $importeMxn;
              }
            @endphp
            <tr>
              <td>{{ $nombreExtra }}</td>
              <td class="text-right">
                @if($pendiente)
                  <span class="muted">—</span>
                @else
                  {{ $fmt($puDisplay) }}
                @endif
              </td>
              <td class="text-right">
                @if($pendiente)
                  <span class="muted">—</span>
                @else
                  {{ $fmt($importeDisplay) }}
                @endif
              </td>
              <td class="text-right">{{ number_format($cant, 2, '.', ',') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  {{-- RESUMEN + FIRMAS --}}
  <div class="totales-firmas-block">
      <div class="section">
        <strong>Resumen de costos</strong>
        <div class="totales-panel" style="margin-top:6px;">
          <table class="tabla-totales small">
            <tr>
              <th>Materiales (detalles)</th>
              <td>{{ $fmt($subtotal_productos ?? 0) }}</td>
            </tr>
            <tr>
              <th>Servicio</th>
              <td>{{ $fmt($subtotal_servicio ?? 0) }}</td>
            </tr>
            <tr>
              <th>Subtotal gravable (Materiales + Servicio)</th>
              <td>{{ $fmt($subtotal_gravable ?? 0) }}</td>
            </tr>
            <tr>
              <th>IVA / Impuestos</th>
              <td>{{ $fmt($impuestos ?? 0) }}</td>
            </tr>
            <tr>
              <th>Costo operativo / viáticos</th>
              <td>{{ $fmt($otros_costos ?? 0) }}</td>
            </tr>
            <tr>
              <th>Materiales / gastos extra</th>
              <td>{{ $fmt($total_extras ?? 0) }}</td>
            </tr>
            <tr>
              <th><strong>TOTAL GENERAL</strong></th>
              <td><strong>{{ $fmt($total_general ?? 0) }}</strong></td>
            </tr>

            @if($monedaOrden === 'USD')
              <tr>
                <td colspan="2" class="small muted note-tc" style="padding-top:6px;">
                  Nota: TC al registrar: 1 USD = {{ number_format($tasaCambio, 4, '.', ',') }} MXN
                </td>
              </tr>
            @endif
          </table>
        </div>
      </div>

      {{-- Firmas --}}
      <div class="signs">
        {{-- Cliente --}}
        <div class="sign">
          <div class="sign-area">
            <div class="sign-box">
              @if(!empty($firmaCliente))
                <img src="{{ $firmaCliente }}" class="sign-img">
              @endif
            </div>
            <div class="sign-line"></div>
            <div class="small">
              Firma de conformidad del cliente / responsable
            </div>
            <div class="small muted" style="margin-top:2px;">
              {{ $actaData['responsable'] ?? '____________________' }}
            </div>
          </div>
        </div>

        {{-- Empresa --}}
        <div class="sign">
          <div class="sign-area">
            <div class="sign-box">
              @if(!empty($firmaEmpresa))
                <img src="{{ $firmaEmpresa }}" class="sign-img">
              @endif
            </div>
            <div class="sign-line"></div>
            <div class="small">
              Representante de la empresa
            </div>
            <div class="small muted" style="margin-top:2px;">
              {{ $actaData['firma_emp_nombre']  ?? 'Ing. José Alberto Rivera Rodríguez' }}<br>
              {{ $actaData['firma_emp_puesto']  ?? 'E-SUPPORT QUERÉTARO' }}<br>
              {{ $actaData['firma_emp_empresa'] ?? 'E-SUPPORT QUERÉTARO' }}
            </div>
          </div>
        </div>
      </div>
  </div>

  <div class="footer">
    Este documento forma parte del expediente de la Orden de Servicio {{ $folioOs }}@if($folioCot), derivada de la cotización {{ $folioCot }}@endif.
    @if(!empty($draft))
      Estado del documento: BORRADOR (sin validez definitiva).
    @else
      Estado del documento: DEFINITIVO (firmado).
    @endif
  </div>
</body>
</html>

