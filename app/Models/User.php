<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // 'admin' or 'user'
        'subscription_plan', // 'free', 'pro', 'enterprise'
        'subscription_expires_at',
        'lstm_allowed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
            'lstm_allowed' => 'boolean',
        ];
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has an active subscription.
     */
    public function isSubscribed(): bool
    {
        if ($this->isAdmin()) return true;
        if (!$this->subscription_expires_at) return false;
        return $this->subscription_expires_at->isFuture();
    }
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Get current plan configuration from DB
     */
    public function getPlanConfig()
    {
        $slug = $this->subscription_plan ?: 'free';
        return \App\Models\Plan::where('slug', $slug)->first() ?: (object)[
            'name' => 'Legacy Free',
            'max_devices' => 2,
            'max_widgets_per_device' => 5,
            'features' => []
        ];
    }

    /**
     * Get specific limit for user
     */
    public function getLimit(string $key)
    {
        if ($this->isAdmin()) return 999999;
        
        $plan = $this->getPlanConfig();
        return $plan->$key ?? 0;
    }

    /**
     * Check if user can add more devices
     */
    public function canAddDevice(): bool
    {
        if ($this->isAdmin()) return true;

        $max = $this->getLimit('max_devices');
        return $this->devices()->count() < $max;
    }

    /**
     * Check if user can add more widgets to a specific device
     */
    public function canAddWidget(Device $device): bool
    {
        if ($this->isAdmin()) return true;

        $max = $this->getLimit('max_widgets_per_device');
        $currentCount = $device->widget ? count($device->widget->widgets_data ?? []) : 0;
        
        return $currentCount < $max;
    }

    /**
     * Check if user is allowed to use LSTM features (Database driven)
     */
    public function canUseLstm(): bool
    {
        // Now fully database driven (Admin controls this)
        return (bool) $this->lstm_allowed;
    }
}