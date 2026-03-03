<?php

namespace App\Providers;

use App\Services\MqttService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MqttService::class, function ($app) {
            return new MqttService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix for older MySQL versions (string length)
        Schema::defaultStringLength(191);

        // ✅ Force HTTPS for all generated URLs
        // if (str_contains(request()->getHost(), 'mdpower.io') || $this->app->environment('production')) {
        //     URL::forceScheme('https');
        // }
    }
}