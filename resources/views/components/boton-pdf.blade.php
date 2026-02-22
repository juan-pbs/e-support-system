@props(['onclick' => null])

<button type="button"
        {{ $attributes->merge(['class' => 'p-2 rounded-full bg-blue-500 hover:bg-blue-600 text-white']) }}
        title="Ver PDF">
    <i class="fas fa-file-pdf"></i>
</button>
