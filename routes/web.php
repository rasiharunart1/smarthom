<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceShareController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// [SECURITY FIX C-1] MQTT credentials served via AJAX — never embedded in HTML source
Route::middleware('auth')->get('/mqtt/credentials', [\App\Http\Controllers\MqttTokenController::class, 'credentials'])
    ->name('mqtt.credentials');

Route::middleware('auth')->group(function () {
    // User Routes
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // [SECURITY FIX] Device selection via POST — keeps device_id OUT of browser URL (no ?device_id=xxx)
    Route::post('/dashboard/select', [DashboardController::class, 'selectDevice'])->name('dashboard.select');
    Route::resource('devices', DeviceController::class);
    Route::post('/devices/{device}/regenerate-code', [DeviceController::class, 'regenerateCode'])->name('devices.regenerate-code');

    // Device Sharing Routes
    Route::get('/devices/{device}/shares', [DeviceShareController::class, 'index'])->name('devices.shares.index');
    Route::post('/devices/{device}/shares', [DeviceShareController::class, 'store'])->name('devices.shares.store');
    Route::put('/devices/{device}/shares/{share}', [DeviceShareController::class, 'update'])->name('devices.shares.update');
    Route::delete('/devices/{device}/shares/{share}', [DeviceShareController::class, 'destroy'])->name('devices.shares.destroy');
    
    // LSTM / AI Routes
    Route::post('/devices/{device}/lstm/toggle', [\App\Http\Controllers\LstmController::class, 'toggle'])->name('devices.lstm.toggle');

    // Widget Routes
    Route::get('/devices/{device}/widgets', [WidgetController::class, 'index'])->name('widgets.index');
    Route::post('/devices/{device}/widgets', [WidgetController::class, 'store'])->name('widgets.store');
    Route::post('/devices/{device}/widgets/bulk', [WidgetController::class, 'bulkStore'])->name('widgets.bulk-store');
    Route::put('/devices/{device}/widgets/{widgetKey}', [WidgetController::class, 'update'])->name('widgets.update');
    Route::delete('/devices/{device}/widgets/{widgetKey}', [WidgetController::class, 'destroy'])->name('widgets.destroy');
    Route::post('/devices/{device}/widgets/positions', [WidgetController::class, 'updatePositions'])->name('widgets.update-positions');
    Route::post('/devices/{device}/widgets/bulk-update-keys', [WidgetController::class, 'bulkUpdateKeys'])->name('widgets.bulk-update-keys');
    Route::post('/devices/{device}/widgets/{widgetKey}/value', [WidgetController::class, 'updateValue'])->name('widgets.update-value');

    // Data Logs & History — static routes MUST be declared before {log} wildcard
    Route::get('/devices/{device}/logs', [App\Http\Controllers\DeviceLogController::class, 'index'])->name('logs.index');
    Route::post('/devices/{device}/logs', [App\Http\Controllers\DeviceLogController::class, 'store'])->name('logs.store');
    Route::get('/devices/{device}/logs/run-export', [App\Http\Controllers\DeviceLogController::class, 'export'])->name('logs.export');
    Route::delete('/devices/{device}/logs/clear', [App\Http\Controllers\DeviceLogController::class, 'clear'])->name('logs.clear');
    Route::delete('/devices/{device}/logs/{log}', [App\Http\Controllers\DeviceLogController::class, 'destroy'])->name('logs.destroy');
    Route::get('/devices/{device}/history', [App\Http\Controllers\DeviceLogController::class, 'history'])->name('devices.history');

    // Aggregated Telemetry Logs (Gold/Enterprise only) — OHLC chart endpoint
    Route::get('/devices/{device}/telemetry/{widgetKey}', [App\Http\Controllers\TelemetryLogController::class, 'aggregated'])->name('telemetry.aggregated');

    // Schedule Management
    Route::resource('schedules', \App\Http\Controllers\ScheduleController::class);

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Streaming Logic — uses {device} (numeric ID) instead of device_code in URL
    Route::get('/stream/device/{device}', function (\App\Models\Device $device) {
        // [SECURITY FIX H-3] Verify the authenticated user owns or has access to this device
        $user = auth()->user();
        $isOwner   = $device->user_id === $user->id;
        $isAdmin   = $user->isAdmin();
        $isShared  = $device->shares()->where('shared_with_user_id', $user->id)->exists();

        if (!$isOwner && !$isAdmin && !$isShared) {
            abort(403, 'You do not have access to stream this device.');
        }

        $deviceCode = $device->device_code;

        return response()->stream(function () use ($deviceCode) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            set_time_limit(0);

            while (true) {
                $widgets = \App\Models\Widget::whereHas('device', function($q) use ($deviceCode) {
                    $q->where('device_code', $deviceCode);
                })->get();

                foreach ($widgets as $widget) {
                    $data = json_encode([
                        'key'        => $widget->key,
                        'value'      => $widget->value,
                        'type'       => $widget->type,
                        'updated_at' => $widget->updated_at->toISOString(),
                    ]);
                    echo "data: {$data}\n\n";
                }

                ob_flush();
                flush();
                sleep(1);
                if (connection_aborted()) break;
            }
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    });

    // Admin Restricted Routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'stats'])->name('stats');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Hardware Inventory
        Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
        Route::delete('/devices/{device}', [AdminDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/devices/{device}/toggle-approval', [AdminDeviceController::class, 'toggleApproval'])->name('devices.toggle-approval');
        // Per-device log interval override
        Route::patch('/devices/{device}/log-interval', [AdminDeviceController::class, 'updateLogInterval'])->name('devices.update-log-interval');
        // Bulk-set log interval for all devices of one user
        Route::post('/devices/bulk-log-interval', [AdminDeviceController::class, 'bulkUpdateLogInterval'])->name('devices.bulk-log-interval');

        // Subscription Plan Governance
        Route::resource('plans', AdminPlanController::class);

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Admin\AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [\App\Http\Controllers\Admin\AdminSettingController::class, 'update'])->name('settings.update');

        // Protocols Management
        Route::resource('protocols', \App\Http\Controllers\Admin\AdminProtocolController::class);
    });
});

require __DIR__.'/auth.php';
