@extends('layouts.sidebar-navigation')

@section('title', 'Carga rápida de inventario')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-gradient-to-r from-emerald-600 to-teal-600 text-white">
            <h1 class="text-2xl font-bold">Carga rápida de inventario</h1>
            <p class="text-sm text-emerald-100 mt-1">
                Esta pantalla solo registra entradas de stock sobre productos existentes.
            </p>
        </div>

        <div class="p-6">
            @if (session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3">
                    <ul class="list-disc ml-5 space-y-1">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('import_errors'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3">
                    <p class="font-semibold mb-2">Filas saltadas</p>
                    <ul class="list-disc ml-5 space-y-1 max-h-48 overflow-y-auto">
                        @foreach (session('import_errors') as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!$preview)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <form method="POST" action="{{ route('inventario.carga_rapida.preview') }}" enctype="multipart/form-data" class="space-y-5">
                            @csrf

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Archivo</label>
                                <input
                                    type="file"
                                    name="archivo"
                                    accept=".xlsx,.csv,.txt"
                                    class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring focus:ring-emerald-200"
                                    required
                                >
                                <p class="text-xs text-slate-500 mt-2">
                                    Formatos permitidos: XLSX, CSV o TXT.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-white font-semibold shadow hover:bg-emerald-700 transition"
                                >
                                    Generar vista previa
                                </button>

                                <a
                                    href="{{ route('inventario') }}"
                                    class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 text-slate-700 font-semibold hover:bg-slate-50 transition"
                                >
                                    Volver a inventario
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5">
                        <h2 class="text-lg font-semibold text-slate-800 mb-3">Columnas sugeridas</h2>
                        <ul class="text-sm text-slate-700 space-y-2">
                            <li><strong>numero_parte</strong> o <strong>codigo_producto</strong></li>
                            <li><strong>proveedor</strong></li>
                            <li><strong>rfc</strong></li>
                            <li><strong>costo</strong></li>
                            <li><strong>precio</strong></li>
                            <li><strong>tipo_control</strong></li>
                            <li><strong>cantidad</strong></li>
                            <li><strong>piezas_por_paquete</strong></li>
                            <li><strong>numeros_serie</strong></li>
                            <li><strong>fecha_entrada</strong></li>
                            <li><strong>fecha_caducidad</strong></li>
                        </ul>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">Total</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $stats['total'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-700">Aceptables</p>
                        <p class="text-2xl font-bold text-emerald-800">{{ $stats['aceptables'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                        <p class="text-sm text-red-700">Inválidas</p>
                        <p class="text-2xl font-bold text-red-800">{{ $stats['invalidos'] ?? 0 }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200">
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
                                <tr>
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
                                    <td class="px-4 py-3">{{ $item['producto_nombre'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['numero_parte'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['proveedor'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['tipo_control'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['cantidad'] ?? 0 }}</td>
                                    <td class="px-4 py-3">{{ $item['piezas_por_paquete'] ?? 0 }}</td>
                                    <td class="px-4 py-3">${{ number_format((float) ($item['costo'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">${{ number_format((float) ($item['precio'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if (!empty($item['numeros_serie']))
                                            <span class="text-slate-700">{{ count($item['numeros_serie']) }} serie(s)</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">{{ $item['motivo'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('inventario.carga_rapida.confirmar') }}">
                        @csrf
                        <textarea name="payload" class="hidden">{{ json_encode($items, JSON_UNESCAPED_UNICODE) }}</textarea>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-white font-semibold shadow hover:bg-emerald-700 transition"
                        >
                            Confirmar importación
                        </button>
                    </form>

                    <a
                        href="{{ route('inventario.carga_rapida.index') }}"
                        class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 text-slate-700 font-semibold hover:bg-slate-50 transition"
                    >
                        Cargar otro archivo
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
