/* ===== Corporate Theme (Global PDF) ===== */
@php
  $pdfTheme = $pdfTheme ?? [];
  $templateKey = $pdfTheme['_template_key'] ?? '';
  $primaryColor = $pdfTheme['primary_color'] ?? '#0072bc';
  $secondaryColor = $pdfTheme['secondary_color'] ?? '#0b4f88';
  $panelColor = $pdfTheme['panel_color'] ?? '#e3e3e3';
  $tableHeadBg = $pdfTheme['table_header_bg'] ?? '#f5f5f5';
  $tableHeadText = $pdfTheme['table_header_text'] ?? '#0072bc';
  $rowStripeColor = $pdfTheme['row_stripe_color'] ?? '#fafcff';
  $bodyTextColor = $pdfTheme['body_text_color'] ?? '#111827';
  $mutedTextColor = $pdfTheme['muted_text_color'] ?? '#555555';
  $footerTextColor = $pdfTheme['footer_text_color'] ?? '#666666';
  $pageMarginY = $pdfTheme['page_margin_vertical'] ?? 30;
  $pageMarginX = $pdfTheme['page_margin_horizontal'] ?? 35;
  $bodyFontSize = $pdfTheme['body_font_size'] ?? 10.5;
  $logoHeight = $pdfTheme['logo_height'] ?? 52;
  $topBarHeight = $pdfTheme['top_bar_height'] ?? 8;
  $sectionGap = $pdfTheme['section_spacing'] ?? 12;
  $cellPadY = $pdfTheme['table_cell_padding_y'] ?? 5;
  $cellPadX = $pdfTheme['table_cell_padding_x'] ?? 6;
  $headPadY = $pdfTheme['table_header_padding_y'] ?? 5;
  $headPadX = $pdfTheme['table_header_padding_x'] ?? 6;
  $headFontSize = $pdfTheme['table_header_font_size'] ?? 9.8;
  $tableLineHeight = $pdfTheme['table_line_height'] ?? 1.16;
@endphp

:root {
  --pdf-primary: {{ $pdfTheme['primary_color'] ?? '#0072bc' }};
  --pdf-secondary: {{ $pdfTheme['secondary_color'] ?? '#0b4f88' }};
  --pdf-panel: {{ $pdfTheme['panel_color'] ?? '#e3e3e3' }};
  --pdf-table-head-bg: {{ $pdfTheme['table_header_bg'] ?? '#f5f5f5' }};
  --pdf-table-head-text: {{ $pdfTheme['table_header_text'] ?? '#0072bc' }};
  --pdf-row-stripe: {{ $pdfTheme['row_stripe_color'] ?? '#fafcff' }};
  --pdf-body: {{ $pdfTheme['body_text_color'] ?? '#111827' }};
  --pdf-muted: {{ $pdfTheme['muted_text_color'] ?? '#555555' }};
  --pdf-footer: {{ $pdfTheme['footer_text_color'] ?? '#666666' }};
  --pdf-margin-y: {{ $pdfTheme['page_margin_vertical'] ?? 30 }}px;
  --pdf-margin-x: {{ $pdfTheme['page_margin_horizontal'] ?? 35 }}px;
  --pdf-font-size: {{ $pdfTheme['body_font_size'] ?? 10.5 }}px;
  --pdf-logo-height: {{ $pdfTheme['logo_height'] ?? 52 }}px;
  --pdf-top-bar-height: {{ $pdfTheme['top_bar_height'] ?? 8 }}px;
  --pdf-section-gap: {{ $pdfTheme['section_spacing'] ?? 12 }}px;
  --pdf-cell-pad-y: {{ $pdfTheme['table_cell_padding_y'] ?? 5 }}px;
  --pdf-cell-pad-x: {{ $pdfTheme['table_cell_padding_x'] ?? 6 }}px;
  --pdf-head-pad-y: {{ $pdfTheme['table_header_padding_y'] ?? 5 }}px;
  --pdf-head-pad-x: {{ $pdfTheme['table_header_padding_x'] ?? 6 }}px;
  --pdf-table-head-font-size: {{ $pdfTheme['table_header_font_size'] ?? 9.8 }}px;
  --pdf-table-line-height: {{ $pdfTheme['table_line_height'] ?? 1.16 }};
}

@page {
  margin: {{ $pageMarginY }}px {{ $pageMarginX }}px;
}
* {
  box-sizing: border-box;
}

html,
body {
  width: 100%;
  max-width: 100%;
}

body {
  margin: 0 !important;
  padding: 0 !important;
  color: {{ $bodyTextColor }};
  font-size: {{ $bodyFontSize }}px !important;
  letter-spacing: 0.05px;
}

.muted,
.enc-label,
.inf-label,
.small,
.sub-doc,
.acta-sub {
  color: {{ $mutedTextColor }} !important;
}

.info-empresa strong,
.titulo-doc,
.acta-titulo,
.enc-titulo,
.enc-bloque,
.enc-solicita-label,
.chip {
  color: {{ $primaryColor }} !important;
}

.chip {
  border-color: {{ $primaryColor }} !important;
}

.panel-orden,
.panel-encabezado,
.panel-inferior,
.panel-acta,
.totales-panel,
.panel-table,
.enc-titulo,
.enc-numero {
  background-color: {{ $panelColor }} !important;
}

/* Contenedor general de cada bloque del PDF */
body > *:not(.footer),
.header,
.panel-wrap,
.table-wrap,
.desc,
.totales,
.section,
.container,
.content,
.main,
.wrap,
.panel-acta,
.panel-orden,
.panel-encabezado,
.panel-inferior,
.totales-panel,
.totales-firmas-block,
.firma-layout,
.signs {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

body > *:not(.footer) {
  width: 100% !important;
  max-width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  position: static !important;
  left: auto !important;
}

h1,
h2,
h3,
.titulo-doc,
.acta-titulo,
.enc-titulo {
  letter-spacing: 0.2px;
}

table {
  max-width: 100%;
}

.table-bordered,
.tabla-seccion,
.tabla-productos,
.data,
.totals-grid,
.tabla-totales,
.tabla-inferior {
  width: 100% !important;
  max-width: 100% !important;
  border-collapse: collapse;
  table-layout: fixed;
  margin-left: 0 !important;
  margin-right: 0 !important;
  position: static !important;
  left: auto !important;
}

.panel-table,
.tabla-header {
  width: 100% !important;
  max-width: 100% !important;
  border-collapse: collapse;
  table-layout: fixed;
}

.table-bordered thead th,
.tabla-seccion th,
.tabla-productos th,
.data thead th {
  background: {{ $tableHeadBg }} !important;
  color: {{ $tableHeadText }} !important;
  border-bottom: 1px solid #d3dde8 !important;
  padding: {{ $headPadY }}px {{ $headPadX }}px !important;
  font-size: {{ $headFontSize }}px !important;
  line-height: {{ $tableLineHeight }} !important;
}

.table-bordered tbody tr:nth-child(odd) td,
.tabla-productos tbody tr:nth-child(odd) td,
.data tbody tr:nth-child(odd) td {
  background: {{ $rowStripeColor }} !important;
}

.table-bordered td,
.tabla-seccion td,
.tabla-productos td,
.data td {
  border-top: 1px solid #eef2f7;
  padding: {{ $cellPadY }}px {{ $cellPadX }}px !important;
  line-height: {{ $tableLineHeight }} !important;
}

.tabla-seccion th,
.tabla-productos th,
.tabla-totales th,
.tabla-inferior th,
.tabla-totales td,
.tabla-inferior td {
  padding: {{ $headPadY }}px {{ $headPadX }}px !important;
  line-height: {{ $tableLineHeight }} !important;
}

.tabla-seccion td,
.tabla-productos td,
.tabla-totales td,
.tabla-inferior td {
  padding: {{ $cellPadY }}px {{ $cellPadX }}px !important;
  line-height: {{ $tableLineHeight }} !important;
}

/* Evita desbordes por textos largos */
p,
div,
span,
td,
th,
small,
strong {
  overflow-wrap: break-word;
  word-break: break-word;
}

/* Totales: mas limpios y alineados */
.tabla-totales th,
.tabla-totales td {
  vertical-align: top;
}

.tabla-totales th {
  text-align: left !important;
}

.tabla-totales td {
  text-align: right !important;
  white-space: nowrap;
}

img,
svg,
canvas {
  max-width: 100% !important;
  height: auto !important;
}

.text-right,
.td-right,
.num,
.tot-valor {
  text-align: right !important;
  white-space: nowrap;
}

.text-center,
.center,
.td-center {
  text-align: center !important;
}

/* Quita marco del watermark para que no invada visualmente el contenido */
.watermark {
  border: 0 !important;
  padding: 0 !important;
  opacity: 0.08 !important;
  letter-spacing: 2px !important;
}

.footer {
  max-width: 100%;
  color: {{ $footerTextColor }} !important;
  border-top: 1px solid #e5e7eb;
  padding-top: 4px;
}

/* Fallback cuando no cargan imagenes de barra/logo */
.barra-fallback {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 4px;
}
.barra-fallback td {
  height: {{ $topBarHeight }}px;
  padding: 0;
  background: {{ $primaryColor }};
}
.logo-fallback {
  display: inline-block;
  border: 1px solid #d3dde8;
  padding: 6px 10px;
  background: #ffffff;
}
.logo-fallback strong {
  display: block;
  color: {{ $secondaryColor }};
  font-size: 12px;
  letter-spacing: 0.2px;
}
.logo-fallback span {
  display: block;
  color: {{ $mutedTextColor }};
  font-size: 9px;
}

.logo {
  @if(!empty($pdfTheme['logo_width']))
  width: {{ $pdfTheme['logo_width'] }}px !important;
  @else
  width: auto !important;
  @endif
  height: {{ $logoHeight }}px !important;
  object-fit: contain;
  object-position: left center;
}

.td-logo {
  width: 48% !important;
}

.td-info {
  width: 52% !important;
  padding-right: 0 !important;
}

.info-empresa {
  width: 100% !important;
  max-width: 100% !important;
  text-align: right !important;
}

.info-empresa strong,
.info-empresa span {
  display: block;
  width: 100%;
  max-width: 100%;
  overflow-wrap: break-word;
  word-break: normal;
}

.barra-superior {
  height: {{ $topBarHeight }}px !important;
}

.panel-wrap,
.table-wrap,
.desc,
.totales,
.section,
.panel-acta,
.panel-orden,
.panel-encabezado,
.panel-inferior,
.totales-panel,
.firma-layout,
.tabla-seccion,
.tabla-productos {
  margin-top: {{ $sectionGap }}px !important;
}

@if($templateKey === 'cotizacion')
.tabla-productos th.cant,
.tabla-productos td.cant {
  width: {{ $pdfTheme['cantidad_width'] ?? 34 }}px !important;
}
.tabla-productos th.unit-price,
.tabla-productos td.unit-price {
  width: {{ $pdfTheme['unit_price_width'] ?? 72 }}px !important;
}
.tabla-productos th.total-col,
.tabla-productos td.total-col {
  width: {{ $pdfTheme['total_width'] ?? 72 }}px !important;
}
.firma-col-text {
  width: {{ $pdfTheme['text_column_width'] ?? 55 }}% !important;
}
.firma-col-sign {
  width: {{ $pdfTheme['signature_column_width'] ?? 45 }}% !important;
}
@endif

@if($templateKey === 'orden_servicio')
.w-desc { width: {{ $pdfTheme['desc_width'] ?? 55 }}% !important; }
.w-cant { width: {{ $pdfTheme['qty_width'] ?? 15 }}% !important; }
.w-pre { width: {{ $pdfTheme['unit_width'] ?? 15 }}% !important; }
.w-total { width: {{ $pdfTheme['total_width'] ?? 15 }}% !important; }
.firma-col-text { width: {{ $pdfTheme['signature_text_width'] ?? 55 }}% !important; }
.firma-col-sign { width: {{ $pdfTheme['signature_block_width'] ?? 45 }}% !important; }
@endif

@if(in_array($templateKey, ['acta_conformidad', 'acta_conformidad_tecnico'], true))
.tabla-detalle-acta thead th:nth-child(1),
.tabla-detalle-acta tbody td:nth-child(1),
.tabla-extra-acta thead th:nth-child(1),
.tabla-extra-acta tbody td:nth-child(1) { width: {{ $pdfTheme['product_width'] ?? 54 }}% !important; }
.tabla-detalle-acta thead th:nth-child(2),
.tabla-detalle-acta tbody td:nth-child(2),
.tabla-extra-acta thead th:nth-child(2),
.tabla-extra-acta tbody td:nth-child(2) { width: {{ $pdfTheme['qty_width'] ?? 10 }}% !important; }
.tabla-detalle-acta thead th:nth-child(3),
.tabla-detalle-acta tbody td:nth-child(3),
.tabla-extra-acta thead th:nth-child(3),
.tabla-extra-acta tbody td:nth-child(3) { width: {{ $pdfTheme['unit_width'] ?? 18 }}% !important; }
.tabla-detalle-acta thead th:nth-child(4),
.tabla-detalle-acta tbody td:nth-child(4),
.tabla-extra-acta thead th:nth-child(4),
.tabla-extra-acta tbody td:nth-child(4) { width: {{ $pdfTheme['total_width'] ?? 18 }}% !important; }
.sign-line,
.firma-line { width: {{ $pdfTheme['signature_line_width'] ?? 220 }}px !important; }
@endif

@if($templateKey === 'reportes')
.panel-td-left { width: {{ $pdfTheme['panel_left_width'] ?? 78 }}% !important; }
.panel-td-right { width: {{ $pdfTheme['panel_right_width'] ?? 22 }}% !important; }
.data,
.table-bordered { font-size: {{ $pdfTheme['table_font_size'] ?? 9.8 }}px !important; }
@endif

@if(!empty($pdfPreviewMode))
body {
  padding-bottom: 24px !important;
}
.footer {
  position: static !important;
  margin-top: 18px;
}
@endif
