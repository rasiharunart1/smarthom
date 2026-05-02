<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;

/**
 * Ensure the authenticated user has a device selected in their session,
 * and that they have access to it.
 *
 * This middleware eliminates the need to expose device IDs or device codes
 * in browser URLs. All device-context routes use this middleware instead of
 * route model binding on {device}.
 *
 * After this middleware runs, the resolved Device is available via:
 *   $request->attributes->get('device')
 */
class EnsureDeviceSelected
{
    public function handle(Request $request, Closure $next)
    {
        $deviceId = session('selected_device_id');

        if (!$deviceId) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No device selected.'], 400);
            }
            return redirect()->route('dashboard')
                ->with('error', 'Please select a device first.');
        }

        $device = Device::with(['user', 'widget'])->find($deviceId);

        if (!$device) {
            // Device no longer exists — clear stale session
            session()->forget(['selected_device_id', 'selected_device_code']);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Device not found.'], 404);
            }
            return redirect()->route('dashboard')
                ->with('error', 'The selected device no longer exists.');
        }

        // Authorization: owner, admin, or shared user
        $user = auth()->user();
        $isOwner  = $device->user_id === $user->id;
        $isAdmin  = $user->isAdmin();
        $isShared = $device->shares()->where('shared_with_user_id', $user->id)->exists();

        if (!$isOwner && !$isAdmin && !$isShared) {
            session()->forget(['selected_device_id', 'selected_device_code']);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied to this device.'], 403);
            }
            return redirect()->route('dashboard')
                ->with('error', 'You do not have access to the selected device.');
        }

        // Make device available to all downstream controllers
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
