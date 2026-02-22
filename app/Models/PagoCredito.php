<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoCredito extends Model
{
    protected $table = 'pagos_credito';

    protected $fillable = [
        'clave_cliente',
        'monto',
        'fecha',
        'descripcion',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'clave_cliente', 'clave_cliente');
    }
}
