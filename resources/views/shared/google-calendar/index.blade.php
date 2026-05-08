@extends($layout)

@section('title', 'Google Calendar')

@section('content')
<div class="mx-auto max-w-6xl px-0 py-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 sm:text-3xl">Google Calendar</h1>
        <p class="text-sm text-gray-500">Sincroniza automáticamente las órdenes asignadas para que aparezcan en el calendario del técnico sin pasos manuales extra.</p>
    </div>

    @foreach (['success', 'error'] as $key)
        @if (session($key))
            <div class="mb-4 rounded-xl border px-4 py-3 {{ $key === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Estado de la conexión</h2>
                        @if (!$googleCalendarConfigured)
                            <p class="mt-2 text-sm text-red-600">Faltan credenciales de Google Calendar en el entorno.</p>
                            <div class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                Configura `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET` y `GOOGLE_CALENDAR_REDIRECT_URI`.
                            </div>
                        @elseif ($account?->is_connected)
                            <p class="mt-2 text-sm text-gray-600">Tu cuenta ya está enlazada y lista para sincronizar órdenes asignadas.</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Correo Google</div>
                                    <div class="mt-1 text-sm font-medium text-emerald-900">{{ $account->google_email ?: 'Sin correo detectado' }}</div>
                                </div>
                                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Sincronización</div>
                                    <div class="mt-1 text-sm font-medium text-blue-900">{{ $account->sync_enabled ? 'Automática activa' : 'Automática pausada' }}</div>
                                </div>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-600">Conecta tu cuenta para que las órdenes asignadas se envíen a Google Calendar automáticamente.</p>
                        @endif
                    </div>

                    <div class="flex w-full flex-col gap-3 lg:w-72">
                        @if ($googleCalendarConfigured && !($account?->is_connected))
                            <a href="{{ route('google-calendar.redirect') }}"
                                class="rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-medium text-white hover:bg-blue-700">
                                Conectar Google Calendar
                            </a>
                        @endif

                        @if ($account?->is_connected)
                            <form method="POST" action="{{ route('google-calendar.sync-now') }}">
                                @csrf
                                <button class="w-full rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 hover:bg-blue-100">
                                    Sincronizar ahora
                                </button>
                            </form>

                            <form method="POST" action="{{ route('google-calendar.toggle') }}">
                                @csrf
                                <input type="hidden" name="sync_enabled" value="{{ $account->sync_enabled ? 0 : 1 }}">
                                <button class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    {{ $account->sync_enabled ? 'Pausar sincronización automática' : 'Activar sincronización automática' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('google-calendar.disconnect') }}">
                                @csrf
                                <button class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 hover:bg-red-100">
                                    Desconectar cuenta
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Cómo funciona</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">1. Conexión</div>
                        <p class="mt-2 text-sm text-gray-600">Cada técnico conecta su propia cuenta de Google para que las órdenes se creen en su calendario principal.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">2. Asignación automática</div>
                        <p class="mt-2 text-sm text-gray-600">Cuando una orden se asigna o cambia de técnico, el sistema crea o actualiza el evento sin que tengas que enviarlo manualmente.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">3. Colores tipo semáforo</div>
                        <p class="mt-2 text-sm text-gray-600">El evento toma color según la prioridad o el estado de la orden para que sea más fácil ubicar pendientes, urgentes o cerradas.</p>
                    </div>
                </div>
            </div>

            @if ($showTechStatuses)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Estado de técnicos</h2>
                            <p class="text-sm text-gray-500">Quién ya conectó Google Calendar y cuántas órdenes tiene asignadas.</p>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-gray-500">
                                    <th class="px-3 py-2 font-medium">Técnico</th>
                                    <th class="px-3 py-2 font-medium">Correo Google</th>
                                    <th class="px-3 py-2 font-medium">Conexión</th>
                                    <th class="px-3 py-2 font-medium">Sync auto</th>
                                    <th class="px-3 py-2 font-medium">Órdenes asignadas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($techStatuses as $row)
                                    <tr class="border-b border-gray-100">
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-gray-900">{{ $row['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['email'] }}</div>
                                        </td>
                                        <td class="px-3 py-3 text-gray-700">{{ $row['google_email'] ?: 'Sin conectar' }}</td>
                                        <td class="px-3 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $row['connected'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ $row['connected'] ? 'Conectado' : 'Pendiente' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $row['sync_enabled'] ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $row['sync_enabled'] ? 'Activo' : 'Pausado' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-700">{{ $row['assigned_orders'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">No hay técnicos registrados todavía.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Resumen rápido</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Estado actual</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">
                            @if (!$googleCalendarConfigured)
                                Falta configuración
                            @elseif ($account?->is_connected)
                                Cuenta conectada
                            @else
                                Aún sin conectar
                            @endif
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Calendario destino</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">{{ $account?->calendar_id ?: 'primary' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Última conexión</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">{{ $account?->connected_at?->format('d/m/Y H:i') ?: 'Sin registro' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
