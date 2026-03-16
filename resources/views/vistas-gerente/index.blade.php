@extends('layouts.sidebar-navigation')

@section('content')
<div class="relative">
    {{-- Marca de agua con el logo --}}
    <div class="absolute inset-0 pointer-events-none opacity-5 grayscale bg-no-repeat bg-center"
         style="background-image:url('/images/logo.png'); background-size:55%;"></div>

    <div class="relative">
        {{-- Título + botón Catálogo dentro del body --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-slate-800">Inicio</h2>
                <a href="{{ route('catalogo.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white shadow-md
                          bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600
                          hover:from-blue-700 hover:via-indigo-700 hover:to-purple-700 transition">
                    <i data-lucide="notebook-pen" class="w-4 h-4"></i>
                    Catálogo
                </a>
            </div>
            <p class="text-sm text-slate-500 hidden sm:block">Accesos rápidos y estado general</p>
        </div>

        {{-- Métricas (tarjetas completas clicables y coloridas) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {{-- Productos (total) --}}
            <a href="{{ route('catalogo.index') }}"
               class="block rounded-2xl p-5 text-white shadow-lg
                      bg-gradient-to-br from-sky-500 to-blue-600
                      hover:shadow-xl hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase opacity-90">Productos</p>
                        <h3 class="text-3xl font-extrabold">{{ number_format($totalProductos ?? 0) }}</h3>
                    </div>
                    <i data-lucide="package" class="w-8 h-8 opacity-90"></i>
                </div>
            </a>

            {{-- Clientes (total) --}}
            <a href="{{ route('clientes') }}"
               class="block rounded-2xl p-5 text-white shadow-lg
                      bg-gradient-to-br from-emerald-500 to-green-600
                      hover:shadow-xl hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase opacity-90">Clientes</p>
                        <h3 class="text-3xl font-extrabold">{{ number_format($totalClientes ?? 0) }}</h3>
                    </div>
                    <i data-lucide="users" class="w-8 h-8 opacity-90"></i>
                </div>
            </a>

            {{-- Cotizaciones NO procesadas --}}
            <a href="{{ route('cotizaciones.vista') }}"
               class="block rounded-2xl p-5 text-white shadow-lg
                      bg-gradient-to-br from-amber-500 to-orange-600
                      hover:shadow-xl hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase opacity-90">Cotizaciones pendientes</p>
                        <h3 class="text-3xl font-extrabold">{{ number_format($totalCotizaciones ?? 0) }}</h3>
                        <p class="text-[11px] opacity-90 mt-1">
                            Sin orden de servicio creada
                        </p>
                    </div>
                    <i data-lucide="file-text" class="w-8 h-8 opacity-90"></i>
                </div>
            </a>

            {{-- Órdenes SIN acta firmada --}}
            <a href="{{ route('ordenes.index') }}"
               class="block rounded-2xl p-5 text-white shadow-lg
                      bg-gradient-to-br from-fuchsia-500 to-pink-600
                      hover:shadow-xl hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase opacity-90">Órdenes sin acta firmada</p>
                        <h3 class="text-3xl font-extrabold">{{ number_format($totalOrdenes ?? 0) }}</h3>
                        <p class="text-[11px] opacity-90 mt-1">
                            Sin acta de conformidad firmada
                        </p>
                    </div>
                    <i data-lucide="wrench" class="w-8 h-8 opacity-90"></i>
                </div>
            </a>
        </div>

        {{-- Accesos rápidos (cada tarjeta es el botón) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('cotizaciones.crear') }}"
               class="block rounded-2xl p-5 shadow-sm border border-indigo-100
                      bg-gradient-to-br from-indigo-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Nueva Cotización</h3>
                    <div class="rounded-xl p-2 bg-indigo-100">
                        <i data-lucide="plus-circle" class="w-6 h-6 text-indigo-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Crea una cotización y comparte el PDF.</p>
            </a>

            <a href="{{ route('ordenes.create') }}"
               class="block rounded-2xl p-5 shadow-sm border border-rose-100
                      bg-gradient-to-br from-rose-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Nueva Orden de Servicio</h3>
                    <div class="rounded-xl p-2 bg-rose-100">
                        <i data-lucide="wrench" class="w-6 h-6 text-rose-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Abre una orden y asigna técnico.</p>
            </a>


            <a href="{{ route('inventario') }}"
               class="block rounded-2xl p-5 shadow-sm border border-teal-100
                      bg-gradient-to-br from-teal-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Entradas de Inventario</h3>
                    <div class="rounded-xl p-2 bg-teal-100">
                        <i data-lucide="package-plus" class="w-6 h-6 text-teal-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Registra entradas manuales de inventario.</p>
            </a>

            <a href="{{ route('catalogo.carga_rapida.index') }}"
               class="block rounded-2xl p-5 shadow-sm border border-indigo-100
                      bg-gradient-to-br from-indigo-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Carga Rápida de Productos</h3>
                    <div class="rounded-xl p-2 bg-indigo-100">
                        <i data-lucide="notebook-tabs" class="w-6 h-6 text-indigo-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Importa productos al catálogo por archivo.</p>
            </a>

            <a href="{{ route('inventario.carga_rapida.index') }}"
               class="block rounded-2xl p-5 shadow-sm border border-emerald-100
                      bg-gradient-to-br from-emerald-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Carga Rápida de Inventario</h3>
                    <div class="rounded-xl p-2 bg-emerald-100">
                        <i data-lucide="boxes" class="w-6 h-6 text-emerald-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Importa entradas por piezas, paquetes o series.</p>
            </a>

            <a href="{{ route('inventario.salidas') }}"
               class="block rounded-2xl p-5 shadow-sm border border-amber-100
                      bg-gradient-to-br from-amber-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Salida de Inventario</h3>
                    <div class="rounded-xl p-2 bg-amber-100">
                        <i data-lucide="package-minus" class="w-6 h-6 text-amber-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Descarga series y actualiza stock.</p>
            </a>

            <a href="{{ route('clientes') }}"
               class="block rounded-2xl p-5 shadow-sm border border-emerald-100
                      bg-gradient-to-br from-emerald-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Clientes</h3>
                    <div class="rounded-xl p-2 bg-emerald-100">
                        <i data-lucide="user-round" class="w-6 h-6 text-emerald-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Administra tu cartera de clientes.</p>
            </a>

            <a href="{{ route('proveedores.index') }}"
               class="block rounded-2xl p-5 shadow-sm border border-sky-100
                      bg-gradient-to-br from-sky-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Proveedores</h3>
                    <div class="rounded-xl p-2 bg-sky-100">
                        <i data-lucide="truck" class="w-6 h-6 text-sky-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Consulta y agrega proveedores.</p>
            </a>

            <a href="{{ route('empleados.index') }}"
               class="block rounded-2xl p-5 shadow-sm border border-purple-100
                      bg-gradient-to-br from-purple-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Empleados</h3>
                    <div class="rounded-xl p-2 bg-purple-100">
                        <i data-lucide="user-plus" class="w-6 h-6 text-purple-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Gestiona usuarios y permisos.</p>
            </a>

            <a href="{{ route('catalogo.index') }}"
               class="block rounded-2xl p-5 shadow-sm border border-blue-100
                      bg-gradient-to-br from-blue-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Catálogo de Productos</h3>
                    <div class="rounded-xl p-2 bg-blue-100">
                        <i data-lucide="notebook-pen" class="w-6 h-6 text-blue-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Explora y administra el catálogo.</p>
            </a>

            {{-- Acceso rápido a Seguimiento de servicios --}}
            <a href="{{ route('reportes.seguimiento') }}"
               class="block rounded-2xl p-5 shadow-sm border border-slate-100
                      bg-gradient-to-br from-slate-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Seguimiento de servicios</h3>
                    <div class="rounded-xl p-2 bg-slate-100">
                        <i data-lucide="activity" class="w-6 h-6 text-slate-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Revisa estados, prioridades y materiales extra.</p>
            </a>

            {{-- Acceso rápido a Reportes --}}
            <a href="{{ route('reportes') }}"
               class="block rounded-2xl p-5 shadow-sm border border-cyan-100
                      bg-gradient-to-br from-cyan-50 to-white
                      hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Reportes</h3>
                    <div class="rounded-xl p-2 bg-cyan-100">
                        <i data-lucide="bar-chart-3" class="w-6 h-6 text-cyan-700"></i>
                    </div>
                </div>
                <p class="mt-2 text-slate-600">Descarga reportes y analíticas de servicios.</p>
            </a>
        </div>
    </div>
</div>
@endsection
