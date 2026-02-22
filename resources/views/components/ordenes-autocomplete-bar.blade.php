@props([
    'autocompleteUrl',
    'placeholder' => 'Buscar por cliente, folio o servicio...',
    'inputId' => 'buscar-ordenes',
    'resultId' => 'resultados-ordenes',
    'name' => 'q',
    'idName' => 'orden_id',
    'value' => null,
    'idValue' => null,
    'submitOnSelect' => false,
])

@php
    $buscar  = $value ?? request($name);
    $ordenId = $idValue ?? request($idName);

    // ID real del hidden (evita concatenar en JS)
    $hiddenIdDom = $inputId . '-id';

    // Texto inicial
    $inputValue = $buscar ?? '';
@endphp

<div class="relative w-full">
    <input
        type="text"
        id="{{ $inputId }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        value="{{ $inputValue }}"
        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"
    >

    {{-- ID real (para filtro exacto) --}}
    <input
        type="hidden"
        id="{{ $hiddenIdDom }}"
        name="{{ $idName }}"
        value="{{ $ordenId ?? '' }}"
    >

    <ul
        id="{{ $resultId }}"
        class="absolute z-[9999] w-full bg-white border border-gray-200 rounded-lg mt-1 hidden shadow-lg text-sm max-h-56 overflow-y-auto"
    ></ul>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input      = document.getElementById(@json($inputId));
    const hiddenId   = document.getElementById(@json($hiddenIdDom));
    const resultados = document.getElementById(@json($resultId));
    const form       = input ? input.closest('form') : null;

    let lastReq = 0;

    function hideResults() {
        if (!resultados) return;
        resultados.innerHTML = '';
        resultados.classList.add('hidden');
    }

    function showResults() {
        if (!resultados) return;
        resultados.classList.remove('hidden');
    }

    if (!input || !hiddenId || !resultados) return;

    // ✅ Si el usuario empieza a escribir, limpiamos el id exacto (para que use q)
    input.addEventListener('input', () => {
        hiddenId.value = '';

        const termino = input.value.trim();
        hideResults();

        if (termino.length < 2) return;

        const reqId = ++lastReq;

        fetch(`{{ $autocompleteUrl }}?term=${encodeURIComponent(termino)}`)
            .then(res => res.json())
            .then(data => {
                if (reqId !== lastReq) return;

                if (Array.isArray(data) && data.length) {
                    showResults();
                    resultados.innerHTML = '';

                    data.forEach(item => {
                        const label = item.label ?? item.text ?? item.nombre ?? '';
                        if (!label) return;

                        const li = document.createElement('li');
                        li.textContent = label;
                        li.className = 'px-4 py-2 cursor-pointer hover:bg-blue-100';

                        li.addEventListener('click', (ev) => {
                            ev.preventDefault();
                            ev.stopPropagation();

                            input.value = label;
                            hiddenId.value = item.id ?? '';

                            hideResults();

                            // ✅ APLICAR BÚSQUEDA AUTOMÁTICA
                            const submitOnSelect = @json((bool) $submitOnSelect);
                            if (submitOnSelect && form) {
                                form.submit();
                            }
                        });

                        resultados.appendChild(li);
                    });
                } else {
                    hideResults();
                }
            })
            .catch((err) => {
                console.error('Autocomplete error:', err);
                hideResults();
            });
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !resultados.contains(e.target)) hideResults();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideResults();
    });

    // ✅ OPCIONAL: si presiona Enter, aplicamos búsqueda también
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && form) {
            // Si hay texto pero no se eligió sugerencia, se filtra por q
            // (hiddenId ya está vacío si el usuario escribió)
            form.submit();
        }
    });
});
</script>
@endpush
