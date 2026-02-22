<?php

namespace App\View\Components;

use Illuminate\View\Component;

class BarraBusquedaAutocomplete extends Component
{
    public function __construct(
        public string $autocompleteUrl,
        public string $placeholder = 'Buscar producto...',
        public string $inputId = 'buscar-producto',
        public string $resultId = 'resultados-producto',
        public string $name = 'buscar',
        public string $idName = 'codigo_producto',
        public $value = null,
        public $idValue = null,
    ) {}

    public function render()
    {
        return view('components.barra-busqueda-autocomplete');
    }
}
