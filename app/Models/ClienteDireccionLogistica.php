<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteDireccionLogistica extends Model
{
    protected $table = 'cliente_direcciones_logisticas';

    protected $fillable = [
        'clave_cliente',
        'alias',
        'direccion_formateada',
        'place_id',
        'latitud',
        'longitud',
        'referencia',
        'activa',
        'predeterminada',
        'verificada_en_mapa',
        'metodo_verificacion',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'activa' => 'boolean',
        'predeterminada' => 'boolean',
        'verificada_en_mapa' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'clave_cliente', 'clave_cliente');
    }
}
