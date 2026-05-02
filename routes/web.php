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

    // ─── Dashboard ────────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // [SECURITY FIX] Device selection via POST — keeps device_id OUT of browser URL
    Route::post('/dashboard/select', [DashboardController::class, 'selectDevice'])->name('dashboard.select');

    // ─── Device Management (list, create, delete) ─────────────────────────────
    // Index & create have no {device} in URL — safe
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    // Delete device from list — DELETE form, ID not shown in browser URL bar
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    // ─── Profile ──────────────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Schedule Management ──────────────────────────────────────────────────
    Route::resource('schedules', \App\Http\Controllers\ScheduleController::class);

    // ═════════════════════════════════════════════════════════════════════════
    // SESSION-BASED device routes (no {device} in URL)
    // Requires EnsureDeviceSelected middleware → device loaded from session
    // ═════════════════════════════════════════════════════════════════════════
    Route::middleware('device.selected')->group(function () {

        // ── Device Settings & Management (current session device) ─────────────
        Route::get('/device/edit', [DeviceController::class, 'edit'])->name('device.edit');
        Route::put('/device', [DeviceController::class, 'update'])->name('device.update');
        Route::post('/device/regenerate-code', [DeviceController::class, 'regenerateCode'])->name('device.regenerate-code');
        Route::post('/device/lstm/toggle', [\App\Http\Controllers\LstmController::class, 'toggle'])->name('device.lstm.toggle');

        // ── Device Sharing ────────────────────────────────────────────────────
        Route::get('/device/shares', [DeviceShareController::class, 'index'])->name('device.shares.index');
        Route::post('/device/shares', [DeviceShareController::class, 'store'])->name('device.shares.store');
        Route::put('/device/shares/{share}', [DeviceShareController::class, 'update'])->name('device.shares.update');
        Route::delete('/device/shares/{share}', [DeviceShareController::class, 'destroy'])->name('device.shares.destroy');

        // ── Widget Management ─────────────────────────────────────────────────
        // Static routes before {widgetKey} wildcard
        Route::get('/widgets', [WidgetController::class, 'index'])->name('widgets.index');
        Route::post('/widgets/bulk', [WidgetController::class, 'bulkStore'])->name('widgets.bulk-store');
        Route::post('/widgets/positions', [WidgetController::class, 'updatePositions'])->name('widgets.update-positions');
        Route::post('/widgets/bulk-update-keys', [WidgetController::class, 'bulkUpdateKeys'])->name('widgets.bulk-update-keys');
        Route::post('/widgets', [WidgetController::class, 'store'])->name('widgets.store');
        // Wildcard {widgetKey} routes — AFTER static routes
        Route::put('/widgets/{widgetKey}', [WidgetController::class, 'update'])->name('widgets.update');
        Route::delete('/widgets/{widgetKey}', [WidgetController::class, 'destroy'])->name('widgets.destroy');
        Route::post('/widgets/{widgetKey}/value', [WidgetController::class, 'updateValue'])->name('widgets.update-value');

        // ── Data Logs & History ───────────────────────────────────────────────
        // Static routes BEFORE {log} wildcard
        Route::get('/logs', [\App\Http\Controllers\DeviceLogController::class, 'index'])->name('logs.index');
        Route::post('/logs', [\App\Http\Controllers\DeviceLogController::class, 'store'])->name('logs.store');
        Route::get('/logs/export', [\App\Http\Controllers\DeviceLogController::class, 'export'])->name('logs.export');
        Route::delete('/logs/clear', [\App\Http\Controllers\DeviceLogController::class, 'clear'])->name('logs.clear');
        // Wildcard {log} — AFTER static routes
        Route::delete('/logs/{log}', [\App\Http\Controllers\DeviceLogController::class, 'destroy'])->name('logs.destroy');

        // ── History & Telemetry ───────────────────────────────────────────────
        Route::get('/history', [\App\Http\Controllers\DeviceLogController::class, 'history'])->name('devices.history');
        Route::get('/telemetry/{widgetKey}', [\App\Http\Controllers\TelemetryLogController::class, 'aggregated'])->name('telemetry.aggregated');

        // ── SSE Stream (no device in URL) ─────────────────────────────────────
        Route::get('/stream/device', function (\Illuminate\Http\Request $request) {
            $device = $request->attributes->get('device');

            return response()->stream(function () use ($device) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
                set_time_limit(0);

                while (true) {
                    $widgets = \App\Models\Widget::where('device_id', $device->id)->get();

                    foreach ($widgets as $widget) {
                        $data = json_encode([
                            'key'        => $widget->key,
                            'value'      => $widget->value,
                            'type'       => $widget->type,
                            'updated_at' => $widget->updated_at?->toISOString(),
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
        })->name('stream.device');

    }); // end device.selected group

    // ─── Admin Restricted Routes ──────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'stats'])->name('stats');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Hardware Inventory — admin needs to target ANY device, not just session device
        Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
        Route::delete('/devices/{device}', [AdminDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/devices/{device}/toggle-approval', [AdminDeviceController::class, 'toggleApproval'])->name('devices.toggle-approval');
        // Per-device log interval override (static before wildcard)
        Route::post('/devices/bulk-log-interval', [AdminDeviceController::class, 'bulkUpdateLogInterval'])->name('devices.bulk-log-interval');
        Route::patch('/devices/{device}/log-interval', [AdminDeviceController::class, 'updateLogInterval'])->name('devices.update-log-interval');

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
