<div x-show="f.tipo === 'tecnicos_top'" class="space-y-6">

    <!-- Cuando SI hay datos -->
    <template x-if="tabla.rows.length">
        <div class="space-y-4">

            <!-- PREVIEW PDF -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 tracking-wide uppercase">
                            Vista previa - PDF
                        </div>
                        <div class="text-sm font-semibold text-slate-800">
                            Tecnicos con mas ordenes
                        </div>
                    </div>
                </div>

                <div class="max-h-[45vh] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <template x-for="(col, ci) in tabla.cols" :key="'tec-pdf-h-'+ci">
                                    <th class="px-4 py-2 font-semibold border-b border-slate-200" x-text="col"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(row, ri) in tabla.rows" :key="'tec-pdf-r-'+ri">
                                <tr>
                                    <template x-for="(col, ci) in tabla.cols" :key="'tec-pdf-c-'+ri+'-'+ci">
                                        <td class="px-4 py-2 text-slate-700" x-text="row[col] ?? '-'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-2 border-t border-slate-100 text-[11px] text-slate-500">
                    El PDF se generara con estas mismas columnas y filas.
                </div>
            </div>

            <!-- PREVIEW EXCEL -->
            <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200/60">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 tracking-wide uppercase">
                            Vista previa - Excel
                        </div>
                        <div class="text-xs text-slate-600">
                            Archivo .xlsx con el mismo ranking de tecnicos.
                        </div>
                    </div>
                </div>

                <div class="max-h-[40vh] overflow-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-white text-[11px] uppercase text-slate-500">
                            <tr>
                                <template x-for="(col, ci) in tabla.cols" :key="'tec-xls-h-'+ci">
                                    <th class="px-3 py-2 font-semibold border-b border-slate-200" x-text="col"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(row, ri) in tabla.rows" :key="'tec-xls-r-'+ri">
                                <tr>
                                    <template x-for="(col, ci) in tabla.cols" :key="'tec-xls-c-'+ri+'-'+ci">
                                        <td class="px-3 py-1.5 text-slate-700" x-text="row[col] ?? '-'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-2 border-t border-slate-100 text-[11px] text-slate-500">
                    El Excel utilizara estas mismas columnas en el mismo orden.
                </div>
            </div>

        </div>
    </template>

    <!-- Cuando NO hay datos -->
    <template x-if="!tabla.rows.length">
        <p class="text-xs text-slate-500">
            No se encontraron tecnicos con ordenes en el periodo seleccionado.
        </p>
    </template>
</div>

