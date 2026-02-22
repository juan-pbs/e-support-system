<?php

namespace App\View\Components;

use Illuminate\View\Component;

class OrdenesAutocompleteBar extends Component
{
    public string $autocompleteUrl;
    public string $placeholder;
    public string $inputId;
    public string $resultId;
    public string $name;
    public string $idName;

    public $value;
    public $idValue;

    public bool $submitOnSelect;

    public function __construct(
        string $autocompleteUrl,
        string $placeholder = 'Buscar orden...',
        string $inputId = 'buscar-ordenes',
        string $resultId = 'resultados-ordenes',
        string $name = 'q',
        string $idName = 'orden_id',
        $value = null,
        $idValue = null,
        bool $submitOnSelect = true
    ) {
        $this->autocompleteUrl  = $autocompleteUrl;
        $this->placeholder      = $placeholder;
        $this->inputId          = $inputId;
        $this->resultId         = $resultId;
        $this->name             = $name;
        $this->idName           = $idName;
        $this->value            = $value;
        $this->idValue          = $idValue;
        $this->submitOnSelect   = $submitOnSelect;
    }

    public function render()
    {
        return view('components.ordenes-autocomplete-bar');
    }
}
