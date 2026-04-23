<?php

use App\Http\Controllers\Api\DeviceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — IoT Device Layer
|--------------------------------------------------------------------------
|
| SECURITY ARCHITECTURE:
|
|   1. Device boots and calls POST /api/devices/auth with its device_code.
|      The server validates approval status and returns a Sanctum API token.
|
|   2. All subsequent requests MUST include:
|         Authorization: Bearer <token>
|
|   3. Rate limiting is applied at all levels to prevent brute-force and DoS.
|
*/

// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC — Device Authentication (no token required, but strictly rate limited)
// [SECURITY FIX C-1 + H-2] Max 10 auth attempts per minute per IP
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('devices')->middleware('throttle:10,1')->group(function () {
    Route::post('/auth', [DeviceApiController::class, 'auth']);

    // Approval status check — ESP32 polls this at boot before auth
    // Also rate limited to prevent enumeration of device codes
    Route::get('/{device_code}/approval-status', [DeviceApiController::class, 'approvalStatus']);
});

// ─────────────────────────────────────────────────────────────────────────────
// PROTECTED — Requires valid Sanctum Bearer token from /auth response
// [SECURITY FIX C-1 + H-2] auth:sanctum + 60 requests/minute rate limit
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('devices')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // Widget read/write
    Route::get('/{device_code}/widgets',              [DeviceApiController::class, 'getWidgets']);
    Route::post('/{device_code}/widgets',             [DeviceApiController::class, 'updateWidgets']);
    Route::post('/{device_code}/widgets/{widget_id}', [DeviceApiController::class, 'updateWidget']);

    // Device telemetry
    Route::post('/{device_code}/heartbeat', [DeviceApiController::class, 'heartbeat']);
    Route::get('/{device_code}/logs',       [DeviceApiController::class, 'getLogs']);
    Route::get('/{device_code}/status',     [DeviceApiController::class, 'getStatus']);

    // [SECURITY FIX C-2] MQTT config — also requires approval check inside controller
    Route::get('/{device_code}/mqtt-config', [\App\Http\Controllers\Api\DeviceProvisioningController::class, 'getMqttConfig']);

    // User verification via device (kept behind auth for safety)
    Route::post('/{device_code}/verify-login', [DeviceApiController::class, 'verifyLogin']);
});

