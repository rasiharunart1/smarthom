<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceProvisioningController extends Controller
{
    /**
     * Get MQTT configuration for a specific device.
     *
     * [SECURITY FIX C-2]
     * - Requires device to be approved before returning MQTT credentials
     * - Uses config() instead of env() so it works when config is cached
     * - Requires a valid Sanctum Bearer token (enforced by auth:sanctum middleware in api.php)
     *
     * @param  string  $deviceCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMqttConfig($deviceCode)
    {
        $device = Device::where('device_code', $deviceCode)->first();

        if (!$device) {
            return response()->json([
                'success'    => false,
                'error_code' => 'DEVICE_NOT_FOUND',
                'message'    => 'The provided device code is invalid or not registered.',
            ], 404);
        }

        // [SECURITY FIX C-2] Block unapproved devices from obtaining MQTT credentials
        if (!$device->isApproved()) {
            Log::warning('[Security] Unapproved device requested MQTT config', [
                'device_code' => $deviceCode,
                'ip'          => request()->ip(),
            ]);

            return response()->json([
                'success'    => false,
                'error_code' => 'DEVICE_NOT_APPROVED',
                'message'    => 'Device is pending admin approval. MQTT credentials will not be issued until approved.',
            ], 403);
        }

        // [SECURITY FIX C-2] Use config() not env() — env() returns null when config is cached
        return response()->json([
            'success'     => true,
            'device_name' => $device->name,
            'mqtt'        => [
                'host'      => config('mqtt.host'),
                'port'      => (int) config('mqtt.port', 8883),
                'username'  => config('mqtt.username'),
                'password'  => config('mqtt.password'),
                'use_tls'   => (bool) config('mqtt.use_tls', true),
                'client_id' => 'esp32_' . $deviceCode . '_' . bin2hex(random_bytes(4)),
            ],
            'topics' => [
                'control' => "users/{$device->user_id}/devices/{$deviceCode}/control/#",
                'status'  => "users/{$device->user_id}/devices/{$deviceCode}/status",
                'sensors' => "users/{$device->user_id}/devices/{$deviceCode}/sensors/",
            ],
        ]);
    }
}
