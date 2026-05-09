@extends('layouts.sidebar-navigation')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex items-center gap-3 mb-4">
        <x-boton-volver />
        <h1 class="text-2xl font-bold">Nueva entrada de inventario</h1>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-green-100 text-green-800 border border-green-300">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-100 text-red-800 border border-red-300">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- INFO DEL PRODUCTO (si viene) --}}
        @isset($producto)
        <div class="lg:col-span-1 rounded border bg-white p-4">
            @php
                $img = $producto->imagen
                    ? (\Illuminate\Support\Str::startsWith($producto->imagen, ['http://','https://'])
                        ? $producto->imagen
                        : asset($producto->imagen))
                    : asset('images/imagen.png');
            @endphp

            <div class="flex items-start gap-3">
                <img src="{{ $img }}" class="w-24 h-24 object-cover rounded border" alt="img">
                <div>
                    <div class="font-semibold">{{ $producto->nombre }}</div>
                    <div class="text-xs text-gray-600">Parte: <span class="font-mono">{{ $producto->numero_parte }}</span></div>
                    <div class="text-xs text-gray-600">Categoría: {{ $producto->categoria ?? 'GENERAL' }}</div>
                    <div class="text-xs text-gray-600">Unidad: {{ $producto->unidad ?? '-' }}</div>

                    <div class="text-xs text-gray-600 mt-1">
                        Stock: <strong>{{ $stock['total'] ?? ($producto->stock_total ?? 0) }}</strong>
                    </div>

                    <div class="text-xs text-gray-500 mt-1">
                        Fecha/Hora de entrada: <strong>Automática</strong>
                    </div>
                </div>
            </div>

            @if(!empty($series) && $series->count())
                <hr class="my-3">
                <div>
                    <div class="font-semibold text-sm mb-1">Números de serie registrados</div>
                    <div class="max-h-44 overflow-auto border rounded p-2 text-xs">
                        @foreach($series as $s)
                            <div class="font-mono">{{ $s }}</div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @endisset

        {{-- FORMULARIO --}}
        <div class="@isset($producto) lg:col-span-2 @else lg:col-span-3 @endisset rounded border bg-white p-4 space-y-4">

            {{-- SELECTOR DE PRODUCTO (GET) SOLO CUANDO NO HAY PRODUCTO --}}
            @if(!isset($producto))
                <div class="p-3 border rounded-lg bg-gray-50">
                    <label class="block text-sm font-medium mb-1">Producto *</label>

                    <form method="GET" action="{{ route('entrada') }}">
                        <x-barra-busqueda-autocomplete
                            autocompleteUrl="{{ route('entrada.autocomplete') }}"
                            placeholder="Buscar por nombre o número de parte..."
                            inputId="buscar-producto"
                            resultId="resultados-producto"
                            name="buscar"
                            idName="codigo_producto"
                        />
                    </form>

                    <p class="text-xs text-gray-600 mt-2">
                        Selecciona un producto de la lista para cargar sus datos.
                    </p>
                </div>
            @endif

            {{-- POST SOLO CUANDO YA HAY PRODUCTO --}}
            @if(isset($producto))
            <form method="POST" action="{{ route('entrada.store') }}" class="space-y-4">
                @csrf

                <input type="hidden" name="codigo_producto" value="{{ $producto->codigo_producto }}">

                <div>
                    <label class="block text-sm font-medium">Proveedor (opcional)</label>
                    <select name="clave_proveedor" class="border rounded px-3 py-2 w-full">
                        <option value="">Sin proveedor</option>
                        @foreach(($proveedores ?? []) as $prov)
                            <option value="{{ $prov->clave_proveedor }}"
                                {{ old('clave_proveedor') == $prov->clave_proveedor ? 'selected' : '' }}>
                                {{ $prov->nombre }} @if($prov->rfc) ({{ $prov->rfc }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Costo *</label>
                        <input type="number" step="0.01" min="0" name="costo"
                               value="{{ old('costo') }}"
                               class="border rounded px-3 py-2 w-full" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Precio</label>
                        <input type="number" step="0.01" min="0" name="precio"
                               value="{{ old('precio') }}"
                               class="border rounded px-3 py-2 w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Tipo de control *</label>

                    @php
                        // ✅ AQUÍ ESTÁ LA CLAVE: last tipo_control real del producto
                        $tc = old('tipo_control', $ultimoTipoControl ?? 'PIEZAS');
                    @endphp

                    <div class="flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="tipo_control" value="PIEZAS" {{ $tc === 'PIEZAS' ? 'checked' : '' }}>
                            <span>Por piezas</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="tipo_control" value="PAQUETES" {{ $tc === 'PAQUETES' ? 'checked' : '' }}>
                            <span>Por paquetes</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="tipo_control" value="SERIE" {{ $tc === 'SERIE' ? 'checked' : '' }}>
                            <span>Por número de serie</span>
                        </label>
                    </div>
                </div>

                {{-- PIEZAS --}}
                <div id="bloque-piezas" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Cantidad *</label>
                        <input type="number" name="cantidad_ingresada" min="1"
                               value="{{ old('cantidad_ingresada') }}"
                               class="border rounded px-3 py-2 w-full">
                    </div>
                </div>

                {{-- PAQUETES --}}
                <div id="bloque-paquetes" class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
                    <div>
                        <label class="block text-sm font-medium">Paquetes *</label>
                        <input type="number" name="cantidad_ingresada" min="1"
                               value="{{ old('cantidad_ingresada') }}"
                               class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Piezas por paquete *</label>
                        <input type="number" name="piezas_por_paquete" min="1"
                               value="{{ old('piezas_por_paquete') }}"
                               class="border rounded px-3 py-2 w-full">
                    </div>
                </div>

                {{-- SERIE --}}
                <div id="bloque-serie" class="hidden">
                    <label class="block text-sm font-medium">Números de serie (uno por línea) *</label>
                    <textarea name="numeros_serie" rows="6"
                              class="border rounded px-3 py-2 w-full"
                              placeholder="ABC123&#10;XYZ456">{{ old('numeros_serie') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Fecha de caducidad</label>
                        <input type="date" name="fecha_caducidad"
                               value="{{ old('fecha_caducidad') }}"
                               class="border rounded px-3 py-2 w-full">
                    </div>
                </div>

                <div class="pt-2">
                    <button class="px-5 py-2 rounded bg-green-600 text-white hover:bg-green-700">
                        Guardar entrada
                    </button>
                </div>
            </form>
            @else
                <div class="p-4 border rounded-lg bg-white text-sm text-gray-700">
                    Primero selecciona un producto para habilitar el registro de entrada.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ✅ Script: alternar bloques + required dinámico (sin forzar PIEZAS) --}}
<script>
(function () {
  function setDisabled(containerId, disabled) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.querySelectorAll('input, textarea, select').forEach(i => {
      i.disabled = disabled;
      if (disabled) i.removeAttribute('required');
    });
  }

  function toggleBloques(tipo) {
    const piezas   = document.getElementById('bloque-piezas');
    const paquetes = document.getElementById('bloque-paquetes');
    const serie    = document.getElementById('bloque-serie');

    piezas?.classList.toggle('hidden', tipo !== 'PIEZAS');
    paquetes?.classList.toggle('hidden', tipo !== 'PAQUETES');
    serie?.classList.toggle('hidden', tipo !== 'SERIE');

    setDisabled('bloque-piezas',   tipo !== 'PIEZAS');
    setDisabled('bloque-paquetes', tipo !== 'PAQUETES');
    setDisabled('bloque-serie',    tipo !== 'SERIE');

    if (tipo === 'PIEZAS') {
      piezas?.querySelector('input[name="cantidad_ingresada"]')?.setAttribute('required', 'required');
    }
    if (tipo === 'PAQUETES') {
      paquetes?.querySelector('input[name="cantidad_ingresada"]')?.setAttribute('required', 'required');
      paquetes?.querySelector('input[name="piezas_por_paquete"]')?.setAttribute('required', 'required');
    }
    if (tipo === 'SERIE') {
      serie?.querySelector('textarea[name="numeros_serie"]')?.setAttribute('required', 'required');
    }
  }

  document.addEventListener('change', (e) => {
    if (e.target?.name === 'tipo_control') toggleBloques(e.target.value);
  });

  document.addEventListener('DOMContentLoaded', () => {
    // ✅ toma el radio que Blade dejó checked (ya viene del último tipo_control)
    const checked = document.querySelector('input[name="tipo_control"]:checked');
    toggleBloques(checked ? checked.value : 'PIEZAS');
  });
})();
</script>
@endsection
