<?php

namespace App\Helpers;

class NumeroALetras
{
    public static function convertir($numero, $moneda = 'MXN')
    {
        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $letras = $formatter->format($numero);

        $monedaTexto = match (strtoupper($moneda)) {
            'USD' => 'dólares',
            'MXN' => 'pesos',
            default => 'unidades'
        };

        return ucfirst($letras) . " $monedaTexto";
    }
}
