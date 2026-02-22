<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleOrdenProducto extends Model
{
    protected $table = 'detalle_orden_producto';
    protected $primaryKey = 'id_orden_producto';

    protected $fillable = [
        'id_orden_servicio',
        'codigo_producto',
        'nombre_producto',
        'descripcion_item',
        'cantidad',
        'precio_unitario',
        'total',
        'unidad',
    ];

    protected $casts = [
        'cantidad'        => 'integer',
        'precio_unitario' => 'float',
        'total'           => 'float',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'codigo_producto', 'codigo_producto');
    }

    public function series()
    {
        return $this->hasMany(DetalleOrdenProductoSerie::class, 'id_orden_producto', 'id_orden_producto');
    }

    // Útil si a veces no guardas "total" y quieres calcularlo al vuelo
    public function getSubtotalAttribute(): float
    {
        return round((float)($this->cantidad ?? 0) * (float)($this->precio_unitario ?? 0), 2);
    }
}
