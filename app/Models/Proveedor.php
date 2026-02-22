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
        'contacto',
        'telefono',
        'correo',
    ];

    // Si aún usas la relación a productos por pivote, déjala; si no, puedes quitarla.
    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_proveedor', 'clave_proveedor', 'codigo_producto');
    }
}
