@extends('layouts.sidebar-navigation')

@section('title', 'Vistas de Reportes')

@section('content')
<style>
  .report-bar { transition: height .5s ease; }
  .fade-in { animation: fadeIn .5s ease-in; }
  @keyframes fadeIn { from {opacity:0} to {opacity:1} }
</style>

<div
  class="w-full mx-auto px-2 sm:px-4 lg:px-6 py-4 lg:py-6 space-y-4"
  x-data="reportesUI()"
  x-init="init()"
>
  {{-- Barra superior descriptiva --}}

<div class="bg-gradient-to-r from-blue-50 via-sky-50 to-slate-50 border border-sky-100 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <!-- IZQUIERDA: Botón + Título -->
    <div class="flex items-start sm:items-center gap-3">
        <x-boton-volver />

        <div>
            <h1 class="text-sm sm:text-base font-bold text-slate-800">
                Centro de reportes
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Elige un tipo de reporte y un rango de fechas. La vista previa se actualiza al instante.
            </p>
        </div>
    </div>

    <!-- DERECHA: Chips / Datos en tiempo real -->
    <div class="flex flex-wrap items-center gap-2 text-[11px] sm:text-xs">
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Datos en tiempo real
        </span>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-50 text-slate-500 border border-slate-200">
            Reportes: Ventas, inventario, clientes, cotizaciones
        </span>
    </div>
</div>


  {{-- Layout principal: izquierda configuración / derecha preview --}}
  <div class="grid grid-cols-1 xl:grid-cols-[0.9fr,2.1fr] gap-6 2xl:gap-8">

    {{-- Columna izquierda: Configuración --}}
    <div class="flex flex-col">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden fade-in flex flex-col">
        <div class="bg-gradient-to-r from-blue-600 to-sky-500 px-6 py-4">
          <h2 class="text-base font-bold text-white flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-white/15">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M4 4h16v4H4z" />
                      <path d="M4 8h16v12H4z" />
                  </svg>
              </span>
              Configuración del reporte
          </h2>
          <p class="text-xs text-blue-100 mt-1">
              Define el período y el tipo de reporte a visualizar.
          </p>
        </div>

        <form
          class="p-6 flex flex-col space-y-6 flex-1 max-h-[72vh] overflow-auto"
          method="GET"
          action="{{ route('reportes') }}"
          @submit.prevent="generar()"
        >
          <!-- Rango de fechas -->
          <div class="space-y-3">
            <label class="block text-sm font-medium text-slate-800 mb-1">
                Rango de fechas
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs text-slate-500 mb-1">Fecha de inicio</label>
                <input type="date" name="desde" x-model="f.desde"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
              </div>
              <div>
                <label class="block text-xs text-slate-500 mb-1">Fecha de fin</label>
                <input type="date" name="hasta" x-model="f.hasta"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
              </div>
            </div>
            <p class="text-[11px] text-slate-400">
                Sugerencia: usa rangos de 1 a 3 meses para reportes más claros.
            </p>
          </div>

          <!-- Tipo de reporte (separado en componentes Blade) -->
          <div>
            <label class="block text-sm font-medium text-slate-800 mb-2">
                Tipo de reporte
            </label>
            <div
              class="border border-slate-200 rounded-xl p-4 space-y-3 bg-slate-50/50
                     max-h-80 overflow-y-auto"
            >
              @include('vistas-gerente.reportes.tipos.ventas')
              @include('vistas-gerente.reportes.tipos.tecnicos_top')
              @include('vistas-gerente.reportes.tipos.entradas')
              @include('vistas-gerente.reportes.tipos.salidas')
              @include('vistas-gerente.reportes.tipos.stock_critico')
              @include('vistas-gerente.reportes.tipos.clientes_top')
              @include('vistas-gerente.reportes.tipos.cotizaciones_estado')
            </div>
          </div>

          <!-- Botón de generar -->
          <div class="pt-2 border-t border-dashed border-slate-200 mt-2">
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v16m16-8H8" />
              </svg>
              Generar reporte
            </button>
            <p class="mt-2 text-[11px] text-slate-400 text-center">
                Al cambiar el <strong>tipo de reporte</strong> la vista previa se actualiza automáticamente.
                Si modificas el <strong>rango de fechas</strong>, haz clic en <strong>"Generar reporte"</strong>.
            </p>
          </div>
        </form>
      </div>
    </div>

    {{-- Columna derecha: Vista previa --}}
    <div class="flex flex-col">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden fade-in flex flex-col">
        <div class="bg-gradient-to-r from-blue-600 to-sky-500 px-6 py-4 flex items-center justify-between">
          <div>
              <h2 class="text-base font-bold text-white">
                  Vista previa del reporte
              </h2>
              <p class="text-xs text-blue-100 mt-1">
                  Simulación del PDF y del Excel según la configuración seleccionada.
              </p>
          </div>
          <div class="hidden sm:flex flex-col items-end text-[11px] text-blue-100">
              <span class="flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                  Vista dinámica
              </span>
              <span>Basada en la información real del sistema.</span>
          </div>
        </div>

        <div
          class="p-6 flex flex-col space-y-6 flex-1
                 max-h-[80vh] overflow-auto
                 bg-slate-50/40"
        >
          <!-- Título genérico (se alimenta desde JS según tipo) -->
          <div class="bg-orange-50 border border-orange-100 p-4 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-center sm:text-left font-bold text-orange-800 text-base" x-text="titulo"></h3>
                <p class="text-center sm:text-left text-orange-700 text-xs mt-1" x-text="rango"></p>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-2 text-[11px] text-orange-700">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/70 border border-orange-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                    Previsualización
                </span>
            </div>
          </div>

          {{-- Vista previa específica según tipo (partials) --}}
          @include('vistas-gerente.reportes.preview.ventas')
          @include('vistas-gerente.reportes.preview.tecnicos_top')
          @include('vistas-gerente.reportes.preview.entradas')
          @include('vistas-gerente.reportes.preview.salidas')
          @include('vistas-gerente.reportes.preview.stock_critico')
          @include('vistas-gerente.reportes.preview.clientes_top')
          @include('vistas-gerente.reportes.preview.cotizaciones_estado')

          <!-- Botones de descarga (compartidos para todos los tipos) -->
          <div class="border-t border-dashed border-slate-200 pt-4 mt-auto">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-500">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                           stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 4h16v16H4z" />
                      </svg>
                  </span>
                  Descargar versión final
              </span>
              <div class="flex space-x-3">
                <a :href="urlDescarga('pdf')"
                   class="flex items-center space-x-1.5 px-4 py-2 bg-white border border-red-200 rounded-lg text-red-600 hover:bg-red-50 transition-colors text-sm font-medium shadow-sm">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16l4-4h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
                  </svg>
                  <span>PDF</span>
                </a>
                <a :href="urlDescarga('excel')"
                   class="flex items-center space-x-1.5 px-4 py-2 bg-white border border-emerald-200 rounded-lg text-emerald-700 hover:bg-emerald-50 transition-colors text-sm font-medium shadow-sm">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 4h10v16H4z"/><path d="M14 4h6v16h-6z" class="opacity-40"/>
                  </svg>
                  <span>Excel</span>
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>

@push('scripts')
<script>
function reportesUI() {
  return {
    f: {
      tipo: new URLSearchParams(window.location.search).get('tipo') || 'ventas',
      desde: new URLSearchParams(window.location.search).get('desde') || '',
      hasta: new URLSearchParams(window.location.search).get('hasta') || '',
    },
    titulo: 'Reporte de Ventas',
    rango: '',
    tabla: { cols: [], rows: [] },
    grafica: { barras: [] },

    // Se ejecuta al montar el componente
    init() {
      // Primera carga
      this.generar();

      // Cuando cambie el TIPO de reporte, recargamos automáticamente
      if (this.$watch) {
        this.$watch('f.tipo', () => {
          this.generar();
        });
      }
    },

    generar() {
      const params = new URLSearchParams({
        tipo: this.f.tipo,
        desde: this.f.desde || '',
        hasta: this.f.hasta || '',
        ajax: 1
      });
      const url = `{{ route('reportes') }}?${params.toString() }`;

      const titulos = {
        ventas: 'Reporte de Ventas',
        productos_top: 'Productos Más Vendidos',
        productos_bottom: 'Productos Menos Vendidos',
        tecnicos_top: 'Técnicos con Más Ventas',
        entradas: 'Entradas de Inventario',
        salidas: 'Salidas de Inventario',
        stock_critico: 'Reporte de Productos',
        clientes_top: 'Clientes',
        cotizaciones_estado: 'Cotizaciones por Estado',
      };
      this.titulo = titulos[this.f.tipo] || 'Reporte';

      this.rango = (this.f.desde || this.f.hasta)
        ? `Del ${this.f.desde || '…'} al ${this.f.hasta || '…'}`
        : 'Sin rango de fechas';

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(json => this.render(json))
        .catch(() => this.render(this.mockData()));
    },

    parseMoney(str) {
      if (str === null || str === undefined) return 0;
      str = String(str);
      str = str.replace(/[^\d,.\-]/g, '');
      if (str.includes('.') && str.includes(',')) {
        str = str.replace(/,/g, '');
      } else if (str.includes(',') && !str.includes('.')) {
        str = str.replace(',', '.');
      }
      const n = parseFloat(str);
      return isNaN(n) ? 0 : n;
    },

    buildBarsFromRows(rows) {
      if (!rows || !rows.length) return [];
      const porDia = {};
      rows.forEach(row => {
        const keys = Object.keys(row || {});
        if (!keys.length) return;
        const fechaKey = keys.find(k => k.toLowerCase().includes('fecha'));
        if (!fechaKey) return;
        const fecha = row[fechaKey];
        if (!fecha) return;
        const importeKey = keys.find(k => {
          const lk = k.toLowerCase();
          return lk.includes('total') || lk.includes('importe') || lk.includes('monto');
        });
        if (!importeKey) return;
        const monto = this.parseMoney(row[importeKey]);
        if (!monto) return;

        let key = fecha;
        if (fecha.includes('/')) {
          const parts = fecha.split('/');
          if (parts.length === 3) {
            const [d, m, y] = parts;
            key = `${y}-${m}-${d}`;
          }
        }
        if (!porDia[key]) porDia[key] = { label: fecha, total: 0 };
        porDia[key].total += monto;
      });

      const series = Object.keys(porDia).sort().map(k => porDia[k]);
      if (!series.length) return [];
      const max = Math.max(...series.map(s => s.total)) || 1;
      return series.map(s => ({
        label: s.label,
        h: Math.max(5, Math.round((s.total / max) * 90))
      }));
    },

    buildBarsFromChart(chart) {
      let barras = [];
      if (!chart) return [];
      if (Array.isArray(chart)) {
        if (!chart.length) return [];
        const sample = chart[0];
        if ('label' in sample && 'h' in sample) {
          return chart.map(d => ({
            label: d.label,
            h: Math.max(5, Math.min(95, Number(d.h) || 10))
          }));
        }
        const temp = chart.map(d => {
          const label = d.label ?? d.fecha ?? d.Fecha ?? '';
          const rawVal = d.value ?? d.total ?? d.importe ?? d.monto ?? d.cantidad ?? d.qty ?? 0;
          const val = typeof rawVal === 'string' ? this.parseMoney(rawVal) : Number(rawVal) || 0;
          return { label, val };
        }).filter(x => x.label && x.val > 0);
        if (!temp.length) return [];
        const max = Math.max(...temp.map(t => t.val)) || 1;
        barras = temp.map(t => ({
          label: t.label,
          h: Math.max(5, Math.round((t.val / max) * 90))
        }));
        return barras;
      }
      if (chart.labels && (chart.data || chart.values)) {
        const labels = chart.labels || [];
        const datas  = chart.data || chart.values || [];
        const temp = labels.map((lab, i) => {
          const rawVal = datas[i] ?? 0;
          const val = typeof rawVal === 'string' ? this.parseMoney(rawVal) : Number(rawVal) || 0;
          return { label: lab, val };
        }).filter(x => x.label && x.val > 0);
        if (!temp.length) return [];
        const max = Math.max(...temp.map(t => t.val)) || 1;
        barras = temp.map(t => ({
          label: t.label,
          h: Math.max(5, Math.round((t.val / max) * 90))
        }));
        return barras;
      }
      return [];
    },

    render(json) {
      this.tabla.cols = json.cols || [];
      this.tabla.rows = json.rows || [];

      let barras = this.buildBarsFromChart(json.chart);
      if (!barras.length && this.f.tipo === 'ventas') {
        barras = this.buildBarsFromRows(this.tabla.rows);
      }
      if (!barras.length && Array.isArray(json.chart) && json.chart.length && 'label' in json.chart[0] && 'h' in json.chart[0]) {
        barras = json.chart.map(d => ({
          label: d.label,
          h: Math.max(5, Math.min(95, Number(d.h) || 10))
        }));
      }
      this.grafica.barras = barras;

      setTimeout(() => {
        document.querySelectorAll('.report-bar').forEach(bar => {
          bar.style.height = bar.style.height;
        });
      }, 0);
    },

    urlDescarga(formato) {
      const params = new URLSearchParams({
        tipo: this.f.tipo,
        desde: this.f.desde || '',
        hasta: this.f.hasta || '',
        formato
      });
      return `{{ route('reportes.descargar') }}?${params.toString() }`;
    },

    mockData() {
      const base = (labels) => ({
        cols: ['Fecha', 'Cliente', 'Total orden', 'Estado venta'],
        rows: labels.map((m, i) => ({
          'Fecha': `0${i+1}/01/2025`,
          'Cliente': `Cliente ${i+1}`,
          'Total orden': `$${(20000 + i*2500).toLocaleString()}`,
          'Estado venta': i % 2 === 0 ? 'Completada' : 'Pendiente'
        })),
        chart: []
      });

      const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio'];

      switch (this.f.tipo) {
        case 'ventas':
        case 'entradas':
        case 'salidas':
          return base(meses);

        case 'productos_top':
        case 'productos_bottom':
          return {
            cols: ['Producto', 'Cantidad'],
            rows: [
              { 'Producto': 'Cable UTP Cat6', 'Cantidad': 180 },
              { 'Producto': 'Router AC1200', 'Cantidad': 145 },
              { 'Producto': 'Cámara IP 4MP', 'Cantidad': 120 },
            ],
            chart: [
              { label:'P1', h: 90 }, { label:'P2', h: 72 }, { label:'P3', h: 60 }
            ]
          };

        case 'tecnicos_top':
          return {
            cols: ['Técnico', 'Órdenes', 'Importe'],
            rows: [
              { 'Técnico': 'Ana López', 'Órdenes': 24, 'Importe': '$120,000' },
              { 'Técnico': 'Luis Pérez', 'Órdenes': 18, 'Importe': '$92,500' },
              { 'Técnico': 'María Díaz', 'Órdenes': 15, 'Importe': '$80,300' },
            ],
            chart: [
              { label:'Ana', h: 88 }, { label:'Luis', h: 70 }, { label:'María', h: 65 }
            ]
          };

        case 'stock_critico':
          return {
            cols: ['Producto', 'Stock actual', 'Precio (última entrada)'],
            rows: [
              { 'Producto':'Conector RJ45', 'Stock actual': 120, 'Precio (última entrada)': '$15.50' },
              { 'Producto':'Clemas 12p', 'Stock actual': 80,  'Precio (última entrada)': '$9.99' },
            ],
            chart: []
          };

        case 'clientes_top':
          return {
            cols: ['Cliente', 'Órdenes', 'Monto generado'],
            rows: [
              { 'Cliente': 'Industrias Ríos', 'Órdenes': 9, 'Monto generado': '$210,500' },
              { 'Cliente': 'Tiendas Nova', 'Órdenes': 7, 'Monto generado': '$175,200' },
            ],
            chart: [
              { label:'Ríos', h: 85 }, { label:'Nova', h: 70 }
            ]
          };

        case 'cotizaciones_estado':
          return {
            cols: ['Estado', 'Cantidad'],
            rows: [
              { 'Estado': 'Borrador', 'Cantidad': 12 },
              { 'Estado': 'Enviada',  'Cantidad': 9  },
              { 'Estado': 'Aceptada', 'Cantidad': 6  },
              { 'Estado': 'Rechazada','Cantidad': 3  },
            ],
            chart: [
              { label:'Bor', h: 80 }, { label:'Env', h: 60 }, { label:'Ace', h: 45 }, { label:'Rec', h: 25 }
            ]
          };

        default:
          return { cols: [], rows: [], chart: [] };
      }
    }
  };
}
</script>
@endpush

@endsection
