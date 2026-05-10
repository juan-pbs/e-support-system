<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel Tecnico') - Modulo Tecnico</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="icon" href="{{ asset('images/ico.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('images/ico.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .sidebar-gradient {
            background: linear-gradient(135deg, #007BD3 0%, #034373 100%);
            border-bottom-right-radius: 4rem;
        }
        .header-gradient {
            background: linear-gradient(135deg, #007BD3 0%, #034373 100%);
        }

        .menu-item-hover {
            background: linear-gradient(135deg, #007BD3 0%, #034373 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .menu-item-hover:hover {
            background: white;
            color: black;
            border-radius: 1rem;
        }

        .lucide-icon { width: 1.25rem; height: 1.25rem; stroke-width: 1.5; }
        html, body { max-width: 100%; overflow-x: hidden; }
        .app-content-shell, .app-content-shell * { min-width: 0; }
        .app-content-shell img,
        .app-content-shell video,
        .app-content-shell iframe { max-width: 100%; }
        .app-content-shell input,
        .app-content-shell select,
        .app-content-shell textarea,
        .app-content-shell button { max-width: 100%; }
        .app-content-shell table { max-width: 100%; }
        .app-content-shell .overflow-x-auto { -webkit-overflow-scrolling: touch; }
        @media (max-width: 640px) {
            body { background: #fff; }
            .app-page-main { overflow: hidden; background: #fff; }
            .app-content-shell {
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 0.75rem !important;
                background: #fff !important;
            }
            .app-content-shell input,
            .app-content-shell select,
            .app-content-shell textarea {
                font-size: 16px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
@php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();
    $rolActual = method_exists($user, 'normalizedRole')
        ? $user->normalizedRole()
        : strtolower(trim((string) ($user->puesto ?? '')));
    $esSistema = $rolActual === 'sistema';
@endphp

<div class="flex h-screen">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

    <div id="sidebar"
         class="sidebar-gradient fixed inset-y-0 left-0 z-50 w-72 sm:w-80 text-white shadow-xl
                transform -translate-x-full transition-transform duration-300 ease-in-out flex flex-col">

        <div class="p-4 sm:p-6 border-b border-white/20">
            <div class="flex items-center justify-between">
                <div class="w-full">
                    <div class="w-full h-20 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                        <img src="/images/logo.png" alt="Logo" class="h-full object-contain">
                    </div>
                </div>
                <button id="close-sidebar"
                        class="text-white hover:bg-white hover:bg-opacity-20 p-1 rounded-full ml-2">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto scrollbar-hide p-2 flex-1">
            <nav class="space-y-2">
                <a href="{{ route('tecnico.inicio') }}" class="block">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                        {{ request()->routeIs('tecnico.inicio') ? 'bg-white text-gray-900' : 'menu-item-hover' }}">
                        <i data-lucide="home" class="lucide-icon"></i>
                        <span class="text-sm font-medium">Inicio</span>
                    </div>
                </a>

                @if($esSistema)
                    <a href="{{ route('gerente.inicio') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors menu-item-hover">
                            <i data-lucide="shield-check" class="lucide-icon"></i>
                            <span class="text-sm font-medium">Panel sistema</span>
                        </div>
                    </a>

                    <a href="{{ route('admin.inicio') }}" class="block">
                        <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors menu-item-hover">
                            <i data-lucide="layout-dashboard" class="lucide-icon"></i>
                            <span class="text-sm font-medium">Panel admin</span>
                        </div>
                    </a>
                @endif

                <a href="{{ route('tecnico.servicios') }}" class="block">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                        {{ request()->routeIs('tecnico.servicios') ? 'bg-white text-gray-900' : 'menu-item-hover' }}">
                        <i data-lucide="wrench" class="lucide-icon"></i>
                        <span class="text-sm font-medium">Servicios</span>
                    </div>
                </a>

                <a href="{{ route('tecnico.logistica.index') }}" class="block">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                        {{ request()->routeIs('tecnico.logistica.*') ? 'bg-white text-gray-900' : 'menu-item-hover' }}">
                        <i data-lucide="map-pinned" class="lucide-icon"></i>
                        <span class="text-sm font-medium">Logística</span>
                    </div>
                </a>

                <a href="{{ route('tecnico.google-calendar.index') }}" class="block">
                    <div class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                        {{ request()->routeIs('tecnico.google-calendar.*') ? 'bg-white text-gray-900' : 'menu-item-hover' }}">
                        <i data-lucide="calendar-days" class="lucide-icon"></i>
                        <span class="text-sm font-medium">Google Calendar</span>
                    </div>
                </a>
            </nav>
        </div>
    </div>

    <div class="flex-1 flex flex-col min-h-0">
        <header class="header-gradient text-white px-4 py-3 sm:px-5 sm:py-4 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                    <button id="open-sidebar"
                            class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-full">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div class="flex flex-col min-w-0">
                        <h1 class="text-sm sm:text-lg font-semibold truncate">
                            @yield('title', 'Panel Tecnico')
                        </h1>
                        <p class="text-xs sm:text-sm opacity-80 truncate">
                            Bienvenido {{ Auth::user()->name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                    <span class="text-xs sm:text-sm hidden md:block">
                        {{ Auth::user()->puesto }}
                    </span>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                                class="inline-flex items-center max-w-[92px] sm:max-w-none px-2 sm:px-3 py-2 border border-transparent
                                       text-xs sm:text-sm leading-4 font-medium rounded-md text-white
                                       bg-blue-800 hover:text-gray-100">
                            <span class="truncate">{{ Auth::user()->puesto }}</span>
                            <svg class="ml-1 sm:ml-2 h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open"
                             @click.away="open = false"
                             x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl z-20">
                            <a href="{{ route('profile.edit') }}"
                               class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                Perfil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    class="w-full text-left px-4 py-2 text-gray-800 hover:bg-gray-100"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Cerrar sesion
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="app-page-main w-full h-full min-w-0">
            <div class="app-content-shell w-full h-full bg-white rounded-lg shadow-sm border border-gray-200 p-6 overflow-auto">
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
    });
</script>

@yield('scripts')
@stack('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
