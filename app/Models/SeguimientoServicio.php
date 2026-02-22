<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoServicio extends Model
{
    protected $table = 'seguimiento_servicio';
    protected $primaryKey = 'id_seguimiento';
    public $timestamps = true;

    protected $fillable = [
        'id_orden_servicio',
        'observaciones',
        'comentarios',
        'imagen',
    ];

    // 👇 evita NULLs en columnas NOT NULL (en tu BD son NOT NULL)
    protected $attributes = [
        'observaciones' => '',
        'comentarios'   => '',
        'imagen'        => '',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Orden de servicio a la que pertenece este seguimiento.
     */
    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    /**
     * Imágenes asociadas a la MISMA orden de servicio.
     * (Todas las imágenes de la orden; ya no se filtra por id_seguimiento)
     */
    public function imagenes()
    {
        return $this->hasMany(SeguimientoImagen::class, 'id_orden_servicio', 'id_orden_servicio')
                    ->orderBy('orden');
    }
}
