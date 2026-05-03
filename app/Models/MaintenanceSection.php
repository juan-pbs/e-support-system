<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSection extends Model
{
    protected $fillable = [
        'key',
        'name',
        'paths',
        'enabled',
        'message',
        'updated_by',
    ];

    protected $casts = [
        'paths' => 'array',
        'enabled' => 'boolean',
    ];
}
