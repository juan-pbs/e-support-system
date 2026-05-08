<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JornadaLogistica extends Model
{
    protected $table = 'jornadas_logisticas';

    protected $fillable = [
        'folio',
        'nombre',
        'fecha',
        'estado',
        'created_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cerradaPor()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoLogistico::class, 'jornada_logistica_id');
    }
}
