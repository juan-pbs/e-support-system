{{-- resources/views/vistas-gerente/clientes-gerente/clientes_gerente.blade.php --}}
@extends('layouts.sidebar-navigation')

@section('title', 'Administración de clientes')

@section('content')

<style>
    [x-cloak]{display:none !important}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="clientesGerente()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <x-boton-volver />
        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-800 flex-1 text-center md:text-left">
            Administración de clientes
        </h1>
        <div class="w-8 md:hidden"></div>
    </div>

    {{-- Alerts --}}
    @if (session('success'))
        <div id="success-message" class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded-lg border border-green-300 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div id="error-message" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded-lg border border-red-300 shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div id="validation-message" class="mb-4 bg-red-100 text-red-800 px-4 py-3 rounded-lg border border-red-300 shadow-sm">
            <ul class="list-disc ml-5">
                @foreach ($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Buscador + botón --}}
    <div class="bg-white shadow-xl border border-gray-200 rounded-xl p-4 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="flex-1">
                <x-barra-busqueda-live
                    :action="route('clientes')"
                    autocompleteUrl="{{ route('clientes.autocomplete') }}"
                    placeholder="Buscar cliente por código, nombre o empresa..."
                    inputId="buscar-cliente"
                    resultId="resultados-cliente"
                    name="buscar"
                />
            </div>

            <div class="lg:w-auto">
                <a href="{{ route('clientes.nuevo') }}"
                   class="w-full lg:w-auto justify-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2">
                    <i class="fas fa-user-plus"></i>
                    Nuevo cliente
                </a>
            </div>
        </div>
    </div>

    {{-- ====== MÓVIL/TABLET: TARJETAS (hasta <lg) ====== --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($clientes as $cliente)
            @php
                $c = $cliente->creditoCliente;
                $max = $c->monto_maximo ?? 0;
                $usado = $c->monto_usado ?? 0;
                $dias = $c->dias_credito ?? 0;
                $fechaLim = $c->fecha_asignacion ?? now()->format('Y-m-d');

                $badgeClass = 'bg-gray-100 text-gray-800';
                $badgeText  = 'Sin crédito';

                if ($c) {
                    if ($dias > 7) { $badgeClass = 'bg-green-100 text-green-800'; $badgeText = 'Activo'; }
                    elseif ($dias >= 1) { $badgeClass = 'bg-yellow-100 text-yellow-800'; $badgeText = 'Por vencer'; }
                    else { $badgeClass = 'bg-red-100 text-red-800'; $badgeText = 'Vencido'; }
                }
            @endphp

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">
                            Código:
                            <span class="inline-flex items-center px-2 py-1 rounded-md bg-gray-100 text-gray-800 font-semibold">
                                {{ $cliente->codigo_cliente ?? '—' }}
                            </span>
                        </p>

                        <p class="text-base font-semibold text-gray-900 truncate">
                            {{ $cliente->nombre }}
                        </p>

                        <p class="text-sm text-gray-600">
                            {{ $cliente->telefono ?? '—' }}
                        </p>

                        <p class="text-sm text-gray-600 break-all">
                            {{ $cliente->correo_electronico ?? '—' }}
                        </p>

                        <p class="text-sm text-gray-600 truncate">
                            {{ $cliente->nombre_empresa ?? '—' }}
                        </p>
                    </div>

                    <span class="shrink-0 px-2 py-1 text-xs font-medium rounded {{ $badgeClass }}">
                        {{ $badgeText }}
                    </span>
                </div>

                <div class="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-3 text-sm space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Límite</span>
                        <span class="font-medium text-gray-800">${{ number_format($max, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Usado</span>
                        <span class="font-medium text-gray-800">${{ number_format($usado, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Días restantes</span>
                        <span class="font-medium text-gray-800">{{ $dias }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Fecha límite</span>
                        <span class="font-medium text-gray-800">{{ $fechaLim }}</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ route('clientes.edit', $cliente->clave_cliente) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2">
                        <i class="fas fa-edit"></i>
                        <span>Editar</span>
                    </a>

                    <button type="button"
                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            @click="openEditModal(
                                {{ $cliente->clave_cliente }},
                                {{ $max }},
                                {{ $usado }},
                                '{{ $fechaLim }}'
                            )">
                        <i class="fas fa-credit-card"></i>
                        <span>Crédito</span>
                    </button>

                    <button type="button"
                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            @click="openHistorial({{ $cliente->clave_cliente }})">
                        <i class="fas fa-money-bill"></i>
                        <span>Pagos</span>
                    </button>

                    <button type="button"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg inline-flex items-center justify-center gap-2"
                            @click="openDelete({{ $cliente->clave_cliente }})">
                        <i class="fas fa-trash"></i>
                        <span>Eliminar</span>
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500">
                No hay clientes registrados.
            </div>
        @endforelse
    </div>

    {{-- ====== ESCRITORIO: TABLA (lg y arriba) ====== --}}
    <div class="hidden lg:block bg-white shadow-xl border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Código</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Cliente</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Empresa</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Correo</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Crédito</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Acciones</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($clientes as $cliente)
                        @php
                            $c = $cliente->creditoCliente;
                            $max = $c->monto_maximo ?? 0;
                            $usado = $c->monto_usado ?? 0;
                            $dias = $c->dias_credito ?? 0;
                            $fechaLim = $c->fecha_asignacion ?? now()->format('Y-m-d');
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-md bg-gray-100 text-gray-800 font-semibold">
                                    {{ $cliente->codigo_cliente ?? '—' }}
                                </span>
                            </td>

                            <td class="px-6 py-3">
                                <div class="font-medium text-gray-900">{{ $cliente->nombre }}</div>
                                <div class="text-gray-500">{{ $cliente->telefono }}</div>
                            </td>

                            <td class="px-6 py-3 text-gray-900">
                                {{ $cliente->nombre_empresa }}
                            </td>

                            <td class="px-6 py-3 text-gray-900">
                                {{ $cliente->correo_electronico }}
                            </td>

                            <td class="px-6 py-3">
                                <div><span class="font-semibold">Límite:</span> ${{ number_format($max, 2) }}</div>
                                <div><span class="font-semibold">Usado:</span> ${{ number_format($usado, 2) }}</div>
                                <div><span class="font-semibold">Días restantes:</span> {{ $dias }}</div>
                                <div><span class="font-semibold">Fecha límite:</span> {{ $fechaLim }}</div>
                            </td>

                            <td class="px-6 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <a href="{{ route('clientes.edit', $cliente->clave_cliente) }}"
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <button type="button"
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded-lg"
                                            title="Editar crédito"
                                            @click="openEditModal(
                                                {{ $cliente->clave_cliente }},
                                                {{ $max }},
                                                {{ $usado }},
                                                '{{ $fechaLim }}'
                                            )">
                                        <i class="fas fa-credit-card"></i>
                                    </button>

                                    <button type="button"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg"
                                            title="Historial / Registrar pago"
                                            @click="openHistorial({{ $cliente->clave_cliente }})">
                                        <i class="fas fa-money-bill"></i>
                                    </button>

                                    <button type="button"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg"
                                            title="Eliminar"
                                            @click="openDelete({{ $cliente->clave_cliente }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                No hay clientes registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4">
            {{ $clientes->links() }}
        </div>
    </div>

    {{-- Paginación en móvil (por si ocultas la de la tabla) --}}
    <div class="mt-4 lg:hidden">
        {{ $clientes->links() }}
    </div>

    {{-- Modal: Confirmar eliminación --}}
    <div x-cloak x-show="showModal"
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         style="display:none">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-5 sm:p-6" @click.away="showModal = false">
            <h2 class="text-lg font-semibold mb-2 text-red-600 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle"></i> Confirmar eliminación
            </h2>
            <p class="text-sm text-gray-700 mb-2">¿Estás seguro de que deseas eliminar este cliente?</p>
            <p class="text-sm text-red-600 mb-4 font-semibold">También se eliminarán su crédito y todo su historial de pagos.</p>

            <form method="POST" x-bind:action="'/clientes/' + clienteId">
                @csrf
                @method('DELETE')

                <div class="flex flex-col sm:flex-row justify-end gap-2">
                    <button type="button"
                            @click="showModal = false"
                            class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Editar crédito --}}
    <div x-cloak x-show="showEditModal"
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         style="display:none">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-xl p-5 sm:p-6" @click.away="showEditModal = false">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-credit-card"></i> Editar crédito del cliente
            </h2>

            <form method="POST" id="formEditarCredito">
                @csrf
                @method('PUT')

                <div class="grid gap-4">
                    <div>
                        <label class="block text-sm font-medium">Monto máximo</label>
                        <input id="monto_maximo" name="monto_maximo" type="number" step="0.01" required
                               class="w-full p-2 border rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Días (calculados)</label>
                        <input id="dias_credito" name="dias_credito" type="number" readonly
                               class="w-full p-2 border rounded-lg bg-gray-100 cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Monto usado</label>
                        <p id="monto_usado_label" class="text-gray-700 font-medium"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Fecha límite del crédito</label>
                        <input id="fecha_asignacion" name="fecha_asignacion" type="date" required
                               class="w-full p-2 border rounded-lg"
                               x-on:input="updateFromDate()">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Fecha límite (confirmación)</label>
                        <p id="fecha_limite_label" class="text-gray-700 font-medium"></p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Estatus</span>
                            <span class="font-semibold"
                                  x-text="estatus === 'activo' ? 'Activo' : estatus === 'advertencia' ? 'Por vencer' : 'Vencido'">
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-1">
                            <span class="text-gray-600">Días restantes</span>
                            <span class="font-semibold" x-text="diasRestantes"></span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 mt-4">
                    <button type="button" @click="showEditModal = false"
                            class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Historial de pagos / Registrar pago --}}
    <div x-cloak x-show="showHistorialModal"
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         style="display:none">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-xl p-5 sm:p-6" @click.away="showHistorialModal = false">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-file-invoice-dollar"></i> Historial de pagos
            </h2>

            {{-- Lista de pagos --}}
            <template x-if="getPagosCliente(historialClienteId).length">
                <div class="mb-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Monto</th>
                                <th class="py-2">Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="p in getPagosCliente(historialClienteId)" :key="p.id">
                                <tr class="border-t">
                                    <td class="py-2 pr-4" x-text="p.fecha"></td>
                                    <td class="py-2 pr-4" x-text="Number(p.monto).toFixed(2)"></td>
                                    <td class="py-2" x-text="p.descripcion"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="!getPagosCliente(historialClienteId).length">
                <div class="mb-4">
                    <p class="text-gray-600">Sin pagos registrados.</p>
                </div>
            </template>

            {{-- Mensaje si no se puede pagar --}}
            <p class="text-red-600 font-semibold mb-2" x-show="getMontoUsado(historialClienteId) == 0">
                Este cliente no tiene crédito usado. No puedes registrar un pago.
            </p>

            {{-- Formulario de pago --}}
            <form method="POST" :action="`/clientes/${historialClienteId}/pagos`" x-show="getMontoUsado(historialClienteId) > 0">
                @csrf

                <div class="grid gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium">Monto</label>
                        <input type="number" name="monto" step="0.01" required
                               class="w-full border rounded-lg p-2"
                               x-model.number="montoPago">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descripción</label>
                        <input type="text" name="descripcion" required class="w-full border rounded-lg p-2">
                    </div>
                </div>

                <p class="text-red-600 text-sm font-semibold mt-2" x-show="montoPago > getMontoUsado(historialClienteId)">
                    El monto ingresado excede el crédito usado. Máximo permitido:
                    $<span x-text="getMontoUsado(historialClienteId).toFixed(2)"></span>
                </p>

                <div class="flex flex-col sm:flex-row justify-end gap-2 mt-4">
                    <button type="button" @click="showHistorialModal = false"
                            class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg">
                        Cerrar
                    </button>

                    <button type="submit"
                            class="w-full sm:w-auto bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg"
                            :disabled="montoPago <= 0 || montoPago > getMontoUsado(historialClienteId)">
                        Registrar pago
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function clientesGerente() {
    return {
        showModal: false,
        showEditModal: false,
        showHistorialModal: false,

        clienteId: null,
        historialClienteId: null,

        estatus: '',
        diasRestantes: 0,

        pagos: @js($pagos),
        clientes: @js($clientesJson),

        montoPago: 0,

        init() {
            // nada extra por ahora
        },

        openDelete(id) {
            this.clienteId = id;
            this.showModal = true;
        },

        openHistorial(id) {
            this.historialClienteId = id;
            this.montoPago = 0;
            this.showHistorialModal = true;
        },

        openEditModal(clienteId, monto_maximo, monto_usado, fecha_limite) {
            this.showEditModal = true;
            this.clienteId = clienteId;

            const form = document.getElementById('formEditarCredito');
            form.action = `/clientes/credito/${clienteId}`;

            document.getElementById('monto_maximo').value = parseFloat(monto_maximo || 0);
            document.getElementById('monto_usado_label').textContent = parseFloat(monto_usado || 0).toFixed(2);

            const fechaInput = document.getElementById('fecha_asignacion');

            const hoy = new Date();
            const toYMD = (d) => {
                const m = (d.getMonth() + 1).toString().padStart(2, '0');
                const day = d.getDate().toString().padStart(2, '0');
                return `${d.getFullYear()}-${m}-${day}`;
            };

            fechaInput.value = fecha_limite ? fecha_limite : toYMD(hoy);

            this.updateFromDate();
        },

        updateFromDate() {
            const fechaInput = document.getElementById('fecha_asignacion');
            const fechaStr = fechaInput.value;

            const hoy = new Date();
            // normaliza a medianoche para evitar brincos por horas
            hoy.setHours(0,0,0,0);

            const fechaLimite = new Date(fechaStr);
            fechaLimite.setHours(0,0,0,0);

            const diffMs = fechaLimite - hoy;
            const diffDias = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            this.diasRestantes = diffDias;

            document.getElementById('dias_credito').value = Math.max(0, diffDias);
            document.getElementById('fecha_limite_label').textContent = fechaStr;

            if (diffDias > 7) this.estatus = 'activo';
            else if (diffDias >= 1) this.estatus = 'advertencia';
            else this.estatus = 'vencido';
        },

        getPagosCliente(id) {
            return this.pagos.filter(p => String(p.clave_cliente) === String(id));
        },

        getMontoUsado(id) {
            const cliente = this.clientes.find(c => String(c.clave_cliente) == String(id));
            return cliente && cliente.credito_cliente && typeof cliente.credito_cliente.monto_usado !== 'undefined'
                ? parseFloat(cliente.credito_cliente.monto_usado)
                : 0;
        }
    }
}

// Ocultar mensajes después de 5 segundos
setTimeout(() => {
  document.getElementById('success-message')?.remove();
  document.getElementById('error-message')?.remove();
  document.getElementById('validation-message')?.remove();
}, 5000);
</script>
@endpush
