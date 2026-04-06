<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceShare extends Model
{
    protected $fillable = [
        'device_id',
        'shared_by_user_id',
        'shared_with_user_id',
        'permission',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function sharedWith()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function canControl(): bool
    {
        return $this->permission === 'control';
    }
}
