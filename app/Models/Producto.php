<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'codigo_producto';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'numero_parte',
        'categoria',
        'clave_prodserv',
        'unidad',
        'stock_seguridad',
        'descripcion',
        'imagen',
        'activo',
        // por compatibilidad con tu UI (si existen):
        'stock_total',
        'stock_paquetes',
        'stock_piezas_sueltas',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'stock_seguridad' => 'integer',
    ];

    public function inventario()
    {
        return $this->hasMany(\App\Models\Inventario::class, 'codigo_producto', 'codigo_producto');
    }

    public function proveedores()
    {
        return $this->belongsToMany(\App\Models\Proveedor::class, 'producto_proveedor', 'codigo_producto', 'clave_proveedor');
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    public function scopeBusqueda($q, $term)
    {
        $like = "%{$term}%";
        return $q->where(function ($w) use ($like) {
            $w->where('nombre', 'like', $like)
              ->orWhere('numero_parte', 'like', $like)
              ->orWhere('categoria', 'like', $like)
              ->orWhere('clave_prodserv', 'like', $like);
        });
    }
}
