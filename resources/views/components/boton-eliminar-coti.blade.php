{{-- resources/views/components/boton-eliminar-coti.blade.php --}}
@props(['onclick'])

<button type="button"
        @click="cotizacionId = {{ $cotizacion->id_cotizacion }}; clienteNombre = '{{ $cotizacion->cliente->nombre }}'; showModal = true"
        class="p-2 rounded-full bg-red-500 hover:bg-red-600 text-white"
        title="Eliminar">
    <i class="fas fa-trash"></i>
</button>
