<?php

namespace App\Http\Controllers;

use App\Mail\DeviceSharedMail;
use App\Models\Device;
use App\Models\DeviceShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class DeviceShareController extends Controller
{
    /**
     * Show the share management page for a device.
     */
    public function index($device)
    {
        $device = $this->resolveDevice($device);
        Gate::authorize('share', $device);

        $shares = $device->shares()->with('sharedWith')->get();

        return view('devices.shares', compact('device', 'shares'));
    }

    /**
     * Share a device with another user.
     */
    public function store(Request $request, $device)
    {
        $device = $this->resolveDevice($device);
        Gate::authorize('share', $device);

        $validated = $request->validate([
            'email'      => 'required|email|exists:users,email',
            'permission' => 'required|in:view,control',
        ]);

        // Find the target user
        $targetUser = User::where('email', $validated['email'])->firstOrFail();

        // Cannot share with yourself
        if ($targetUser->id === Auth::id()) {
            return back()->with('error', 'Kamu tidak bisa berbagi device dengan dirimu sendiri.');
        }

        // Cannot share with super admin
        if ($targetUser->isAdmin()) {
            return back()->with('error', 'Admin sudah memiliki akses ke semua device.');
        }

        // Check if already shared
        $existing = DeviceShare::where('device_id', $device->id)
            ->where('shared_with_user_id', $targetUser->id)
            ->first();

        if ($existing) {
            return back()->with('error', "Device ini sudah di-share ke {$targetUser->name}. Gunakan tombol Edit untuk mengubah permission.");
        }

        DeviceShare::create([
            'device_id'          => $device->id,
            'shared_by_user_id'  => Auth::id(),
            'shared_with_user_id'=> $targetUser->id,
            'permission'         => $validated['permission'],
        ]);

        // Send email notification to the shared user
        try {
            Mail::to($targetUser->email)->send(new DeviceSharedMail($device, Auth::user(), $targetUser, $validated['permission']));
        } catch (\Exception $e) {
            // Don't fail the action if mail fails, just log it
            \Log::warning('DeviceShare mail failed: ' . $e->getMessage());
        }

        return back()->with('success', "Device berhasil di-share ke {$targetUser->name} dengan akses {$validated['permission']}.");
    }

    /**
     * Update permission level for an existing share.
     */
    public function update(Request $request, $device, DeviceShare $share)
    {
        $device = $this->resolveDevice($device);
        Gate::authorize('share', $device);

        // Make sure the share belongs to this device
        if ($share->device_id !== $device->id) {
            abort(403);
        }

        $validated = $request->validate([
            'permission' => 'required|in:view,control',
        ]);

        $share->update(['permission' => $validated['permission']]);

        return back()->with('success', "Permission untuk {$share->sharedWith->name} berhasil diubah ke {$validated['permission']}.");
    }

    /**
     * Revoke access for a shared user.
     */
    public function destroy($device, DeviceShare $share)
    {
        $device = $this->resolveDevice($device);
        Gate::authorize('share', $device);

        if ($share->device_id !== $device->id) {
            abort(403);
        }

        $userName = $share->sharedWith->name;
        $share->delete();

        return back()->with('success', "Akses {$userName} ke device ini telah dicabut.");
    }

    private function resolveDevice($id): Device
    {
        if (is_numeric($id)) {
            return Device::findOrFail($id);
        }
        return Device::where('device_code', $id)->firstOrFail();
    }
}
