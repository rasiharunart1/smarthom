<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'max_devices',
        'max_widgets_per_device',
        'features',
        'has_logs',
    ];

    protected $casts = [
        'features'  => 'array',
        'has_logs'  => 'boolean',
    ];
}
