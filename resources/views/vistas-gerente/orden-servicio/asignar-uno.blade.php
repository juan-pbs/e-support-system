@extends('layouts.sidebar-navigation')

@section('title', 'Asignar Técnico')

@section('content')
<div class="max-w-7xl mx-auto p-4 md:p-6">
  <!-- Header -->
            <div class="relative mb-10 text-center mx-a">


               <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Asignar técnico a orden de servicio</h1>


                <div class="flex items-center justify-between mb-6">
                            <x-boton-volver />


                </div>
           </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Orden (detalles) -->
    <section class="lg:col-span-2 bg-white border rounded-xl p-5">
      <h3 class="text-lg font-semibold mb-4">Orden</h3>
      @php
        $oid = $orden->id_orden_servicio ?? $orden->getKey();
        $folio = $orden->folio ?? ('ORD-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT));
      @endphp

      <div class="grid md:grid-cols-2 gap-4 text-sm">
        <div>
          <p><span class="font-medium text-gray-600">Folio:</span> {{ $folio }}</p>
          <p><span class="font-medium text-gray-600">Cliente:</span> {{ $orden->cliente->nombre ?? '—' }}</p>
          <p><span class="font-medium text-gray-600">Tipo:</span> {{ $orden->tipo_orden ?? '—' }}</p>
          <p><span class="font-medium text-gray-600">Estado:</span> {{ $orden->estado ?? '—' }}</p>
        </div>
        <div>
          <p><span class="font-medium text-gray-600">Prioridad:</span> {{ $orden->prioridad ?? '—' }}</p>
          <p><span class="font-medium text-gray-600">Servicio:</span> {{ $orden->servicio ?? '—' }}</p>
          <p><span class="font-medium text-gray-600">Creada:</span> {{ optional($orden->created_at)->format('d/m/Y H:i') }}</p>
        </div>
      </div>

      <div class="mt-5">
        <h4 class="font-medium text-gray-700 mb-2">Materiales / Productos</h4>
        <div class="overflow-x-auto border rounded-lg">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Producto</th>
                <th class="px-3 py-2 text-left">Descripción</th>
                <th class="px-3 py-2 text-right">Cantidad</th>
                <th class="px-3 py-2 text-right">P/U</th>
                <th class="px-3 py-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              @forelse($orden->productos as $d)
                <tr>
                  <td class="px-3 py-2">{{ $d->nombre_producto ?? '—' }}</td>
                  <td class="px-3 py-2 whitespace-pre-line text-gray-600">{{ $d->descripcion ?? '—' }}</td>
                  <td class="px-3 py-2 text-right">{{ number_format($d->cantidad ?? 0, 2) }}</td>
                  <td class="px-3 py-2 text-right">{{ number_format($d->precio_unitario ?? 0, 2) }}</td>
                  <td class="px-3 py-2 text-right">{{ number_format(($d->cantidad ?? 0) * ($d->precio_unitario ?? 0), 2) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="px-3 py-4 text-center text-gray-500">Sin productos.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Técnicos -->
    <section class="bg-white border rounded-xl p-5">
      <h3 class="text-lg font-semibold mb-4">Técnicos disponibles</h3>

      <form id="asignarForm" method="POST" action="{{ route('ordenes.asignar.guardar', ['id' => $oid]) }}">
        @csrf
        {{-- @method('PUT') <!-- Úsalo si tu ruta es PUT --> --}}

        {{-- Fallback legacy: guardaré el primero también en id_tecnico --}}
        <input type="hidden" name="id_tecnico" id="id_tecnico" value="">

        @php
          $preSeleccion = old('tecnicos_ids', $orden->tecnicos->pluck('id')->all());
        @endphp

        <div id="tecnicos-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          @forelse($tecnicos as $tec)
            <label class="block">
              <input
                type="checkbox"
                name="tecnicos_ids[]"
                value="{{ $tec->id }}"
                class="sr-only peer"
                @checked(in_array($tec->id, $preSeleccion))
              >
              <div class="border rounded-lg px-3 py-2 hover:border-green-500 transition peer-checked:ring-2 peer-checked:ring-green-400 peer-checked:bg-green-50">
                <div class="font-medium">{{ $tec->name }}</div>
                @if($orden->tecnicos->contains('id', $tec->id) || $orden->id_tecnico === $tec->id)
                  <div class="text-xs text-emerald-600 mt-0.5">Actualmente asignado</div>
                @endif
              </div>
            </label>
          @empty
            <div class="text-gray-500 text-sm col-span-2">No hay técnicos registrados.</div>
          @endforelse
        </div>

        <div class="mt-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
          <select name="prioridad" class="w-full rounded-lg border-gray-300">
            @foreach($prioridades as $p)
              <option value="{{ $p }}" @selected(($orden->prioridad ?? '') === $p)>{{ $p }}</option>
            @endforeach
          </select>
        </div>

        <div class="mt-5 flex items-center justify-end gap-3">
          <a href="{{ url()->previous() }}" class="rounded-lg border px-4 py-2 text-gray-700 hover:bg-gray-50">Cancelar</a>
          <button type="submit"
                  class="rounded-lg px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold disabled:opacity-60"
                  id="btnAsignar">
            Guardar asignación
          </button>
        </div>
      </form>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
  const form = document.getElementById('asignarForm');
  const btn  = document.getElementById('btnAsignar');
  const idLegacy = document.getElementById('id_tecnico');

  function updateBtn() {
    const checks = Array.from(document.querySelectorAll('input[name="tecnicos_ids[]"]'));
    const any = checks.some(c => c.checked);
    btn.disabled = !any;
  }
  updateBtn();

  document.querySelectorAll('input[name="tecnicos_ids[]"]').forEach(ch => {
    ch.addEventListener('change', updateBtn);
  });

  // Fallback legacy: poner el primero seleccionado en id_tecnico
  form.addEventListener('submit', () => {
    const first = Array.from(document.querySelectorAll('input[name="tecnicos_ids[]"]:checked'))[0];
    idLegacy.value = first ? first.value : '';
  });
</script>
@endpush
