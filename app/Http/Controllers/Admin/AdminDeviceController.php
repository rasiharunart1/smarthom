<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class AdminDeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::with(['user', 'approvedBy'])->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $devices = $query->paginate(15);
        $filteredUser = $request->has('user_id') ? \App\Models\User::find($request->user_id) : null;

        return view('admin.devices.index', compact('devices', 'filteredUser'));
    }

    /**
     * Toggle approve / revoke a device.
     * Admin only. Called via POST /admin/devices/{device}/toggle-approval
     */
    public function toggleApproval(Device $device)
    {
        if ($device->isApproved()) {
            $device->revoke();
            $status  = 'revoked';
            $message = "Hardware node [{$device->device_code}] has been revoked. It will no longer be able to connect.";
        } else {
            $device->approve(auth()->user());
            $status  = 'approved';
            $message = "Hardware node [{$device->device_code}] is now approved and ready to operate.";
        }

        return back()->with('success', $message)->with('approval_status', $status);
    }

    public function destroy(Device $device)
    {
        $device->delete();
        return redirect()->route('admin.devices.index')->with('success', 'Hardware unit and all associated modules removed from global inventory.');
    }
}
