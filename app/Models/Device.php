<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\DeviceShare;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'device_code',
        'status',
        'last_seen_at',
        'metadata',
        'lstm_enabled',
        'lstm_config',
        'is_approved',
        'approved_at',
        'approved_by',
        'log_interval',   // Per-device override (null = inherit from user)
    ];

    protected $casts = [
        'metadata'     => 'array',
        'last_seen_at' => 'datetime',
        'lstm_enabled' => 'boolean',
        'lstm_config'  => 'array',
        'is_approved'  => 'boolean',
        'approved_at'  => 'datetime',
        'log_interval' => 'integer',
    ];

    /**
     * Use numeric 'id' as the route key instead of 'device_code'.
     * This prevents device_code (a sensitive credential) from being exposed in browser URLs.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }


    protected static function boot(){
        parent::boot();

        static::creating(function ($device){
            $device->device_code = self::generateDeviceCode();
        });
    }


    public static function generateDeviceCode()
    {
        do{
            $code ='DEV_'.strtoupper(Str::random(10));
        }while(self::where('device_code', $code)->exists());

        return $code;
    }
    public function markAsOnline()
    {
        $this->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    public function updateStatus($status = 'online')
    {
        $this->update([
            'status' => $status,
            'last_seen_at' => now(),
        ]);

    }

    public function isOnline()
    {
        if(!$this->last_seen_at){
            return false;
        }
        return $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status){
            'online' =>'success',
            'offline' => 'secondary',
            'error' => 'danger',
            default => 'warning'
        };
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function widgets()
    {
        return $this->hasMany(Widget::class);
    }
    public function deviceLogs()
    {
        return $this->hasMany(DeviceLog::class);
    }

    public function widget()
    {
        return $this->hasOne(Widget::class);
    }

    /**
     * All share records for this device
     */
    public function shares()
    {
        return $this->hasMany(DeviceShare::class);
    }

    /**
     * Users this device has been shared with
     */
    public function sharedWithUsers()
    {
        return $this->belongsToMany(User::class, 'device_shares', 'device_id', 'shared_with_user_id')
            ->withPivot('permission', 'shared_by_user_id')
            ->withTimestamps();
    }

    /**
     * Check if the device is shared with a specific user
     */
    public function isSharedWith(User $user): bool
    {
        return $this->shares()->where('shared_with_user_id', $user->id)->exists();
    }

    /**
     * Get the permission level for a specific user ('view', 'control', or null if not shared)
     */
    public function getPermissionFor(User $user): ?string
    {
        $share = $this->shares()->where('shared_with_user_id', $user->id)->first();
        return $share?->permission;
    }

    /**
     * Get all widgets (alias for easier access)
     */
    // public function getWidgetsAttribute(): array
    // {
    //     return $this->widget?->getOrderedWidgets() ?? [];
    // }

    /**
     * Initialize widget configuration for new device
     */
    public function initializeWidgets(): Widget
    {
        return Widget::create([
            'device_id' => $this->id,
            'widgets_data' => $this->getDefaultWidgetsData(),
            'grid_config' => [
                'columns' => 12,
                'cellHeight' => 100,
                'margin' => 20
            ],
            'widget_count' => 3
        ]);
    }

    /**
     * Get default widgets for new device
     */
    private function getDefaultWidgetsData(): array
    {
        return [
            'status' => [
                'name' => 'Status',
                'type' => 'text',
                'value' => 'Online',
                'position_x' => 0,
                'position_y' => 0,
                'width' => 4,
                'height' => 2,
                'order' => 0,
                'config' => [
                    'icon' => 'info-circle',
                    'color' => 'success'
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            'lamp_1' => [
                'name' => 'Lampu Teras',
                'type' => 'toggle',
                'value' => '0',
                'position_x' => 4,
                'position_y' => 0,
                'width' => 4,
                'height' => 2,
                'order' => 1,
                'config' => [
                    'icon' => 'lightbulb',
                    'color' => 'primary'
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            'temp' => [
                'name' => 'Temperature',
                'type' => 'gauge',
                'value' => '0',
                'min' => 0,
                'max' => 100,
                'position_x' => 8,
                'position_y' => 0,
                'width' => 4,
                'height' => 3,
                'order' => 2,
                'config' => [
                    'icon' => 'thermometer-half',
                    'unit' => '°C',
                    'color' => 'info'
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ]
        ];
    }



    /**
     * Get the effective log interval for this device.
     *
     * Priority chain:
     *   1. Device-level override  (device.log_interval NOT NULL)
     *   2. User-level setting     (user.log_interval)
     *   3. Default: 0             (log every value change)
     */
    public function getEffectiveLogInterval(): int
    {
        // Device override takes priority
        if (!is_null($this->log_interval)) {
            return (int) $this->log_interval;
        }

        // Fallback to owner's setting (relation may already be loaded)
        return (int) ($this->user?->getLogInterval() ?? 0);
    }

    /**
     * Whether this device has a device-level log interval override.
     */
    public function hasLogIntervalOverride(): bool
    {
        return !is_null($this->log_interval);
    }

    /**
     * Check if LSTM is currently enabled/active
     */
    public function isLstmActive(): bool
    {
        return (bool) $this->lstm_enabled;
    }

    /**
     * Get LSTM Configuration
     */
    public function getLstmConfig()
    {
        return $this->lstm_config ?? [
            'sensor_key' => 'soil_moisture',
            'actuator_key' => 'lamp_1',
            'threshold_low' => 30,
            'threshold_high' => 80
        ];
    }

    // ─────────────────────────────────────────
    // Approval System
    // ─────────────────────────────────────────

    /**
     * Check if this device has been approved by an administrator.
     */
    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }

    /**
     * Approve this device. Records who approved it and when.
     */
    public function approve(User $admin): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
    }

    /**
     * Revoke approval — device will no longer be able to connect or publish data.
     */
    public function revoke(): void
    {
        $this->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Relationship: the admin who approved this device.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
