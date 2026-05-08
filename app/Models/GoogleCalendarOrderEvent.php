<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarOrderEvent extends Model
{
    protected $fillable = [
        'google_calendar_account_id',
        'user_id',
        'orden_servicio_id',
        'calendar_id',
        'event_id',
        'payload_hash',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(GoogleCalendarAccount::class, 'google_calendar_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id', 'id_orden_servicio');
    }
}
