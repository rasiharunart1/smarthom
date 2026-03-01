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
            $devices = Device::with('widgets')->latest()->get();
        } else {
            $devices = Device::where('user_id', Auth::id())
                ->with('widgets')
                ->latest()
                ->get();
        }

        return view('devices.index', compact('devices'));
    }

    public function create(Request $request)
    {
        $targetUserId = $request->query('user_id');
        $targetUser = $targetUserId ? \App\Models\User::find($targetUserId) : null;
        
        return view('devices.create', compact('targetUser'));
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        if (Auth::user()->isAdmin()) {
            $rules['user_id'] = 'sometimes|exists:users,id';
        }

        $validated = $request->validate($rules);

        $userId = Auth::id();
        if (Auth::user()->isAdmin() && isset($validated['user_id'])) {
            $userId = $validated['user_id'];
        }

        $user = \App\Models\User::find($userId);

        // Check Device Limit
        if (!$user->canAddDevice()) {
            return back()->with('error', "Provisioning failed: This identity has reached the hardware limit of {$user->getLimit('max_devices')} nodes.");
        }

        $device = Device::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'status' => 'offline',
        ]);

        // Create empty widget JSON storage for this device
        $device->widget()->create([
            'widgets_data' => [],
            'widget_count' => 0,
            'layout_version' => 1,
        ]);

        return redirect()
            ->route('dashboard', ['device_id' => $device->id])
            ->with('success', 'Device created successfully!  Device Code: ' .$device->device_code);
    }

    private function resolveDevice($id)
    {
        if (is_numeric($id)) {
            return Device::findOrFail($id);
        }
        return Device::where('device_code', $id)->firstOrFail();
    }

    public function show($id)
    {
        $device = $this->resolveDevice($id);

        Gate::authorize('view', $device);

        return view('devices.show', compact('device'));
    }

    public function edit($id)
    {
        // Eager load widget for edit
        if (is_numeric($id)) {
             $device = Device::with('widget')->findOrFail($id);
        } else {
             $device = Device::with('widget')->where('device_code', $id)->firstOrFail();
        }

        Gate::authorize('update', $device);

        return view('devices.edit', compact('device'));
    }

    public function update(Request $request, $id)
    {
        $device = $this->resolveDevice($id);

        Gate::authorize('update', $device);

        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $device->update($validated);

        return redirect()
            ->route('devices.edit', $device->device_code)
            ->with('success', 'Device updated successfully');
    }

    public function destroy($id)
    {
        $device = $this->resolveDevice($id);

        Gate::authorize('delete', $device);

        // Delete widget data
        if ($device->widget) {
            $device->widget->delete();
        }

        $device->delete();

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device deleted successfully');
    }

    public function regenerateDeviceCode($id)
    {
        $device = $this->resolveDevice($id);

        Gate::authorize('update', $device);

        $device->update([
            'device_code' => Device::generateDeviceCode()
        ]);

        return back()->with('success', 'Device code regenerated successfully: ' .$device->device_code);
    }
}