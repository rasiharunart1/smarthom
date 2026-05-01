<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDeviceController extends Controller
{
    /**
     * List all devices with optional filter by user/owner.
     */
    public function index(Request $request)
    {
        $query = Device::with(['user', 'approvedBy'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $devices      = $query->paginate(15)->withQueryString();
        $filteredUser = $request->filled('user_id') ? User::find($request->user_id) : null;

        // Only users who own at least one device — for the filter dropdown
        $users = User::has('devices')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.devices.index', compact('devices', 'filteredUser', 'users'));
    }

    /**
     * Toggle approve / revoke a device.
     */
    public function toggleApproval(Device $device)
    {
        if ($device->isApproved()) {
            $device->revoke();
            $message = "Hardware node [{$device->device_code}] has been revoked. It will no longer be able to connect.";
            $status  = 'revoked';
        } else {
            $device->approve(auth()->user());
            $message = "Hardware node [{$device->device_code}] is now approved and ready to operate.";
            $status  = 'approved';
        }

        return back()->with('success', $message)->with('approval_status', $status);
    }

    /**
     * Update log interval for a single device.
     * PATCH /admin/devices/{device}/log-interval
     */
    public function updateLogInterval(Request $request, Device $device)
    {
        $request->validate([
            // null = reset to user default; integer = device override
            'log_interval' => 'nullable|integer|min:0|max:3600',
        ]);

        $raw = $request->input('log_interval');

        // Empty string submitted from form reset → treat as null (inherit user)
        $value = ($raw === '' || $raw === null) ? null : (int) $raw;

        $device->update(['log_interval' => $value]);

        $label = is_null($value)
            ? 'Reset to user default'
            : ($value === 0 ? 'Every change' : "{$value}s");

        return back()->with('success', "Log interval for [{$device->name}] set to: {$label}.");
    }

    /**
     * Bulk-update log interval for ALL devices of a specific user.
     * POST /admin/devices/bulk-log-interval
     */
    public function bulkUpdateLogInterval(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'log_interval' => 'nullable|integer|min:0|max:3600',
        ]);

        $raw   = $request->input('log_interval');
        $value = ($raw === '' || $raw === null) ? null : (int) $raw;
        $user  = User::findOrFail($request->user_id);

        $count = Device::where('user_id', $user->id)
                       ->update(['log_interval' => $value]);

        $label = is_null($value)
            ? 'Reset to user default'
            : ($value === 0 ? 'Every change' : "{$value}s");

        return back()->with('success', "Bulk updated {$count} device(s) for [{$user->name}] → Log interval: {$label}.");
    }

    /**
     * Delete a device entirely.
     */
    public function destroy(Device $device)
    {
        $device->delete();
        return redirect()->route('admin.devices.index')
                         ->with('success', 'Hardware unit and all associated modules removed from global inventory.');
    }
}
