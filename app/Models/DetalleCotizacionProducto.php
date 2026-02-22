<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCotizacionProducto extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'detalle_cotizacion_producto';
    protected $primaryKey = 'id_cotizacion_producto';

    protected $fillable = [
        'id_cotizacion',
        'codigo_producto',
        'nombre_producto',
        'descripcion_item',   // <-- agregado
        'cantidad',
        'precio_unitario',
        'total',
        'unidad',             // <-- agregado
    ];

    protected $casts = [
        'precio_unitario' => 'float',
        'total' => 'float',
        'cantidad' => 'integer',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'codigo_producto');
    }
}
