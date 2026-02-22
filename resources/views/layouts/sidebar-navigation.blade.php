@php
    use Illuminate\Support\Facades\Auth;
    $currentRoute = Route::currentRouteName();
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/images/ico.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .sidebar-gradient { background: linear-gradient(135deg, #007BD3 0%, #034373 100%); border-bottom-right-radius: 4rem; }
        .header-gradient { background: linear-gradient(135deg, #007BD3 0%, #034373 100%); }
        .sidebar-header { border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .menu-item-hover:hover { background: white; color: black; border-radius: 1rem; }
        .menu-item-hover { background: linear-gradient(135deg, #007BD3 0%, #034373 100%); color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .lucide-icon { width: 1.25rem; height: 1.25rem; stroke-width: 1.5; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .submenu.open { max-height: 500px; }
        .submenu-item { padding-left: 3rem; }
        .rotate-90 { transform: rotate(90deg); transition: transform 0.3s ease; }
        #sidebar { display: flex; flex-direction: column; }
        .sidebar-header { flex-shrink: 0; }
        .menu-container { flex: 1; overflow-y: auto; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-gradient fixed inset-y-0 left-0 z-50 w-72 sm:w-80 text-white shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
            <div class="p-4 sm:p-6 sidebar-header">
                <div class="flex items-center justify-between">
                    <div class="w-full">
                        <div class="w-full h-20 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                            <img src="/images/logo.png" alt="Logo de la empresa" class="h-full object-contain">
                        </div>
                    </div>
                    <button id="close-sidebar" class="text-white hover:bg-white hover:bg-opacity-20 p-1 rounded-full ml-2">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="menu-container overflow-y-auto scrollbar-hide p-2">
                <nav class="space-y-2">
                    <!-- Inicio -->
                    <a href="{{ route('gerente.inicio') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover rounded-lg">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="home" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium">Inicio</span>
                        </div>
                    </a>

                    <!-- Inventario (redirige a Ver Productos) -->
                    <a href="{{ route('catalogo.index') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover rounded-lg">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="notebook-pen" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium">Inventario</span>
                        </div>
                    </a>

                    <!-- Bitácora -->
                    <div class="menu-item-with-submenu">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors cursor-pointer menu-item-hover text-white rounded-lg"
                             data-submenu="Bitacora">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="package-search" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium flex-grow">Bitácora</span>
                            <i data-lucide="chevron-right" class="lucide-icon submenu-chevron" data-for="Bitacora"></i>
                        </div>
                        <div class="submenu" id="submenu-Bitacora">
                            <div class="space-y-1 mt-1">
                                <!-- Entrada de inventario (conservado) -->
                                <a href="{{ route('inventario') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="list" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Entrada de inventario</span>
                                    </div>
                                </a>
                                <!-- Añadir producto -->
                                <a href="{{ route('producto.crear') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="plus-circle" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Añadir producto</span>
                                    </div>
                                </a>
                                <!-- Salida de inventario (conservado) -->
                                <a href="{{ route('inventario.salidas') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="package-minus" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Salida de inventario</span>
                                    </div>
                                </a>
                                <!-- Carga rápida de productos (conservado) -->
                                <a href="{{ route('cargaRapidaProd.index') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="zap" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Carga rápida de productos</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Cotizaciones -->
                    <div class="menu-item-with-submenu">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors cursor-pointer menu-item-hover text-white rounded-lg"
                             data-submenu="Cotizaciones">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="file-text" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium flex-grow">Cotizaciones</span>
                            <i data-lucide="chevron-right" class="lucide-icon submenu-chevron" data-for="Cotizaciones"></i>
                        </div>
                        <div class="submenu" id="submenu-Cotizaciones">
                            <div class="space-y-1 mt-1">
                                <a href="{{ route('cotizaciones.vista') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="eye" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Ver cotizaciones</span>
                                    </div>
                                </a>
                                <a href="{{ route('cotizaciones.crear') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="plus-circle" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Crear cotización</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Otros módulos -->
                    <a href="{{ route('ordenes.index') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover rounded-lg">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="notebook-pen" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium">Orden de servicio</span>
                        </div>
                    </a>

                    <a href="{{ route('seguimiento') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover rounded-lg">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="eye" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium">Seguimiento</span>
                        </div>
                    </a>

                    <a href="{{ route('reportes') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover rounded-lg">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="chart-column-big" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium">Reportes</span>
                        </div>
                    </a>

                    <!-- Submenú Contactos -->
                    <div class="menu-item-with-submenu">
                        <div class="flex items-center gap-3 px-4 py-3 transition-colors cursor-pointer menu-item-hover text-white rounded-lg"
                             data-submenu="Contactos">
                            <div class="flex items-center justify-center w-8 h-8">
                                <i data-lucide="users" class="lucide-icon"></i>
                            </div>
                            <span class="text-sm font-medium flex-grow">Contactos</span>
                            <i data-lucide="chevron-right" class="lucide-icon submenu-chevron" data-for="Contactos"></i>
                        </div>
                        <div class="submenu" id="submenu-Contactos">
                            <div class="space-y-1 mt-1">
                                <a href="{{ route('clientes') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="user-round" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Clientes</span>
                                    </div>
                                </a>
                                <a href="{{ route('empleados.index') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="user-plus" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Empleados</span>
                                    </div>
                                </a>
                                <a href="{{ route('proveedores.index') }}" class="block submenu-item">
                                    <div class="flex items-center gap-3 px-4 py-3 transition-colors menu-item-hover text-white">
                                        <i data-lucide="truck" class="lucide-icon"></i>
                                        <span class="text-sm font-medium">Proveedores</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="flex-1 flex flex-col min-h-0">
            <header class="header-gradient text-white p-3 sm:p-4 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                        <button id="open-sidebar" class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-full">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h1 class="text-sm sm:text-lg font-semibold truncate">Bienvenido {{ Auth::user()->name }}</h1>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4">
                        <span class="text-xs sm:text-sm hidden md:block">{{ Auth::user()->puesto }}</span>
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-800 hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
                                    <div>{{ Auth::user()->puesto }}</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Perfil') }}
                                </x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Cerrar sesión ') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </header>

            <main class="w-full h-full">
                <div class="w-full h-full bg-white rounded-lg shadow-sm border border-gray-200 p-6 overflow-auto">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const openBtn = document.getElementById('open-sidebar');
            const closeBtn = document.getElementById('close-sidebar');

            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
                setTimeout(() => lucide.createIcons(), 300);
            }

            openBtn.addEventListener('click', toggleSidebar);
            closeBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);

            document.querySelectorAll('.menu-item-with-submenu > .menu-item-hover').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'A') return;
                    const submenuId = this.getAttribute('data-submenu');
                    const submenu = document.getElementById(`submenu-${submenuId}`);
                    const chevron = this.querySelector('.submenu-chevron');
                    submenu.classList.toggle('open');
                    chevron.classList.toggle('rotate-90');
                    lucide.createIcons();
                });
            });
        });
    </script>

    @yield('scripts')
    @stack('scripts')
</body>
</html>
