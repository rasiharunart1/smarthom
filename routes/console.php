<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:process-schedules')->everyMinute();

// Data Aggregation Schedule
Schedule::command('logs:aggregate 5min')->everyFiveMinutes();
Schedule::command('logs:aggregate hourly')->hourly();
Schedule::command('logs:aggregate daily')->daily();
