<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DeviceController extends Controller
{
    public function index()
    {
        if (Auth::user()->isAdmin()) {
            $devices = Device::with(['widgets', 'user'])->latest()->get()
                ->map(fn($d) => $d->setAttribute('_share_source', 'owned'));
        } else {
            // Owned devices
            $ownedDevices = Device::where('user_id', Auth::id())
                ->with(['widgets', 'user'])
                ->latest()
                ->get()
                ->map(fn($d) => $d->setAttribute('_share_source', 'owned'));

            // Shared devices
            $sharedDevices = Auth::user()->sharedDevices()
                ->with(['widgets', 'user'])
                ->get()
                ->map(function($d) {
                    $d->setAttribute('_share_source', 'shared');
                    $d->setAttribute('_share_permission', $d->pivot->permission);
                    $d->setAttribute('_shared_by', \App\Models\User::find($d->pivot->shared_by_user_id));
                    return $d;
                });

            $devices = $ownedDevices->concat($sharedDevices);
        }

        return view('devices.index', compact('devices'));
    }

    public function create(Request $request)
    {
        $targetUserId = $request->query('user_id');
        $targetUser   = $targetUserId ? \App\Models\User::find($targetUserId) : null;

        return view('devices.create', compact('targetUser'));
    }

    public function store(Request $request)
    {
        $rules = ['name' => 'required|string|max:255'];

        if (Auth::user()->isAdmin()) {
            $rules['user_id'] = 'sometimes|exists:users,id';
        }

        $validated = $request->validate($rules);

        $userId = Auth::id();
        if (Auth::user()->isAdmin() && isset($validated['user_id'])) {
            $userId = $validated['user_id'];
        }

        $user = \App\Models\User::find($userId);

        if (!$user->canAddDevice()) {
            return back()->with('error', "Provisioning failed: This identity has reached the hardware limit of {$user->getLimit('max_devices')} nodes.");
        }

        $device = Device::create([
            'user_id' => $userId,
            'name'    => $validated['name'],
            'status'  => 'offline',
        ]);

        // Create empty widget JSON storage for this device
        $device->widget()->create([
            'widgets_data' => [],
            'widget_count' => 0,
            'layout_version' => 1,
        ]);

        return redirect()
            ->route('dashboard', ['device_id' => $device->id])
            ->with('success', 'Device created successfully! Device Code: ' . $device->device_code);
    }

    /**
     * Edit current session device (no {device} in URL).
     */
    public function edit(Request $request)
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');
        Gate::authorize('update', $device);

        return view('devices.edit', compact('device'));
    }

    /**
     * Update current session device (no {device} in URL).
     */
    public function update(Request $request)
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');
        Gate::authorize('update', $device);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $device->update($validated);

        return redirect()
            ->route('device.edit')
            ->with('success', 'Device updated successfully.');
    }

    /**
     * Regenerate device code for current session device.
     */
    public function regenerateCode(Request $request)
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');
        Gate::authorize('update', $device);

        $device->update(['device_code' => Device::generateDeviceCode()]);

        return back()->with('success', 'Device code regenerated: ' . $device->device_code);
    }

    /**
     * Destroy a specific device (from device list page).
     * DELETE /devices/{device} — not shown in browser URL bar.
     */
    public function destroy(Device $device)
    {
        Gate::authorize('delete', $device);

        if ($device->widget) {
            $device->widget->delete();
        }

        // Clear session if this was the selected device
        if (session('selected_device_id') === $device->id) {
            session()->forget(['selected_device_id', 'selected_device_code']);
        }

        $device->delete();

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device deleted successfully.');
    }
}