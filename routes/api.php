<?php

use App\Http\Controllers\Api\DeviceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::prefix('devices')->group(function(){
    // Route::post('/auth/{device_code}',[DeviceApiController::class, 'auth']);

    Route::post('/auth',[DeviceApiController::class, 'auth']);
    Route::post('/{device_code}/verify-login', [DeviceApiController::class, 'verifyLogin']);
    Route::get('/{device_code}/widgets', [DeviceApiController::class, 'getWidgets']);
    Route::post('/{device_code}/widgets', [DeviceApiController::class, 'updateWidgets']);
    Route::post('/{device_code}/widgets/{widget_id}', [DeviceApiController::class, 'updateWidget']);

    Route::post('/{device_code}/heartbeat', [DeviceApiController::class, 'heartbeat']);
    Route::get('/{device_code}/logs', [DeviceApiController::class, 'getLogs']);
    
    Route::get('/{device_code}/status', [DeviceApiController::class, 'getStatus']);

    // Approval status — ESP32/ESP8266 checks this at boot time
    Route::get('/{device_code}/approval-status', [DeviceApiController::class, 'approvalStatus']);

    // Provisioning: Get MQTT Creds
    Route::get('/{device_code}/mqtt-config', [\App\Http\Controllers\Api\DeviceProvisioningController::class, 'getMqttConfig']);
});
