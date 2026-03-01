<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        // Fetch all devices for the user (or all if admin)
        $devices = $user->isAdmin() ? Device::all() : $user->devices;

        $schedules = collect();

        foreach ($devices as $device) {
            foreach ($device->widget?->widgets_data ?? [] as $key => $widgetData) {
                if (isset($widgetData['config']['schedules'])) {
                    foreach ($widgetData['config']['schedules'] as $index => $schedule) {
                        $schedules->push((object) [
                            'device_id' => $device->id,
                            'device_name' => $device->name,
                            'widget_key' => $key,
                            'widget_name' => $widgetData['name'],
                            'widget_type' => $widgetData['type'],
                            'index' => $index,
                            'time' => $schedule['time'],
                            'value' => $schedule['value'],
                            'days' => $schedule['days'] ?? [],
                            'enabled' => $schedule['enabled'] ?? false,
                        ]);
                    }
                }
            }
        }

        return view('schedules.index', compact('schedules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $devices = $user->isAdmin() ? Device::all() : $user->devices;
        
        // We need to pass devices and their widgets to the view to populate dropdowns
        $devicesWithWidgets = $devices->map(function ($device) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'widgets' => collect($device->widget?->widgets_data ?? [])->map(function ($w, $k) {
                    return ['key' => $k, 'name' => $w['name'], 'type' => $w['type']];
                })->values()
            ];
        });

        return view('schedules.create', compact('devicesWithWidgets'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'widget_key' => 'required',
            'time' => 'required',
            'value' => 'required',
            'days' => 'array',
        ]);

        $device = Device::findOrFail($request->device_id);
        
        // Check permission
        if (!Auth::user()->isAdmin() && $device->user_id !== Auth::id()) {
            abort(403);
        }

        $widgetModel = $device->widget;
        $widgetsData = $widgetModel->widgets_data;
        $widgetKey = $request->widget_key;

        if (!isset($widgetsData[$widgetKey])) {
            return back()->with('error', 'Widget not found.');
        }

        // Initialize config and schedules if missing
        if (!isset($widgetsData[$widgetKey]['config'])) {
            $widgetsData[$widgetKey]['config'] = [];
        }
        if (!isset($widgetsData[$widgetKey]['config']['schedules'])) {
            $widgetsData[$widgetKey]['config']['schedules'] = [];
        }

        // Create new schedule
        $newSchedule = [
            'time' => $request->time,
            'value' => $request->value,
            'days' => $request->days ?? [],
            'enabled' => $request->has('enabled') ? true : false,
        ];

        // Append to array
        $widgetsData[$widgetKey]['config']['schedules'][] = $newSchedule;

        // Save back
        $widgetModel->widgets_data = $widgetsData;
        $widgetModel->save();

        return redirect()->route('schedules.index')->with('success', 'Schedule created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $id) 
    {
        // $id here is tricky because we don't have DB IDs for schedules.
        // We'll pass composite ID: deviceId:widgetKey:index in the route or query
        // Actually, cleaner valid REST is tricky. Let's assume the ID passed is a composite string
        // base64 encoded to avoid slash issues? Or just use query params.
        
        $deviceId = $request->query('device_id');
        $widgetKey = $request->query('widget_key');
        $index = $request->query('index');

        if (!$deviceId || !$widgetKey || is_null($index)) {
            return redirect()->route('schedules.index')->with('error', 'Invalid schedule reference.');
        }

        $device = Device::findOrFail($deviceId);
        if (!Auth::user()->isAdmin() && $device->user_id !== Auth::id()) {
            abort(403);
        }

        $widgetsData = $device->widget->widgets_data;
        $schedule = $widgetsData[$widgetKey]['config']['schedules'][$index] ?? null;

        if (!$schedule) {
            return redirect()->route('schedules.index')->with('error', 'Schedule not found.');
        }

        return view('schedules.edit', compact('device', 'widgetKey', 'index', 'schedule', 'widgetsData'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // ID is ignored, we use hidden fields
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'widget_key' => 'required',
            'schedule_index' => 'required|integer',
            'time' => 'required',
            'value' => 'required',
            'days' => 'array',
        ]);

        $device = Device::findOrFail($request->device_id);
        if (!Auth::user()->isAdmin() && $device->user_id !== Auth::id()) {
            abort(403);
        }

        $widgetModel = $device->widget;
        $widgetsData = $widgetModel->widgets_data;
        $widgetKey = $request->widget_key;
        $index = $request->schedule_index;

        if (!isset($widgetsData[$widgetKey]['config']['schedules'][$index])) {
            return back()->with('error', 'Schedule not found to update.');
        }

        // Update schedule
        $widgetsData[$widgetKey]['config']['schedules'][$index] = [
            'time' => $request->time,
            'value' => $request->value,
            'days' => $request->days ?? [],
            'enabled' => $request->has('enabled') ? true : false,
        ];

        $widgetModel->widgets_data = $widgetsData;
        $widgetModel->save();

        return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $deviceId = $request->query('device_id');
        $widgetKey = $request->query('widget_key');
        $index = $request->query('index');

        $device = Device::findOrFail($deviceId);
        if (!Auth::user()->isAdmin() && $device->user_id !== Auth::id()) {
            abort(403);
        }

        $widgetModel = $device->widget;
        $widgetsData = $widgetModel->widgets_data;

        if (isset($widgetsData[$widgetKey]['config']['schedules'][$index])) {
            array_splice($widgetsData[$widgetKey]['config']['schedules'], $index, 1);
            $widgetModel->widgets_data = $widgetsData;
            $widgetModel->save();
             return redirect()->route('schedules.index')->with('success', 'Schedule deleted successfully.');
        }

        return back()->with('error', 'Schedule could not be deleted.');
    }
}
