<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionServicio extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'detalle_cotizacion_servicio';
    protected $primaryKey = 'id_detalle_servicio';

    protected $fillable = [
        'id_cotizacion',
        'descripcion',
        'precio',
    ];

    protected $casts = [
        'precio' => 'float',
    ];

    /**
     * Relación: este servicio pertenece a una cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
