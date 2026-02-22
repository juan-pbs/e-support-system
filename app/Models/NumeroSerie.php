<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventario;

class NumeroSerie extends Model
{
    protected $table = 'numeros_serie';

    protected $fillable = [
        'inventario_id',
        'numero_serie',
    ];

    public function inventario()
    {
        return $this->belongsTo(Inventario::class);
    }
}
