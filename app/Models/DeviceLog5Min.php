<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLog5Min extends Model
{
    use HasFactory;
    
    protected $table = 'device_log_5min';
    
    protected $fillable = [
        'device_id',
        'widget_key',
        'avg_value',
        'min_value',
        'max_value',
        'bucket_time'
    ];
    
    protected $casts = [
        'bucket_time' => 'datetime',
        'avg_value' => 'float',
        'min_value' => 'float',
        'max_value' => 'float'
    ];
}
