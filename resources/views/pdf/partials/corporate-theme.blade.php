/* ===== Corporate Theme (Global PDF) ===== */
@page { margin: 30px 35px; }
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
  color: #0f172a;
  letter-spacing: 0.05px;
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
  width: auto !important;
  max-width: 100% !important;
  margin-left: 6px !important;
  margin-right: 6px !important;
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
.panel-table,
.totals-grid,
.tabla-header,
.tabla-totales,
.tabla-inferior {
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.table-bordered thead th,
.tabla-seccion th,
.tabla-productos th,
.data thead th {
  background: #edf2f7 !important;
  color: #0b4f88 !important;
  border-bottom: 1px solid #d3dde8 !important;
}

.table-bordered tbody tr:nth-child(odd) td,
.tabla-productos tbody tr:nth-child(odd) td,
.data tbody tr:nth-child(odd) td {
  background: #fafcff !important;
}

.table-bordered td,
.tabla-seccion td,
.tabla-productos td,
.data td {
  border-top: 1px solid #eef2f7;
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
  padding: 5px 8px !important;
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
  color: #4b5563 !important;
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
  height: 8px;
  padding: 0;
  background: #0072bc;
}
.logo-fallback {
  display: inline-block;
  border: 1px solid #d3dde8;
  padding: 6px 10px;
  background: #ffffff;
}
.logo-fallback strong {
  display: block;
  color: #0b4f88;
  font-size: 12px;
  letter-spacing: 0.2px;
}
.logo-fallback span {
  display: block;
  color: #475569;
  font-size: 9px;
}
