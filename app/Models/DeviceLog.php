<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'widget_id',
        'widget_key',
        'event_type',
        'old_value',
        'new_value',
        'metadata',
        'created_at',
        'source',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];


    public function device()
    {
        return $this->belongsTo(Device::class);
    }
    public function widget()
    {
        return $this->belongsTo(Widget::class);
    }

    public function scopeRecent($query, $day=7)
    {
        $date = now()->subDays($day);
        return $query->where('created_at', '>=', $date);
    }
    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
