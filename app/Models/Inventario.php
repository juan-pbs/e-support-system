<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Producto;
use App\Models\NumeroSerie;
use App\Models\Proveedor;

class Inventario extends Model
{
    protected $table = 'inventario';

    protected $fillable = [
        'costo',
        'precio',
        'tipo_control',
        'cantidad_ingresada',
        'piezas_por_paquete',
        'paquetes_restantes',
        'piezas_sueltas',
        'numero_serie',
        'fecha_entrada',
        'fecha_salida',
        'hora_entrada',
        'hora_salida',
        'fecha_caducidad',
        'codigo_producto',
        'clave_proveedor',
    ];

    /**
     * Relación con el producto.
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'codigo_producto', 'codigo_producto');
    }

    /**
     * Relación con el proveedor.
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'clave_proveedor', 'clave_proveedor');
    }

    /**
     * Relación con los números de serie.
     */
    public function numerosSerie()
    {
        return $this->hasMany(NumeroSerie::class);
    }

    /**
     * Accessor para total de piezas disponibles en esta entrada.
     */
    public function getTotalPiezasAttribute()
    {
        return ($this->paquetes_restantes * $this->piezas_por_paquete) + $this->piezas_sueltas;
    }
}
