@props(['onclick'])

<button type="button"
        onclick="{{ $onclick }}"
        class="p-2 rounded-full bg-red-500 hover:bg-red-600 text-white"
        title="Eliminar">
    <i class="fas fa-trash"></i>
</button>
