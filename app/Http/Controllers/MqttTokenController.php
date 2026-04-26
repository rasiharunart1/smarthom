<?php

namespace App\Http\Controllers;

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
     * Return MQTT WebSocket connection credentials for the authenticated user.
     *
     * GET /mqtt/credentials
     * Requires: auth middleware (web session)
     */
    public function credentials(Request $request)
    {
        return response()->json([
            'host'     => config('mqtt.host'),
            'port'     => (int) config('mqtt.websocket_port', 8884),
            'path'     => config('mqtt.websocket_path', '/mqtt'),
            'protocol' => config('mqtt.protocol', 'wss'),
            'username' => config('mqtt.username'),
            'password' => config('mqtt.password'),
        ]);
    }
}
