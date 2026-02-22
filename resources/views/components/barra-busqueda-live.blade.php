@props([
    'action', // Ruta a la que se enviará el formulario (proveedores.index)
    'autocompleteUrl', // Ruta para el fetch del autocomplete (proveedores.autocomplete)
    'placeholder' => 'Buscar...',
    'inputId' => 'buscar-proveedor',
    'resultId' => 'resultados-proveedor',
    'name' => 'buscar',
])

<form method="GET" action="{{ $action }}" class="relative w-full">
    <input
        type="text"
        id="{{ $inputId }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        value="{{ request($name) }}"
        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
    >
    <ul id="{{ $resultId }}" class="absolute z-50 w-full bg-white border rounded-lg mt-1 hidden shadow text-sm max-h-48 overflow-y-auto"></ul>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('{{ $inputId }}');
    const resultados = document.getElementById('{{ $resultId }}');
    const form = input.closest('form');

    input.addEventListener('input', () => {
        const termino = input.value.trim();
        resultados.innerHTML = '';
        resultados.classList.add('hidden');

        if (termino.length >= 2) {
            fetch(`{{ $autocompleteUrl }}?term=${encodeURIComponent(termino)}`)
                .then(res => res.json())
                .then(data => {
                    if (Array.isArray(data) && data.length) {
                        resultados.classList.remove('hidden');
                        data.forEach(item => {
                            const li = document.createElement('li');
                            li.textContent = item.label;
                            li.className = 'px-4 py-2 cursor-pointer hover:bg-blue-100';
                            li.onclick = () => {
                                input.value = item.label;
                                resultados.innerHTML = '';
                                resultados.classList.add('hidden');
                                form.submit(); // ← ahora sí va a index
                            };
                            resultados.appendChild(li);
                        });
                    }
                });
        }
    });
});
</script>
@endpush
