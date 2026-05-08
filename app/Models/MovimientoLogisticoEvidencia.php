<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoLogisticoEvidencia extends Model
{
    protected $table = 'movimiento_logistico_evidencias';

    protected $fillable = [
        'movimiento_logistico_id',
        'tipo_foto',
        'ruta_archivo',
        'latitud',
        'longitud',
        'tomado_en',
        'comentario',
        'created_by',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'tomado_en' => 'datetime',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoLogistico::class, 'movimiento_logistico_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
