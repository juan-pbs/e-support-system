<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMaintenanceHistory extends Model
{
    protected $fillable = [
        'orden_id',
        'user_id',
        'action',
        'reason',
        'before_snapshot',
        'after_snapshot',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_id', 'id_orden_servicio');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
