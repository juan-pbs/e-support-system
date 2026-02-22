@props([
    'evento'
])

<button x-on:click="{{ $evento }}"
        type="button"
        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center gap-2">
    <i data-lucide="arrow-big-up-dash"></i>
    <span>Importar Excel</span>
</button>
