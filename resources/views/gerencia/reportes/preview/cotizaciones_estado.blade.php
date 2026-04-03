{{-- resources/views/vistas-gerente/reportes/preview/cotizaciones_estado.blade.php --}}

<div x-show="f.tipo === 'cotizaciones_estado'" x-cloak class="space-y-6">

    {{-- Cuando SI hay datos de cotizaciones --}}
    <template x-if="tabla.rows.length">
        <div class="space-y-6">

            {{-- Resumen superior --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">
                        Resumen del reporte de cotizaciones
                    </h3>
                    <p class="text-xs text-slate-500">
                        Previsualiza como se vera el PDF y el Excel antes de descargar.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span>
                        <span x-text="`${tabla.rows.length} cotizaciones encontradas`"></span>
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-50 text-slate-500 border border-slate-100">
                        Rango:
                        <span class="ml-1 font-medium" x-text="rango"></span>
                    </span>
                </div>
            </div>

            {{-- ==================== PREVIEW PDF ==================== --}}
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="px-5 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.15em] text-slate-400">
                            Vista previa PDF
                        </div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-100 text-red-500 text-[10px] font-bold">
                                P
                            </span>
                            Hoja tamano carta
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-red-50 text-red-600 border border-red-100">
                        PDF
                    </span>
                </div>

                {{-- "Hoja" simulando el PDF --}}
                <div class="p-4 sm:p-5 bg-slate-50">
                    <div class="bg-white rounded-xl shadow-inner border border-slate-200 overflow-hidden">
                        <div class="p-4 sm:p-5 space-y-4 text-[11px] sm:text-xs">

                            {{-- Encabezado como el PDF --}}
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-[11px] font-semibold text-blue-700 tracking-wide">
                                        E-SUPPORT QUERETARO
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">
                                        Reporte de cotizaciones
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-400">Rango del reporte</div>
                                    <div class="text-[11px] text-slate-700 font-medium" x-text="rango"></div>
                                </div>
                            </div>

                            {{-- Tabla recortada con SCROLL HORIZONTAL --}}
                            <div class="border-t border-dashed border-slate-200 pt-3">
                                <div class="overflow-x-auto">
                                    <div class="min-w-[900px] grid gap-2 text-[11px]"
                                         :style="`grid-template-columns: repeat(${Math.max(tabla.cols.length, 1)}, minmax(0, 1fr));`">

                                        {{-- Encabezados --}}
                                        <template x-for="(col, ci) in tabla.cols" :key="'cot-pdf-h-'+ci">
                                            <div
                                                class="font-semibold text-slate-700 pb-1 border-b border-slate-200 bg-slate-50/60 px-1 rounded text-[11px] whitespace-nowrap"
                                                x-text="col">
                                            </div>
                                        </template>

                                        {{-- Primeras 8 filas --}}
                                        <template x-for="(row, ri) in tabla.rows.slice(0, 8)" :key="'cot-pdf-r-'+ri">
                                            <template x-for="(col, ci) in tabla.cols" :key="'cot-pdf-c-'+ri+'-'+ci">
                                                <div
                                                    class="py-1 px-1 border-b border-dotted border-slate-100 whitespace-nowrap text-[11px] text-slate-600 truncate"
                                                    x-text="row[col] ?? '-'">
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <div class="text-[10px] text-slate-400">
                                    Mostrando solo las primeras 8 filas.
                                </div>
                                <div class="text-[10px] text-slate-400 italic">
                                    El PDF contendra todas las cotizaciones del reporte.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================== PREVIEW EXCEL =================== --}}
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="px-5 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.15em] text-slate-400">
                            Vista previa Excel
                        </div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-600 text-[10px] font-bold">
                                X
                            </span>
                            Plantilla de cotizaciones
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-green-50 text-green-700 border border-green-100">
                        Excel
                    </span>
                </div>

                <div class="p-4 sm:p-5 bg-slate-50">
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-[11px] sm:text-xs">
                                <thead class="bg-emerald-50 border-b border-emerald-100">
                                    <tr>
                                        <template x-for="(col, ci) in tabla.cols" :key="'cot-xl-h-'+ci">
                                            <th
                                                class="px-2.5 py-1.5 border-r border-emerald-100 text-left font-semibold text-emerald-800 text-[11px] whitespace-nowrap"
                                                x-text="col">
                                            </th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Primeras 10 filas --}}
                                    <template x-for="(row, ri) in tabla.rows.slice(0, 10)" :key="'cot-xl-r-'+ri">
                                        <tr class="odd:bg-white even:bg-slate-50/80">
                                            <template x-for="(col, ci) in tabla.cols" :key="'cot-xl-c-'+ri+'-'+ci">
                                                <td
                                                    class="px-2.5 py-1.5 border-t border-slate-100 border-r last:border-r-0 whitespace-nowrap text-[11px] text-slate-700"
                                                    x-text="row[col] ?? '-'">
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50 text-[10px] text-slate-400 flex items-center justify-between">
                            <span>Vista previa rapida basada en las primeras 10 filas.</span>
                            <span class="hidden sm:inline">
                                El archivo Excel incluira todas las columnas y cotizaciones del reporte.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Texto explicativo --}}
            <div class="text-xs text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-xl border border-dashed border-gray-200">
                <p>
                    Este reporte muestra las cotizaciones generadas en el periodo seleccionado,
                    indicando el cliente, cuantas veces fueron procesadas, cuantas veces se edito
                    la vigencia, asi como la moneda y el total actual. Es util para dar seguimiento
                    al ciclo de vida de las cotizaciones y detectar oportunidades de mejora.
                </p>
            </div>

        </div>
    </template>

    {{-- Cuando NO hay datos --}}
    <template x-if="!tabla.rows.length">
        <div class="bg-gradient-to-r from-slate-50 to-slate-100 p-8 rounded-2xl border border-dashed border-slate-300 text-center">
            <div class="flex flex-col items-center space-y-3">
                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3h18v4H3z" />
                        <path d="M3 7h18v14H3z" />
                        <path d="M8 11h3" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600">
                    Aun no hay cotizaciones para este rango de fechas.
                </p>
                <p class="text-xs text-slate-500 max-w-md">
                    Ajusta el rango de fechas en la columna izquierda y vuelve a generar el reporte
                    para ver la previsualizacion del PDF y del Excel.
                </p>
            </div>
        </div>
    </template>

</div>

