<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleOrdenProductoSerie extends Model
{
    protected $table = 'detalle_orden_producto_series';

    protected $fillable = [
        'id_orden_producto',
        'numero_serie',
    ];

    // Si tu tabla NO tiene timestamps, descomenta:
    // public $timestamps = false;

    public function detalle()
    {
        return $this->belongsTo(DetalleOrdenProducto::class, 'id_orden_producto', 'id_orden_producto');
    }
}
