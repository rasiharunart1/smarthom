<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceProvisioningController extends Controller
{
    /**
     * Get MQTT configuration for a specific device.
     *
     * @param string $deviceCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMqttConfig($deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
                'message' => 'The provided device code is invalid or not registered.'
            ], 404);
        }

        // In a real production environment, you might want to issue unique credentials per device.
        // For now, we return the central broker credentials but this architecture allows
        // for easy swapping to per-device credentials in the future.
        
        return response()->json([
            'success' => true,
            'device_name' => $device->name,
            'mqtt' => [
                'host' => env('MQTT_HOST'),
                'port' => (int) env('MQTT_PORT', 1883), // Devices usually connect to non-TLS 1883 or TLS 8883
                'username' => env('MQTT_USERNAME'),
                'password' => env('MQTT_PASSWORD'),
                'use_tls' => (bool) env('MQTT_USE_TLS', false),
                'client_id' => 'esp32_' . $deviceCode . '_' . uniqid(),
            ],
            'topics' => [
                'control' => "users/{$device->user_id}/devices/{$deviceCode}/control/#",
                'status' => "users/{$device->user_id}/devices/{$deviceCode}/status",
                'sensors' => "users/{$device->user_id}/devices/{$deviceCode}/sensors/",
            ]
        ]);
    }
}
