@extends('layouts.sidebar-navigation')

@section('title', 'Mantenimiento por secciones')

@section('content')
<div class="max-w-7xl mx-auto px-0 sm:px-6 lg:px-8 py-4">
    <div class="flex flex-col items-start sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex flex-col items-start sm:flex-row sm:items-center gap-3 min-w-0">
            <x-boton-volver />
            <div class="min-w-0">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">Mantenimiento por secciones</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Deshabilita modulos especificos sin apagar todo el sistema.
                </p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mb-4 rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">Herramientas de rescate</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Reabre una orden finalizada para corregir datos y guarda historial. Si hace falta, puedes volver a cerrarla conservando el acta, PDF y firmas que ya tenia.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('sistema.mantenimiento.ordenes.reabrir') }}" class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-amber-950">Reabrir para editar</h3>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">ID de orden</span>
                            <input type="number" name="orden_id" min="1" value="{{ old('orden_id') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej. 125">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Motivo</span>
                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="500" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej. corregir serie">
                        </label>
                    </div>
                    <button type="submit" class="mt-3 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700" onclick="return confirm('Reabrir esta orden para permitir edicion?')">
                        Reabrir orden
                    </button>
                </form>

                <form method="POST" action="{{ route('sistema.mantenimiento.ordenes.cerrar') }}" class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-emerald-950">Cerrar de nuevo</h3>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">ID de orden</span>
                            <input type="number" name="orden_id" min="1" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej. 125">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Motivo</span>
                            <input type="text" name="reason" maxlength="500" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej. correccion terminada">
                        </label>
                    </div>
                    <button type="submit" class="mt-3 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" onclick="return confirm('Cerrar de nuevo esta orden conservando su acta y firmas?')">
                        Cerrar orden
                    </button>
                </form>
            </div>

            @if(($orderMaintenanceHistories ?? collect())->isNotEmpty())
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-800">
                        Historial reciente de rescates
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-white text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Orden</th>
                                    <th class="px-4 py-2">Accion</th>
                                    <th class="px-4 py-2">Usuario</th>
                                    <th class="px-4 py-2">Cambio</th>
                                    <th class="px-4 py-2">Motivo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($orderMaintenanceHistories as $history)
                                    @php
                                        $before = (array) ($history->before_snapshot ?? []);
                                        $after = (array) ($history->after_snapshot ?? []);
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-gray-600">{{ optional($history->created_at)->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-2 font-semibold text-gray-900">OS-{{ $history->orden_id }}</td>
                                        <td class="px-4 py-2">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $history->action === 'reopen_order' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                {{ $history->action === 'reopen_order' ? 'Reabrio' : 'Cerro' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $history->user?->name ?? 'Sistema' }}</td>
                                        <td class="px-4 py-2 text-xs text-gray-600">
                                            {{ $before['estado'] ?? '---' }} / {{ $before['acta_estado'] ?? '---' }}
                                            <span class="text-gray-400">&rarr;</span>
                                            {{ $after['estado'] ?? '---' }} / {{ $after['acta_estado'] ?? '---' }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $history->reason ?: 'Sin motivo capturado' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ route('sistema.mantenimiento.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
            Los usuarios con rol <strong>Sistema</strong> pueden seguir entrando a las secciones bloqueadas para revisar o reactivar el servicio.
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900">Envio de correos</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Activa o desactiva el envio de correos por tipo de documento.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    @php
                        $emailToggles = [
                            ['name' => 'email_cotizaciones_enabled', 'label' => 'Cotizaciones', 'enabled' => $emailCotizacionesEnabled ?? true],
                            ['name' => 'email_ordenes_enabled', 'label' => 'Ordenes de servicio', 'enabled' => $emailOrdenesEnabled ?? true],
                            ['name' => 'email_actas_enabled', 'label' => 'Actas de conformidad', 'enabled' => $emailActasEnabled ?? true],
                        ];
                    @endphp

                    @foreach($emailToggles as $toggle)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $toggle['label'] }}</h3>
                                    <p class="mt-1 text-xs font-medium {{ $toggle['enabled'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $toggle['enabled'] ? 'Habilitado' : 'Deshabilitado' }}
                                    </p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="hidden" name="{{ $toggle['name'] }}" value="0">
                                    <input type="checkbox"
                                           name="{{ $toggle['name'] }}"
                                           value="1"
                                           class="peer sr-only"
                                           @checked($toggle['enabled'])>
                                    <div class="h-7 w-12 rounded-full bg-gray-200 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-5"></div>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="space-y-3">
            @foreach($groupedSections as $groupKey => $items)
                @php
                    $moduleSection = $items->firstWhere('key', $groupKey);
                    $childSections = $items->reject(fn($section) => $section->key === $groupKey)->values();
                    $activeCount = $items->where('enabled', true)->count();
                @endphp

                <details class="group rounded-xl border border-gray-200 bg-white shadow-sm" @if($activeCount > 0) open @endif>
                    <summary class="flex cursor-pointer list-none flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-1 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-gray-300 text-gray-500 group-open:rotate-90">
                                <span class="text-sm">›</span>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900">{{ $groupLabels[$groupKey] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $groupKey)) }}</h2>
                                <p class="text-sm text-gray-500">Desactiva todo el modulo o abre para controlar sus vistas y mecanicas.</p>
                            </div>
                        </div>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $activeCount ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $activeCount }} bloqueos activos
                        </span>
                    </summary>

                    <div class="border-t border-gray-100 p-4 pt-3">
                        @if($moduleSection)
                            <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900">Desactivar toda la seccion</h3>
                                        <p class="mt-1 text-xs text-gray-500 break-words">Reglas: {{ implode(', ', (array) $moduleSection->paths) }}</p>
                                    </div>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input type="hidden" name="sections[{{ $moduleSection->key }}][enabled]" value="0">
                                        <input type="checkbox" name="sections[{{ $moduleSection->key }}][enabled]" value="1" class="peer sr-only" @checked($moduleSection->enabled)>
                                        <div class="h-7 w-12 rounded-full bg-gray-200 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-5"></div>
                                    </label>
                                </div>
                                <label class="mt-3 block">
                                    <span class="text-sm font-medium text-gray-700">Mensaje para el usuario</span>
                                    <textarea name="sections[{{ $moduleSection->key }}][message]" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old("sections.{$moduleSection->key}.message", $moduleSection->message) }}</textarea>
                                </label>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @foreach($childSections as $section)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="text-base font-semibold text-gray-900">{{ $section->name }}</h3>
                                            <p class="mt-1 text-xs text-gray-500 break-words">Reglas: {{ implode(', ', (array) $section->paths) }}</p>
                                        </div>
                                        <label class="relative inline-flex cursor-pointer items-center">
                                            <input type="hidden" name="sections[{{ $section->key }}][enabled]" value="0">
                                            <input type="checkbox" name="sections[{{ $section->key }}][enabled]" value="1" class="peer sr-only" @checked($section->enabled)>
                                            <div class="h-7 w-12 rounded-full bg-gray-200 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-5"></div>
                                        </label>
                                    </div>

                                    <label class="mt-4 block">
                                        <span class="text-sm font-medium text-gray-700">Mensaje para el usuario</span>
                                        <textarea name="sections[{{ $section->key }}][message]" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old("sections.{$section->key}.message", $section->message) }}</textarea>
                                    </label>

                                    <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                                        <span class="inline-flex rounded-full px-2.5 py-1 font-semibold {{ $section->enabled ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $section->enabled ? 'En mantenimiento' : 'Disponible' }}
                                        </span>
                                        <span class="text-gray-400">Actualizado: {{ optional($section->updated_at)->format('d/m/Y H:i') ?: 'Sin cambios' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="sticky bottom-0 z-10 -mx-0 sm:-mx-6 lg:-mx-8 border-t border-gray-200 bg-white/95 px-0 sm:px-6 lg:px-8 py-3 backdrop-blur">
            <button type="submit"
                    class="w-full sm:w-auto rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Guardar configuracion
            </button>
        </div>
    </form>
</div>
@endsection
