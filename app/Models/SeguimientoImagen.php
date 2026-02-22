<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoImagen extends Model
{
    protected $table = 'seguimiento_imagenes';
    protected $primaryKey = 'id_imagen';
    public $timestamps = true;

    protected $fillable = [
        'id_orden_servicio',
        'ruta',
        'titulo',
        'orden',
    ];

    protected $casts = [
        'orden'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Orden de servicio a la que pertenece la imagen.
     */
    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    // Alias más explícito
    public function ordenServicio()
    {
        return $this->orden();
    }

    /**
     * URL pública de la imagen para usar en <img src="...">
     * Se basa en la ruta almacenada en /storage/app/public.
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->ruta)) {
            return null;
        }

        // Si ya viene una URL completa, la regresamos tal cual
        if (str_starts_with($this->ruta, 'http://') || str_starts_with($this->ruta, 'https://')) {
            return $this->ruta;
        }

        // Caso normal: ruta tipo "seguimientos/archivo.jpg"
        return asset('storage/' . ltrim($this->ruta, '/'));
    }
}
