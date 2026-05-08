<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoLogisticoDetalle extends Model
{
    protected $table = 'movimiento_logistico_detalles';

    protected $fillable = [
        'movimiento_logistico_id',
        'codigo_producto',
        'nombre_producto',
        'cantidad',
        'unidad',
        'tipo_control',
        'seriales',
        'payload',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'seriales' => 'array',
        'payload' => 'array',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoLogistico::class, 'movimiento_logistico_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'codigo_producto', 'codigo_producto');
    }
}
