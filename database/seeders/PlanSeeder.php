<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Free Tier',
            'max_devices' => 2,
            'max_widgets_per_device' => 5,
            'features' => ['history' => '24h', 'api_access' => false]
        ]);

        Plan::updateOrCreate(['slug' => 'pro'], [
            'name' => 'Professional',
            'max_devices' => 10,
            'max_widgets_per_device' => 20,
            'features' => ['history' => '30d', 'api_access' => true]
        ]);

        Plan::updateOrCreate(['slug' => 'enterprise'], [
            'name' => 'Enterprise',
            'max_devices' => 100,
            'max_widgets_per_device' => 50,
            'features' => ['history' => '365d', 'api_access' => true]
        ]);
    }
}
