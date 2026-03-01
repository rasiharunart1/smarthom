<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Check subscription expiration
        $showExpirationPopup = !$user->isAdmin() && 
                               $user->subscription_expires_at && 
                               $user->subscription_expires_at->isPast();
        
        $adminWhatsapp = Setting::where('key', 'admin_whatsapp')->value('value');

        // Get devices: Admins see all, Users see their own
        if ($user->isAdmin()) {
            $devices = Device::orderBy('name')->get();
        } else {
            $devices = Device::where('user_id', $user->id)
                ->orderBy('name')
                ->get();
        }

        $selectedDevice = null;

        if ($devices->count() > 0) {
            // Get selected device from request or session
            $selectedDeviceId = $request->input('device_id') 
                             ?? $request->session()->get('selected_device_id');

            if ($selectedDeviceId) {
                // Load device with widget configuration (new structure)
                // Load device
                $query = Device::where('id', $selectedDeviceId);
                if (!$user->isAdmin()) {
                    $query->where('user_id', $user->id);
                }
                $selectedDevice = $query->with('widget')->first();

                if (!$selectedDevice && $devices->count() > 0) {
                    // Fallback to first device
                    $request->session()->forget('selected_device_id');
                    $selectedDevice = $devices->first();
                    $selectedDevice->load('widget');
                    $request->session()->put('selected_device_id', $selectedDevice->id);
                }
            } else {
                // Use first device
                $selectedDevice = $devices->first();
                $selectedDevice->load('widget');
                $request->session()->put('selected_device_id', $selectedDevice->id);
            }

            // Save to session
            if ($selectedDevice) {
                $request->session()->put('selected_device_id', $selectedDevice->id);
                $request->session()->put('selected_device_code', $selectedDevice->device_code);
            }
        }

        // Get widgets from new structure
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

        // Get widgets data from JSON column
        $widgetsData = $device->widget->widgets_data ?? [];

        // Convert to collection and prepare for frontend
        $widgets = collect($widgetsData)->map(function ($widget, $key) use ($device) {
            // ✅ Use KEY as identifier (no DB ID in JSON!)
            $widgetData = [
                'key' => $key,  // ✅ Primary identifier
                'name' => $widget['name'] ?? 'Unnamed',
                'type' => $widget['type'] ?? 'text',
                'value' => $widget['value'] ?? '',
                'min' => $widget['min'] ?? 0,
                'max' => $widget['max'] ?? 100,
                'position_x' => $widget['position_x'] ?? 0,
                'position_y' => $widget['position_y'] ?? 0,
                'width' => $widget['width'] ?? 4,
                'height' => $widget['height'] ?? 2,
                'order' => $widget['order'] ?? 0,
                'config' => $widget['config'] ?? [],
                'created_at' => $widget['created_at'] ?? null,
                'updated_at' => $widget['updated_at'] ?? null,
            ];

            // Add MQTT topic info for display
            $widgetData['mqtt_topic'] = "users/{$device->user_id}/devices/{$device->device_code}/sensors/{$key}";
            
            // Add formatted unit
            $unit = $widgetData['config']['unit'] ?? '';
            $widgetData['unit'] = $unit;
            
            // Add icon
            $widgetData['icon'] = $widgetData['config']['icon'] ?? $this->getDefaultIcon($widgetData['type']);

            return (object) $widgetData; // Convert to object for blade compatibility
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
            'gauge' => 'tachometer-alt',
            'text' => 'info-circle',
            'chart' => 'chart-line',
            default => 'cube'
        };
    }

    /**
     * Change selected device
     */
    public function selectDevice(Request $request)
    {
        $deviceId = $request->input('device_id');

        $query = Device::where('id', $deviceId);
        if (!Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }
        $device = $query->first();

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

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'No device selected'
            ], 400);
        }

        $query = Device::where('id', $deviceId);
        if (!Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }
        $device = $query->with('widget')->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found'
            ], 404);
        }

        $widgets = $this->getWidgetsForDashboard($device);

        return response()->json([
            'success' => true,
            'widgets' => $widgets,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'code' => $device->device_code,
                'status' => $device->status,
                'last_seen' => $device->last_seen?->diffForHumans()
            ],
            'timestamp' => now()->toIso8601String()
        ]);
    }
}