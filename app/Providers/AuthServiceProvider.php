<?php

namespace App\Providers;

use App\Models\Device;
use App\Policies\DevicePolicy;
// use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     *
     */

    protected $policies = [
        Device::class=>DevicePolicy::class,
    ];



    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
