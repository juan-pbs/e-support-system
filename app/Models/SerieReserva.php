<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerieReserva extends Model
{
    protected $table = 'serie_reservas';

    protected $fillable = [
        'codigo_producto',
        'numero_serie',
        'token',
        'user_id',
        'estado',
        'reserved_at',
        'expires_at',
        'source_type',
        'source_id',
        'assigned_at',
    ];

    protected $casts = [
        'codigo_producto' => 'integer',
        'user_id'         => 'integer',
        'source_id'       => 'integer',
        'reserved_at'     => 'datetime',
        'expires_at'      => 'datetime',
        'assigned_at'     => 'datetime',
    ];
}
