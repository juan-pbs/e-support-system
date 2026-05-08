<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';
    protected $primaryKey = 'clave_proveedor'; // BIGINT PK
    // timestamps activos (tu tabla tiene created_at/updated_at)

    protected $fillable = [
        'nombre',     // Emisor
        'rfc',
        'alias',
        'direccion',
        'direccion_logistica',
        'direccion_logistica_place_id',
        'direccion_logistica_latitud',
        'direccion_logistica_longitud',
        'direccion_logistica_referencia',
        'direccion_logistica_verificada_en_mapa',
        'direccion_logistica_metodo',
        'contacto',
        'telefono',
        'correo',
    ];

    protected $casts = [
        'direccion_logistica_latitud' => 'float',
        'direccion_logistica_longitud' => 'float',
        'direccion_logistica_verificada_en_mapa' => 'boolean',
    ];

    // Si aún usas la relación a productos por pivote, déjala; si no, puedes quitarla.
    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_proveedor', 'clave_proveedor', 'codigo_producto');
    }

    public function movimientosLogisticos()
    {
        return $this->hasMany(MovimientoLogistico::class, 'clave_proveedor', 'clave_proveedor');
    }
}
