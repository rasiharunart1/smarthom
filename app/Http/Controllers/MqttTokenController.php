<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

/**
 * [SECURITY FIX C-1] Serve MQTT credentials via authenticated AJAX endpoint
 * instead of embedding them directly in HTML source.
 *
 * This prevents any user from extracting shared MQTT credentials via DevTools.
 */
class MqttTokenController extends Controller
{
    /**
     * Return MQTT WebSocket connection credentials for the authenticated user,
     * plus the device context (owner_user_id, device_code) needed to build
     * MQTT topics — served securely via AJAX, never rendered into HTML/URL.
     *
     * GET /mqtt/credentials
     * Requires: auth middleware (web session)
     */
    public function credentials(Request $request)
    {
        $user = auth()->user();

        // Resolve selected device from session (set by DashboardController)
        $deviceId   = $request->session()->get('selected_device_id');
        $deviceCode = null;
        $ownerUserId = $user->id; // default: current user

        if ($deviceId) {
            // Verify the session device belongs to (or is shared with) this user
            $device = Device::find($deviceId);

            if ($device) {
                $isOwner  = $device->user_id === $user->id;
                $isAdmin  = $user->isAdmin();
                $isShared = $device->isSharedWith($user);

                if ($isOwner || $isAdmin || $isShared) {
                    $deviceCode  = $device->device_code;
                    $ownerUserId = $device->user_id; // topic owner = device owner
                }
            }
        }

        return response()->json([
            'host'          => config('mqtt.host'),
            'port'          => (int) config('mqtt.websocket_port', 8884),
            'path'          => config('mqtt.websocket_path', '/mqtt'),
            'protocol'      => config('mqtt.protocol', 'wss'),
            'username'      => config('mqtt.username'),
            'password'      => config('mqtt.password'),
            // Device context for MQTT topic construction — safe here (auth-gated endpoint)
            'device_code'   => $deviceCode,
            'owner_user_id' => $ownerUserId,
        ]);
    }
}
