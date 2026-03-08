@extends('layouts.sidebar-navigation')

@section('title', 'Carga rápida de inventario')

@section('content')
<div class="min-h-screen bg-slate-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Carga rápida de inventario</h1>
                <p class="text-slate-500 mt-1">
                    Registra entradas masivas sobre productos existentes del catálogo.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('inventario') }}"
                   class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-xl shadow-sm hover:bg-slate-100 transition">
                    ← Volver a inventario
                </a>

                <a href="{{ route('inventario.carga_rapida.plantilla') }}"
                   class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2.5 rounded-xl shadow hover:bg-emerald-700 transition">
                    Descargar plantilla Excel
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-5 py-4 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 shadow-sm">
                <p class="font-semibold mb-2">Corrige lo siguiente:</p>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('import_errors'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 shadow-sm">
                <p class="font-semibold mb-2">Filas saltadas</p>
                <ul class="list-disc ml-5 space-y-1 max-h-56 overflow-y-auto">
                    @foreach (session('import_errors') as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 bg-gradient-to-r from-slate-900 to-slate-700 text-white">
                    <h2 class="text-xl font-semibold">Subir archivo</h2>
                    <p class="text-sm text-slate-200 mt-1">
                        Usa la plantilla recomendada para que el sistema identifique bien los encabezados.
                    </p>
                </div>

                <div class="p-6">
                    <form method="POST" action="{{ route('inventario.carga_rapida.preview') }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Archivo Excel / CSV</label>
                            <input
                                type="file"
                                name="archivo"
                                accept=".xlsx,.csv,.txt"
                                class="block w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200"
                                required
                            >
                            <p class="text-xs text-slate-500 mt-2">
                                Formatos permitidos: XLSX, CSV y TXT.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-3 rounded-xl shadow hover:bg-blue-700 transition"
                            >
                                Generar vista previa
                            </button>

                            <a href="{{ route('inventario.carga_rapida.plantilla') }}"
                               class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-5 py-3 rounded-xl shadow-sm hover:bg-slate-100 transition">
                                Descargar plantilla
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-800">Campos de la plantilla</h2>
                </div>

                <div class="p-6 space-y-4 text-sm">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="inline-flex rounded-full bg-red-100 text-red-700 px-3 py-1 font-semibold">Obligatorios</span>
                        <span class="text-slate-600">numero_parte o codigo_producto</span>
                        <span class="text-slate-600">tipo_control</span>
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="font-semibold text-slate-700 mb-2">Obligatorios según el caso</p>
                        <ul class="space-y-1 text-slate-600">
                            <li>• <strong>cantidad</strong> si el tipo es PIEZAS o PAQUETES.</li>
                            <li>• <strong>piezas_por_paquete</strong> si el tipo es PAQUETES.</li>
                            <li>• <strong>numeros_serie</strong> si el tipo es SERIE.</li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full bg-blue-100 text-blue-700 px-3 py-1 font-semibold">Opcionales</span>
                        <span class="text-slate-600">proveedor</span>
                        <span class="text-slate-600">rfc</span>
                        <span class="text-slate-600">costo</span>
                        <span class="text-slate-600">precio</span>
                        <span class="text-slate-600">fecha_entrada</span>
                        <span class="text-slate-600">fecha_caducidad</span>
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <p class="font-semibold text-slate-700 mb-2">La plantilla incluye</p>
                        <ul class="space-y-1 text-slate-600">
                            <li>• Hoja <strong>Plantilla</strong> para llenar.</li>
                            <li>• Hoja <strong>Instrucciones</strong> con campos obligatorios y opcionales.</li>
                            <li>• Hoja <strong>Ejemplos</strong> con un ejemplo completo y otro mínimo.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @if ($preview)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <p class="text-sm text-slate-500">Total</p>
                    <p class="text-3xl font-bold text-slate-800">{{ $stats['total'] ?? 0 }}</p>
                </div>
                <div class="bg-emerald-50 rounded-2xl border border-emerald-200 shadow-sm p-5">
                    <p class="text-sm text-emerald-700">Aceptables</p>
                    <p class="text-3xl font-bold text-emerald-800">{{ $stats['aceptables'] ?? 0 }}</p>
                </div>
                <div class="bg-red-50 rounded-2xl border border-red-200 shadow-sm p-5">
                    <p class="text-sm text-red-700">Inválidas</p>
                    <p class="text-3xl font-bold text-red-800">{{ $stats['invalidos'] ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200">
                    <h2 class="text-xl font-semibold text-slate-800">Vista previa</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Fila</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-left">Producto</th>
                                <th class="px-4 py-3 text-left">Número de parte</th>
                                <th class="px-4 py-3 text-left">Proveedor</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-left">Cantidad</th>
                                <th class="px-4 py-3 text-left">Pzs/paq</th>
                                <th class="px-4 py-3 text-left">Costo</th>
                                <th class="px-4 py-3 text-left">Precio</th>
                                <th class="px-4 py-3 text-left">Series</th>
                                <th class="px-4 py-3 text-left">Motivo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($items as $item)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">{{ $item['_row'] ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        @if (($item['estado'] ?? '') === 'ACEPTAR')
                                            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {{ $item['estado'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                                                {{ $item['estado'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item['producto_nombre'] ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['numero_parte'] ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['proveedor'] ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['tipo_control'] ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['cantidad'] ?? 0 }}</td>
                                    <td class="px-4 py-3">{{ $item['piezas_por_paquete'] ?? 0 }}</td>
                                    <td class="px-4 py-3">${{ number_format((float) ($item['costo'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">${{ number_format((float) ($item['precio'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if (!empty($item['numeros_serie']))
                                            {{ count($item['numeros_serie']) }} serie(s)
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">{{ $item['motivo'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 border-t border-slate-200 bg-slate-50">
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('inventario.carga_rapida.confirmar') }}">
                            @csrf
                            <textarea name="payload" class="hidden">{{ json_encode($items, JSON_UNESCAPED_UNICODE) }}</textarea>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 bg-emerald-600 text-white px-5 py-3 rounded-xl shadow hover:bg-emerald-700 transition"
                            >
                                Confirmar importación
                            </button>
                        </form>

                        <a href="{{ route('inventario.carga_rapida.index') }}"
                           class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-5 py-3 rounded-xl shadow-sm hover:bg-slate-100 transition">
                            Cargar otro archivo
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
