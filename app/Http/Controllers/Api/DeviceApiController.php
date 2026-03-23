<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Events\WidgetUpdated;
use App\Events\DeviceStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceApiController extends Controller
{
    /**
     * Authenticate device and return MQTT credentials
     *
     * POST /api/devices/auth
     * Body: { "device_code": "DEV001" }
     */
    public function auth(Request $request)
    {
        $request->validate([
            'device_code' => 'required|string'
        ]);

        $device = Device::where('device_code', $request->device_code)
            ->with('user')
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found. Please check your device code.'
            ], 404);
        }

        // Mark device as online
        $device->markAsOnline();

        // 🚀 Broadcast device status
        // broadcast(new DeviceStatusUpdated(
        //     $device->device_code,
        //     'online',
        //     now()->toIso8601String(),
        //     true
        // ));

        // Get MQTT credentials from config (NOT env() — env() returns null when config is cached!)
        $mqttConfig = [
            'host' => config('mqtt.host'),
            'port' => (int) config('mqtt.port', 8883),
            'username' => config('mqtt.username'),
            'password' => config('mqtt.password'),
            'use_tls' => config('mqtt.use_tls', true),
        ];

        // Log authentication
        Log::info('Device authenticated', [
            'device_code' => $device->device_code,
            'device_name' => $device->name,
            'user_id' => $device->user_id,
            'user_name' => $device->user->name,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful',
            'device' => [
                'id' => $device->id,
                'code' => $device->device_code,
                'name' => $device->name,
                'user_id' => $device->user_id,
                'user_name' => $device->user->name,
            ],
            'mqtt' => $mqttConfig,
            'topics' => [
                'subscribe' => "users/{$device->user_id}/devices/{$device->device_code}/control/#",
                'publish_prefix' => "users/{$device->user_id}/devices/{$device->device_code}/sensors/",
                'base' => "users/{$device->user_id}/devices/{$device->device_code}"
            ],
            'api' => [
                'base_url' => url('/api/devices'),
                'widgets_url' => url("/api/devices/{$device->device_code}/widgets"),
                'heartbeat_url' => url("/api/devices/{$device->device_code}/heartbeat"),
            ],
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Device heartbeat
     *
     * POST /api/devices/{device_code}/heartbeat
     */
    public function heartbeat(Request $request, $deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        // Update last seen
        $device->markAsOnline();

        // 🚀 Broadcast device status
        // broadcast(new DeviceStatusUpdated(
        //     $deviceCode,
        //     'online',
        //     now()->toIso8601String(),
        //     true
        // ));

        // Optionally store additional info
        if ($request->has('uptime') || $request->has('free_heap') || $request->has('rssi')) {
            Log::info('Device heartbeat', [
                'device_code' => $deviceCode,
                'uptime' => $request->uptime,
                'free_heap' => $request->free_heap,
                'rssi' => $request->rssi,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'server_time' => now()->toIso8601String(),
            'device_status' => $device->status
        ]);
    }

    /**
     * Get device status
     *
     * GET /api/devices/{device_code}/status
     */
    public function getStatus($deviceCode)
    {
        
        // $device = Device::where('device_code', $deviceCode)->first();
        $device = Device::where('device_code', $deviceCode)
            ->with('user')
            ->first();
    
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'device_code' => $device->device_code,
            'is_online' => $device->isOnline(),
            'status' => $device->status,
            'last_seen_at' => $device->last_seen_at ?    $device->last_seen_at->toIso8601String() : null,
            'subscription_expires_at' =>$device->user->subscription_expires_at,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Get device logs (optional)
     *
     * GET /api/devices/{device_code}/logs
     */
    public function getLogs(Request $request, $deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        $logFile = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);

            $count = 0;
            foreach ($lines as $line) {
                if ($count >= 50) break;

                if (stripos($line, $deviceCode) !== false) {
                    $logs[] = $line;
                    $count++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'device_code' => $deviceCode,
            'logs' => $logs,
            'count' => count($logs),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Get widgets for device (JSON format)
     *
     * GET /api/devices/{device_code}/widgets
     */
    public function getWidgets(Request $request, $deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)
            ->with('widget')
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        // Get widgets from JSON column
        // $widgetsData = $device->widget?->getAllWidgets() ?? [];

        // if ($request->query('lite') == '1') {
        //     $liteWidgets = [];
        //     foreach ($widgetsData as $key => $w) {
        //         $liteWidgets[$key] = [
        //             'name' => $w['name'] ?? '',
        //             'type' => $w['type'] ?? 'toggle',
        //             'value' => $w['value'] ?? '0'
        //             // 'config' => [
        //             //     'unit' => $w['config']['unit'] ?? ''
        //             // ]
        //         ];
        //     }
        //     return response()->json([
        //         'success' => true,
        //         'widgets' => $liteWidgets
        //     ]);
        // }

        // return response()->json([
        //     'success' => true,
        //     'device' => [
        //         'id' => $device->id,
        //         'code' => $device->device_code,
        //         'name' => $device->name,
        //         'user_id' => $device->user_id
        //     ],
        //     'widgets' => $widgetsData,
        //     'widget_count' => count($widgetsData),
        //     'layout_version' => $device->widget?->layout_version ?? 1,
        //     'timestamp' => now()->toIso8601String()
        // ]);
       $widgetsData = $device->widget?->getAllWidgets() ?? [];

        // Default: full
        $widgets = $widgetsData;
        
        // Lite mode
        if ($request->query('lite') == '1') {
            $widgets = [];
        
            foreach ($widgetsData as $key => $w) {
                $widgets[$key] = [
                    'name' => $w['name'] ?? '',
                    'type' => $w['type'] ?? 'toggle',
                    'value' => $w['value'] ?? '0',
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'code' => $device->device_code,
                'name' => $device->name,
                'user_id' => $device->user_id
            ],
            'widgets' => $widgets, // ✅ PENTING
            'widget_count' => count($widgets),
            'layout_version' => $device->widget?->layout_version ?? 1,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Update widget value (with Reverb broadcast)
     *
     * POST /api/devices/{device_code}/widgets/{widget_key}
     * Body: { "value": "..." }
     */
    public function updateWidget(Request $request, $deviceCode, $widgetKey)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        if (!$device->widget) {
            return response()->json([
                'success' => false,
                'message' => 'No widgets configured for this device'
            ], 404);
        }

        $request->validate([
            'value' => 'required'
        ]);

        $widget = $device->widget->getWidget($widgetKey);

        if (!$widget) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found'
            ], 404);
        }

        // ❌ Tidak markAsOnline di sini — ini dipanggil dari user dashboard,
        //    bukan dari device. Status device hanya diupdate via heartbeat MQTT.

        // Update widget value
        $oldValue = $widget['value'];
        $device->widget->updateWidgetValue($widgetKey, $request->value);

        // Get all widgets after update
        $allWidgets = $device->widget->getAllWidgets();

        // 🚀 BROADCAST TO REVERB - Widget Update
        // broadcast(new WidgetUpdated($deviceCode, $allWidgets));

        // // 🚀 BROADCAST TO REVERB - Device Status
        // broadcast(new DeviceStatusUpdated(
        //     $deviceCode,
        //     'online',
        //     now()->toIso8601String(),
        //     true
        // ));

        Log::info("Widget updated via API", [
            'device_code' => $deviceCode,
            'widget_key' => $widgetKey,
            'widget_name' => $widget['name'],
            'old_value' => $oldValue,
            'new_value' => $request->value,
            'source' => 'device_api',
            // 'broadcast' => 'reverb'
        ]);

        // MQTT publish (if available) - Skip if requested (e.g. from Dashboard that already published via WebSockets)
        $mqttPublished = false;
        if (!$request->boolean('skip_mqtt')) {
            try {
                if (class_exists('App\Services\MqttService')) {
                    $mqtt = app(\App\Services\MqttService::class);
                    $mqtt->publishWidgetControl(
                        $device->user_id,
                        $deviceCode,
                        $widgetKey, // ✅ Consistent with browser (use key instead of name)
                        $request->value
                    );
                    $mqttPublished = true;
                }
            } catch (\Exception $e) {
                Log::error("MQTT publish failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'widget' => array_merge($device->widget->getWidget($widgetKey), ['key' => $widgetKey]),
            'mqtt_published' => $mqttPublished,
            // 'reverb_broadcast' => true,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Batch update multiple widgets (with Reverb broadcast)
     *
     * POST /api/devices/{device_code}/widgets
     * Body: { "widgets": [{ "key": "...", "value": "..." }] }
     */
    public function updateWidgets(Request $request, $deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        if (!$device->widget) {
            return response()->json([
                'success' => false,
                'message' => 'No widgets configured'
            ], 404);
        }

        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.key' => 'required|string',
            'widgets.*.value' => 'required'
        ]);

        // ❌ Tidak markAsOnline di sini — ini dipanggil dari user/dashboard,
        //    bukan dari device fisik.

        $updated = [];
        $errors = [];

        foreach ($request->widgets as $widgetData) {
            $widget = $device->widget->getWidget($widgetData['key']);

            if ($widget) {
                $oldValue = $widget['value'];
                $device->widget->updateWidgetValue($widgetData['key'], $widgetData['value']);

                $updated[] = [
                    'key' => $widgetData['key'],
                    'name' => $widget['name'],
                    'old_value' => $oldValue,
                    'new_value' => $widgetData['value']
                ];
            } else {
                $errors[] = [
                    'key' => $widgetData['key'],
                    'error' => 'Widget not found'
                ];
            }
        }

        // Get all widgets after update
        $allWidgets = $device->widget->getAllWidgets();

        // 🚀 BROADCAST TO REVERB
        // broadcast(new WidgetUpdated($deviceCode, $allWidgets));
        // broadcast(new DeviceStatusUpdated(
        //     $deviceCode,
        //     'online',
        //     now()->toIso8601String(),
        //     true
        // ));

        Log::info("Batch widget update via API", [
            'device_code' => $deviceCode,
            'updated_count' => count($updated),
            'error_count' => count($errors),
            'source' => 'device_api_batch',
            // 'broadcast' => 'reverb'
        ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'errors' => $errors,
            // 'reverb_broadcast' => true,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    public function verifyLogin(Request $request, $deviceCode)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $device = Device::where('device_code', $deviceCode)->first();
        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        if (\Illuminate\Support\Facades\Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($device->user_id === $user->id) {
                return response()->json(['success' => true]);
            } else {
                return response()->json(['success' => false, 'message' => 'Device belongs to another user'], 403);
            }
        }

        return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
    }
}