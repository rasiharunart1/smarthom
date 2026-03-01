<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class AdminDeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::with('user')->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $devices = $query->paginate(15);
        $filteredUser = $request->has('user_id') ? \App\Models\User::find($request->user_id) : null;

        return view('admin.devices.index', compact('devices', 'filteredUser'));
    }

    public function destroy(Device $device)
    {
        $device->delete();
        return redirect()->route('admin.devices.index')->with('success', 'Hardware unit and all associated modules removed from global inventory.');
    }
}
