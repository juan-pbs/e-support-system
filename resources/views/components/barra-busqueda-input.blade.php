@props([
    'name',
    'placeholder' => 'Buscar...',
    'inputId' => 'buscar',
    'resultId' => 'resultados',
    'autocompleteUrl',
])

<div class="relative">
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ request($name) }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
        oninput="buscarSugerencias(this, '{{ $autocompleteUrl }}', '{{ $resultId }}')"
    >
    <ul id="{{ $resultId }}"
        class="absolute z-10 bg-white w-full border border-gray-300 rounded-lg mt-1 hidden max-h-60 overflow-y-auto text-sm text-gray-700">
    </ul>
</div>

@once
@push('scripts')
<script>
    function buscarSugerencias(input, url, resultId) {
        const resultBox = document.getElementById(resultId);
        const term = input.value.trim();

        // Si está vacío, enviamos el formulario para mostrar todo
        if (term.length === 0) {
            resultBox.classList.add('hidden');
            const form = input.closest('form');
            if (form) form.submit();
            return;
        }

        // Si tiene menos de 2 caracteres, no mostrar sugerencias
        if (term.length < 2) {
            resultBox.classList.add('hidden');
            return;
        }

        fetch(`${url}?term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                resultBox.innerHTML = '';
                if (data.length === 0) {
                    resultBox.classList.add('hidden');
                    return;
                }

                data.forEach(item => {
                    const li = document.createElement('li');
                    li.textContent = item.label;
                    li.classList.add('px-4', 'py-2', 'hover:bg-blue-100', 'cursor-pointer');
                    li.onclick = () => {
                        input.value = item.label;
                        resultBox.classList.add('hidden');

                        // Al hacer clic, se envía el formulario
                        const form = input.closest('form');
                        if (form) form.submit();
                    };
                    resultBox.appendChild(li);
                });

                resultBox.classList.remove('hidden');
            });
    }
</script>
@endpush
@endonce
