<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditoCliente extends Model
{
    use HasFactory;

    protected $table = 'credito_cliente';
    protected $primaryKey = 'id_credito';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'monto_maximo',
        'monto_usado',
        'dias_credito',
        'fecha_asignacion',
        'estatus',
        'clave_cliente',
    ];

    // Relación con el modelo Cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'clave_cliente', 'clave_cliente');
    }

    // Atributo calculado: crédito disponible
    public function getDisponibleAttribute()
    {
        return $this->monto_maximo - $this->monto_usado;
    }
}

