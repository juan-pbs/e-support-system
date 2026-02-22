<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenMaterialExtra extends Model
{
    protected $table = 'orden_material_extra';
    protected $primaryKey = 'id_material_extra'; // ajusta si cambia
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_orden_servicio',
        'descripcion',
        'cantidad',
        'precio_unitario', // NULL = pendiente
        'subtotal',        // NULL = pendiente (si existe)
    ];

    protected $casts = [
        'cantidad'        => 'float',
        'precio_unitario' => 'float',
        'subtotal'        => 'float',
    ];

    public function getPrecioPendienteAttribute(): bool
    {
        return is_null($this->precio_unitario);
    }
}
