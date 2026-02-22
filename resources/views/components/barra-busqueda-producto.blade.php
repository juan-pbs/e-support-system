@props([
    'autocompleteUrl',
    'placeholder' => 'Buscar producto...',
    'inputId' => 'buscar-producto',
    'resultId' => 'resultados-producto',
    'name' => 'buscar',
    'idName' => 'producto_id',   // ✅ hidden con el ID real
    'value' => null,             // opcional (si lo mandas desde la vista)
    'idValue' => null,           // opcional (si lo mandas desde la vista)
])

@php
    $buscar = $value ?? request($name);
    $productoId = $idValue ?? request($idName);

    $producto = null;

    if ($productoId) {
        $producto = \App\Models\Producto::where('codigo_producto', $productoId)->first();
    } elseif ($buscar) {
        $producto = \App\Models\Producto::where('numero_parte', $buscar)
            ->orWhere('nombre', $buscar)
            ->first();
    }

    $inputValue = $producto
        ? $producto->nombre . ($producto->numero_parte ? ' (' . $producto->numero_parte . ')' : '')
        : ($buscar ?? '');
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

    {{-- ✅ aquí mandamos el ID real del producto --}}
    <input
        type="hidden"
        id="{{ $inputId }}-id"
        name="{{ $idName }}"
        value="{{ $productoId ?? '' }}"
    >

    <ul id="{{ $resultId }}"
        class="absolute z-50 w-full bg-white border rounded-lg mt-1 hidden shadow text-sm max-h-48 overflow-y-auto">
    </ul>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById(@json($inputId));
    const hiddenId = document.getElementById(@json($inputId . '-id'));
    const resultados = document.getElementById(@json($resultId));
    const form = input.closest('form'); // ✅ usa el FORM PADRE (filtros)

    let lastReq = 0;

    function hideResults() {
        resultados.innerHTML = '';
        resultados.classList.add('hidden');
    }

    function showResults() {
        resultados.classList.remove('hidden');
    }

    // Si el usuario escribe manualmente, limpiamos el producto_id
    input.addEventListener('input', () => {
        hiddenId.value = '';

        const termino = input.value.trim();
        hideResults();

        if (termino.length < 2) return;

        const reqId = ++lastReq;

        fetch(`{{ $autocompleteUrl }}?term=${encodeURIComponent(termino)}`)
            .then(res => res.json())
            .then(data => {
                // Evitar respuestas viejas (race condition)
                if (reqId !== lastReq) return;

                if (Array.isArray(data) && data.length) {
                    showResults();

                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = item.label;
                        li.className = 'px-4 py-2 cursor-pointer hover:bg-blue-100';

                        li.onclick = () => {
                            input.value = item.label;  // ✅ mostrar texto bonito
                            hiddenId.value = item.id;  // ✅ enviar ID real
                            hideResults();
                            form.submit();
                        };

                        resultados.appendChild(li);
                    });
                }
            })
            .catch(() => {
                hideResults();
            });
    });

    // Ocultar si haces clic fuera
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !resultados.contains(e.target)) {
            hideResults();
        }
    });
});
</script>
@endpush
