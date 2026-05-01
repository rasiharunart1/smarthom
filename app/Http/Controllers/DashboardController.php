<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Return all device IDs the current user can access (owned + shared).
     */
    private function getAccessibleDeviceIds(): array
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return []; // empty = no filter needed (admin sees all)
        }

        $ownedIds  = Device::where('user_id', $user->id)->pluck('id')->toArray();
        $sharedIds = $user->sharedDevices()->pluck('devices.id')->toArray();

        return array_unique(array_merge($ownedIds, $sharedIds));
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Check subscription expiration
        $showExpirationPopup = !$user->isAdmin() &&
                               $user->subscription_expires_at &&
                               $user->subscription_expires_at->isPast();

        $adminWhatsapp = Setting::where('key', 'admin_whatsapp')->value('value');

        // Get devices: Admins see all, Users see owned + shared
        if ($user->isAdmin()) {
            $devices = Device::orderBy('name')->get();
        } else {
            $accessibleIds = $this->getAccessibleDeviceIds();
            $devices = Device::whereIn('id', $accessibleIds)
                ->orderBy('name')
                ->get();
        }

        $selectedDevice = null;

        if ($devices->count() > 0) {
            // [SECURITY FIX] device_id is ONLY read from session, never from GET query string.
            // Use POST /dashboard/select to switch devices (keeps device_id out of the URL).
            $selectedDeviceId = $request->session()->get('selected_device_id');

            if ($selectedDeviceId) {
                if ($user->isAdmin()) {
                    $selectedDevice = Device::where('id', $selectedDeviceId)
                        ->with('widget')->first();
                } else {
                    $accessibleIds = $this->getAccessibleDeviceIds();
                    $selectedDevice = Device::where('id', $selectedDeviceId)
                        ->whereIn('id', $accessibleIds)
                        ->with('widget')->first();
                }

                if (!$selectedDevice && $devices->count() > 0) {
                    $request->session()->forget('selected_device_id');
                    $selectedDevice = $devices->first();
                    $selectedDevice->load('widget');
                    $request->session()->put('selected_device_id', $selectedDevice->id);
                }
            } else {
                $selectedDevice = $devices->first();
                $selectedDevice->load('widget');
                $request->session()->put('selected_device_id', $selectedDevice->id);
            }

            if ($selectedDevice) {
                $request->session()->put('selected_device_id', $selectedDevice->id);
                $request->session()->put('selected_device_code', $selectedDevice->device_code);
            }
        }

        $widgets = $this->getWidgetsForDashboard($selectedDevice);

        return view('dashboard', compact('devices', 'selectedDevice', 'widgets', 'showExpirationPopup', 'adminWhatsapp'));
    }

    /**
     * Get widgets for dashboard from new JSON structure
     */
    private function getWidgetsForDashboard($device)
    {
        if (!$device || !$device->widget) {
            return collect([]);
        }

        $widgetsData = $device->widget->widgets_data ?? [];

        $widgets = collect($widgetsData)->map(function ($widget, $key) use ($device) {
            $widgetData = [
                'key'        => $key,
                'name'       => $widget['name'] ?? 'Unnamed',
                'type'       => $widget['type'] ?? 'text',
                'value'      => $widget['value'] ?? '',
                'min'        => $widget['min'] ?? 0,
                'max'        => $widget['max'] ?? 100,
                'position_x' => $widget['position_x'] ?? 0,
                'position_y' => $widget['position_y'] ?? 0,
                'width'      => $widget['width'] ?? 4,
                'height'     => $widget['height'] ?? 2,
                'order'      => $widget['order'] ?? 0,
                'config'     => $widget['config'] ?? [],
                'created_at' => $widget['created_at'] ?? null,
                'updated_at' => $widget['updated_at'] ?? null,
            ];

            $widgetData['mqtt_topic'] = "users/{$device->user_id}/devices/{$device->device_code}/sensors/{$key}";
            $unit = $widgetData['config']['unit'] ?? '';
            $widgetData['unit'] = $unit;
            $widgetData['icon'] = $widgetData['config']['icon'] ?? $this->getDefaultIcon($widgetData['type']);

            return (object) $widgetData;
        })->sortBy('order')->values();

        return $widgets;
    }

    /**
     * Get default icon based on widget type
     */
    private function getDefaultIcon($type)
    {
        return match($type) {
            'toggle' => 'lightbulb',
            'slider' => 'sliders-h',
            'gauge'  => 'tachometer-alt',
            'text'   => 'info-circle',
            'chart'  => 'chart-line',
            default  => 'cube'
        };
    }

    /**
     * [SECURITY FIX] Change selected device via POST — device_id stays in request body,
     * never appears in the browser URL bar.
     */
    public function selectDevice(Request $request)
    {
        $request->validate(['device_id' => 'required|integer']);

        $deviceId = $request->input('device_id');
        $user     = Auth::user();

        if ($user->isAdmin()) {
            $device = Device::where('id', $deviceId)->first();
        } else {
            $accessibleIds = $this->getAccessibleDeviceIds();
            $device = Device::where('id', $deviceId)
                ->whereIn('id', $accessibleIds)
                ->first();
        }

        if ($device) {
            $request->session()->put('selected_device_id', $device->id);
            $request->session()->put('selected_device_code', $device->device_code);
            return redirect()->route('dashboard')->with('success', 'Device switched successfully');
        }

        return redirect()->route('dashboard')->with('error', 'Device not found');
    }

    /**
     * Refresh widgets data via AJAX
     */
    public function refreshWidgets(Request $request)
    {
        $deviceId = $request->session()->get('selected_device_id');
        $user     = Auth::user();

        if (!$deviceId) {
            return response()->json(['success' => false, 'message' => 'No device selected'], 400);
        }

        if ($user->isAdmin()) {
            $device = Device::where('id', $deviceId)->with('widget')->first();
        } else {
            $accessibleIds = $this->getAccessibleDeviceIds();
            $device = Device::where('id', $deviceId)
                ->whereIn('id', $accessibleIds)
                ->with('widget')->first();
        }

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        $widgets = $this->getWidgetsForDashboard($device);

        return response()->json([
            'success' => true,
            'widgets' => $widgets,
            'device'  => [
                'id'       => $device->id,
                'name'     => $device->name,
                'code'     => $device->device_code,
                'status'   => $device->status,
                'last_seen'=> $device->last_seen_at?->diffForHumans()
            ],
            'timestamp' => now()->toIso8601String()
        ]);
    }
}