@extends('layouts.sidebar-navigation')

@section('title', 'Carga rápida de catálogo')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 bg-gradient-to-r from-blue-600 to-sky-600 text-white">
            <h1 class="text-2xl font-bold">Carga rápida de productos</h1>
            <p class="text-sm text-blue-100 mt-1">
                Esta pantalla solo crea o actualiza productos del catálogo. No registra inventario.
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
                    <form method="POST" action="{{ route('catalogo.carga_rapida.preview') }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Archivo</label>
                            <input type="file" name="archivo" accept=".xlsx,.csv,.txt" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200" required>
                            <p class="text-xs text-slate-500 mt-2">
                                Formatos permitidos: XLSX, CSV o TXT.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-3 text-white font-semibold shadow hover:bg-blue-700 transition">
                                Generar vista previa
                            </button>

                            <a href="{{ route('catalogo.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 text-slate-700 font-semibold hover:bg-slate-50 transition">
                                Volver al catálogo
                            </a>
                        </div>
                    </form>
                </div>

                <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5">
                    <h2 class="text-lg font-semibold text-slate-800 mb-3">Columnas sugeridas</h2>
                    <ul class="text-sm text-slate-700 space-y-2">
                        <li><strong>nombre</strong></li>
                        <li><strong>numero_parte</strong></li>
                        <li><strong>categoria</strong></li>
                        <li><strong>clave_prodserv</strong></li>
                        <li><strong>unidad</strong></li>
                        <li><strong>stock_seguridad</strong></li>
                        <li><strong>descripcion</strong></li>
                        <li><strong>require_serie</strong></li>
                        <li><strong>activo</strong></li>
                    </ul>
                </div>
            </div>
            @else
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $stats['total'] ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-700">Aceptables</p>
                    <p class="text-2xl font-bold text-emerald-800">{{ $stats['aceptables'] ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm text-amber-700">Duplicadas</p>
                    <p class="text-2xl font-bold text-amber-800">{{ $stats['duplicados'] ?? 0 }}</p>
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
                            <th class="px-4 py-3 text-left">Acción</th>
                            <th class="px-4 py-3 text-left">Nombre</th>
                            <th class="px-4 py-3 text-left">Número de parte</th>
                            <th class="px-4 py-3 text-left">Categoría</th>
                            <th class="px-4 py-3 text-left">Clave prod/serv</th>
                            <th class="px-4 py-3 text-left">Unidad</th>
                            <th class="px-4 py-3 text-left">Stock seguridad</th>
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
                                @elseif (($item['estado'] ?? '') === 'DUPLICADO_EN_ARCHIVO')
                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                    {{ $item['estado'] }}
                                </span>
                                @else
                                <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                                    {{ $item['estado'] }}
                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item['accion'] ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $item['nombre'] ?? '' }}</td>
                            <td class="px-4 py-3">{{ $item['numero_parte'] ?: 'Se generará' }}</td>
                            <td class="px-4 py-3">{{ $item['categoria'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item['clave_prodserv'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item['unidad'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item['stock_seguridad'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $item['motivo'] ?: '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('catalogo.carga_rapida.confirmar') }}">
                    @csrf
                    <textarea name="payload" class="hidden">{{ json_encode($items, JSON_UNESCAPED_UNICODE) }}</textarea>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-white font-semibold shadow hover:bg-emerald-700 transition">
                        Confirmar importación
                    </button>
                </form>

                <a href="{{ route('catalogo.carga_rapida.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-5 py-3 text-slate-700 font-semibold hover:bg-slate-50 transition">
                    Cargar otro archivo
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
