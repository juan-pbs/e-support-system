@extends('layouts.sidebar-navigation')

@section('title', 'Usuarios conectados')

@section('content')
<div class="max-w-7xl mx-auto px-0 sm:px-6 lg:px-8 py-4">
    <div class="flex flex-col items-start sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex flex-col items-start sm:flex-row sm:items-center gap-3 min-w-0">
            <x-boton-volver />
            <div class="min-w-0">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">Usuarios conectados</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Monitorea las sesiones vigentes del sistema y la actividad reciente.
                </p>
            </div>
        </div>

        <a href="{{ route('sistema.usuarios-conectados') }}"
           class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
            Actualizar
        </a>
    </div>

    @unless($sessionTableExists)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No se encontro la tabla de sesiones. Para ver usuarios conectados se requiere usar sesiones en base de datos.
        </div>
    @endunless

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Conectados ahora</p>
            <p class="mt-2 text-3xl font-bold text-blue-950">{{ $totalUsuariosConectados }}</p>
            <p class="mt-1 text-xs text-blue-700">Actividad en los ultimos {{ $onlineWindowMinutes }} min.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Usuarios con sesion</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $totalUsuarios }}</p>
            <p class="mt-1 text-xs text-slate-500">Sesiones dentro de {{ $lifetimeMinutes }} min.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sesiones vigentes</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $totalSesionesVigentes }}</p>
            <p class="mt-1 text-xs text-slate-500">Incluye multiples dispositivos.</p>
        </div>
    </div>

    <div class="lg:hidden space-y-3">
        @forelse($usuarios as $usuario)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-base font-semibold text-gray-900 break-words">{{ $usuario->name }}</p>
                        <p class="text-sm text-gray-500 break-all">{{ $usuario->email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $usuario->is_online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $usuario->is_online ? 'En linea' : 'Ausente' }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm">
                    <div>
                        <span class="text-gray-500">Rol</span>
                        <p class="font-medium text-gray-900">{{ ucfirst($usuario->puesto) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Ultima actividad</span>
                        <p class="font-medium text-gray-900">{{ $usuario->last_activity->format('d/m/Y H:i') }} · {{ $usuario->last_activity_human }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Sesiones</span>
                        <p class="font-medium text-gray-900">{{ $usuario->sessions_count }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">IP</span>
                        <p class="font-medium text-gray-900 break-all">{{ $usuario->ip_address }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-gray-500">
                No hay usuarios con sesion vigente.
            </div>
        @endforelse
    </div>

    <div class="hidden lg:block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Usuario</th>
                        <th class="px-4 py-3 text-left font-semibold">Rol</th>
                        <th class="px-4 py-3 text-left font-semibold">Estado</th>
                        <th class="px-4 py-3 text-left font-semibold">Ultima actividad</th>
                        <th class="px-4 py-3 text-left font-semibold">Sesiones</th>
                        <th class="px-4 py-3 text-left font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($usuarios as $usuario)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $usuario->name }}</div>
                                <div class="text-xs text-gray-500">{{ $usuario->email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ ucfirst($usuario->puesto) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $usuario->is_online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $usuario->is_online ? 'En linea' : 'Ausente' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $usuario->last_activity->format('d/m/Y H:i') }}</div>
                                <div class="text-xs text-gray-500">{{ $usuario->last_activity_human }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $usuario->sessions_count }}</td>
                            <td class="px-4 py-3">{{ $usuario->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay usuarios con sesion vigente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
