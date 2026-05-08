<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarAccount extends Model
{
    protected $fillable = [
        'user_id',
        'google_email',
        'google_sub',
        'calendar_id',
        'access_token',
        'refresh_token',
        'scopes',
        'token_expires_at',
        'sync_enabled',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'token_expires_at' => 'datetime',
        'sync_enabled' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderEvents()
    {
        return $this->hasMany(GoogleCalendarOrderEvent::class);
    }

    public function getIsConnectedAttribute(): bool
    {
        return is_null($this->disconnected_at)
            && (!empty($this->refresh_token) || !empty($this->access_token));
    }
}
