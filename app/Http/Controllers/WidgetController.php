<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Widget;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    /**
     * Display a listing of the widgets (Metadata Table)
     */
    public function index(Device $device)
    {
        // Check if user owns this device
        if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $widgetConfig = $device->widget;
        $widgets = $widgetConfig ? $widgetConfig->getOrderedWidgets() : [];

        return view('devices.widgets.index', compact('device', 'widgets'));
    }

    /**
     * Store a new widget
     */
    public function store(Request $request, Device $device)
    {
        // Check if user owns this device
        if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        // Check Widget Limit
        if (!$device->user->canAddWidget($device)) {
            $msg = "Module limit reached: This node cannot exceed {$device->user->getLimit('max_widgets_per_device')} active interactive modules.";
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            return back()->with('error', $msg);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:toggle,slider,gauge,text,chart',
            'min' => 'nullable|integer',
            'max' => 'nullable|integer|gt:min',
            'width' => 'nullable|integer|min:1|max:12',
            'height' => 'nullable|integer|min:1|max:12',
            'config' => 'nullable|array',
        ]);


        try {
            // Get or create widget configuration for device
            $widgetConfig = $device->widget;

            if (!$widgetConfig) {
                $widgetConfig = Widget::create([
                    'device_id' => $device->id,
                    'widgets_data' => [],
                    'grid_config' => [
                        'columns' => 12,
                        'cellHeight' => 100,
                        'margin' => 20
                    ],
                    'widget_count' => 0
                ]);
            }

            // Generate unique key for new widget (Sequential: toggle1, toggle2, etc)
            $widgetKey = Widget::generateSequentialKey($validated['type'], $widgetConfig->getAllWidgets());

            // Set default value based on type
            $defaultValue = match($validated['type']) {
                'toggle' => '0',
                'slider', 'gauge' => (string)($validated['min'] ?? 0),
                'text' => 'N/A',
                'chart' => '0',
                default => '0'
            };

            // Get current widgets count for positioning
            $currentWidgets = $widgetConfig->widgets_data ?? [];
            $widgetCount = count($currentWidgets);

            // Prepare new widget data
            $newWidget = [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'type_index' => $widgetKey, // ✅ Explicit index (e.g. toggle1)
                'value' => $defaultValue,
                'min' => $validated['min'] ?? 0,
                'max' => $validated['max'] ?? 100,
                'position_x' => 0,
                'position_y' => $widgetCount,
                'width' => $validated['width'] ??  4,
                'height' => $validated['height'] ?? 2,
                'order' => $widgetCount,
                'config' => $validated['config'] ?? [
                    'icon' => $this->getDefaultIcon($validated['type']),
                    'color' => 'success',
                    'unit' => null,
                    'description' => null
                ],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ];

            // Add widget to configuration
            $widgetConfig->addWidget($widgetKey, $newWidget);

            Log::info('✅ Widget created', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'widget_key' => $widgetKey,
                'widget_name' => $validated['name']
            ]);

            if ($request->ajax()) {
                // Return rendered HTML for instant addition
                $widgetObj = (object) array_merge($newWidget, ['key' => $widgetKey]);
                $html = view('partials.widget-card', ['widget' => $widgetObj])->render();

                return response()->json([
                    'success' => true,
                    'message' => 'Module created successfully!',
                    'widget' => $widgetObj,
                    'html' => $html
                ]);
            }

            return redirect()
                ->route('dashboard')
                ->with('success', 'Widget created successfully!');

        } catch (Exception $e) {
            Log::error('❌ Widget creation failed: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create widget: '.$e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to create widget.');
        }
    }
    /**
     * Store multiple widgets at once
     */
    public function bulkStore(Request $request, Device $device)
    {
        // Check if user owns this device
        if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:toggle,slider,gauge,text,chart',
            'quantity' => 'required|integer|min:1|max:50',
            'min' => 'nullable|integer',
            'max' => 'nullable|integer|gt:min',
            'width' => 'nullable|integer|min:1|max:12',
            'height' => 'nullable|integer|min:1|max:12',
            'config' => 'nullable|array',
        ]);

        $quantity = (int) $validated['quantity'];

        // Check Widget Limit for total quantity
        $currentCount = $device->widget ? count($device->widget->widgets_data ?? []) : 0;
        $maxAllowed = auth()->user()->getLimit('max_widgets_per_device');
        if ($currentCount + $quantity > $maxAllowed) {
            $msg = "Bulk creation failed: This would exceed the limit of {$maxAllowed} modules (Current: {$currentCount}, Requested: {$quantity}).";
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            return back()->with('error', $msg);
        }

        try {
            $widgetConfig = $device->widget;

            if (!$widgetConfig) {
                $widgetConfig = Widget::create([
                    'device_id' => $device->id,
                    'widgets_data' => [],
                    'grid_config' => [
                        'columns' => 12,
                        'cellHeight' => 100,
                        'margin' => 20
                    ],
                    'widget_count' => 0
                ]);
            }

            $widgetsToBatch = [];
            $existingWidgets = $widgetConfig->getAllWidgets();
            
            for ($i = 0; $i < $quantity; $i++) {
                // Generate sequential key
                $tempExisting = array_merge($existingWidgets, $widgetsToBatch);
                $widgetKey = Widget::generateSequentialKey($validated['type'], $tempExisting);
                
                // Default value based on type
                $defaultValue = match($validated['type']) {
                    'toggle' => '0',
                    'slider', 'gauge' => (string)($validated['min'] ?? 0),
                    'text' => 'N/A',
                    'chart' => '0',
                    default => '0'
                };

                $newWidget = [
                    'name' => ucfirst($validated['type']) . " " . preg_replace('/[^0-9]/', '', $widgetKey),
                    'type' => $validated['type'],
                    'type_index' => $widgetKey,
                    'value' => $defaultValue,
                    'min' => $validated['min'] ?? 0,
                    'max' => $validated['max'] ?? 100,
                    'position_x' => 0,
                    'position_y' => $currentCount + $i,
                    'width' => $validated['width'] ?? 4,
                    'height' => $validated['height'] ?? 2,
                    'order' => $currentCount + $i,
                    'config' => $validated['config'] ?? [
                        'icon' => $this->getDefaultIcon($validated['type']),
                        'color' => 'success',
                        'unit' => null,
                        'description' => null
                    ],
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ];

                $widgetsToBatch[$widgetKey] = $newWidget;
            }

            // Save all at once using the new model method
            $widgetConfig->addWidgets($widgetsToBatch);

            Log::info('✅ Bulk widgets created', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'type' => $validated['type'],
                'count' => $quantity
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$quantity} modules initialized successfully!",
                ]);
            }

            return redirect()
                ->route('dashboard')
                ->with('success', "{$quantity} modules created successfully!");

        } catch (Exception $e) {
            Log::error('❌ Bulk widget creation failed: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to perform bulk creation: '.$e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to perform bulk creation.');
        }
    }

    /**
     * Update a widget
     */
    public function update(Request $request, Device $device, $widgetKey)
    {
        try {
            // Check if user owns this device
            if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                abort(403);
            }

            if (!$device->widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget configuration not found'
                ], 404);
            }

            $widgetConfig = $device->widget;
            Log::info("DEBUG UPDATE: Device {$device->id}, Request Key: {$widgetKey}");

            $widget = $widgetConfig->getWidget($widgetKey);

            if (!$widget) {
                Log::error("DEBUG UPDATE: Widget not found. Available keys: " . implode(', ', array_keys($widgetConfig->getAllWidgets())));
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found'
                ], 404);
            }

            // ── Fix checkbox unchecked behavior ────────────────────────────
            // HTML checkbox tidak mengirim nilai apapun saat unchecked.
            // Jika form dikirim dengan config[] tapi tanpa config[alert_enabled],
            // artinya user sengaja mematikannya → paksa false.
            if ($request->has('config') && !$request->has('config.alert_enabled')) {
                $request->merge([
                    'config' => array_merge($request->input('config', []), [
                        'alert_enabled' => false,
                    ])
                ]);
            }

            $validated = $request->validate([
                'key'                  => 'sometimes|string|max:255',
                'type_index'           => 'sometimes|string|max:255',
                'name'                 => 'sometimes|required|string|max:255',
                'type'                 => 'sometimes|required|in:toggle,slider,gauge,text,chart',
                'value'                => 'sometimes|nullable',
                'min'                  => 'sometimes|nullable|integer',
                'max'                  => 'sometimes|nullable|integer|gt:min',
                'width'                => 'sometimes|nullable|integer|min:1|max:12',
                'height'               => 'sometimes|nullable|integer|min:1|max:12',
                'position_x'           => 'sometimes|nullable|integer',
                'position_y'           => 'sometimes|nullable|integer',
                'config'               => 'sometimes|nullable|array',
                'config.source_key'    => 'sometimes|nullable|string',
                'config.y_axis_step'   => 'sometimes|nullable|numeric',
                'config.unit'          => 'sometimes|nullable|string|max:50',
                'config.icon'          => 'sometimes|nullable|string|max:50',
                'config.description'   => 'sometimes|nullable|string|max:500',
                'config.schedules'     => 'sometimes|nullable|array',
                // Alert threshold fields
                'config.alert_enabled' => 'sometimes|nullable|boolean',
                'config.alert_min'     => 'sometimes|nullable|numeric',
                'config.alert_max'     => 'sometimes|nullable|numeric',
            ]);

            // Handle key renaming if requested
            if (isset($validated['key']) && $validated['key'] !== $widgetKey) {
                $newKey = $validated['key'];
                // Use renameWidget logic
                $widgetConfig->renameWidget($widgetKey, $newKey);
                $widgetKey = $newKey; // Update current key for subsequent updates
                unset($validated['key']);
            }

            // Update widget data
            $widgetConfig->updateWidget($widgetKey, $validated);

            Log::info('✅ Widget updated', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'widget_key' => $widgetKey
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Widget updated successfully!',
                    'widget' => $widgetConfig->getWidget($widgetKey)
                ]);
            }

            return back()->with('success', 'Widget updated successfully!');

        } catch (Exception $e) {
            Log::error('❌ Widget update failed: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update widget: '.$e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to update widget.');
        }
    }

    /**
     * Delete a widget
     */
    public function destroy(Device $device, $widgetKey)
    {
        try {
            // Check if user owns this device
            if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                abort(403);
            }

            if (!$device->widget) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Widget configuration not found'
                    ], 404);
                }
                return back()->with('error', 'Widget configuration not found');
            }

            $widgetConfig = $device->widget;

            if (!$widgetConfig->getWidget($widgetKey)) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Widget not found'
                    ], 404);
                }
                return back()->with('error', 'Widget not found');
            }

            // Remove widget
            $widgetConfig->removeWidget($widgetKey);

            Log::info('✅ Widget deleted', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'widget_key' => $widgetKey
            ]);

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Widget deleted successfully!'
                ]);
            }

            return redirect()
                ->route('dashboard')
                ->with('success', 'Widget deleted successfully!');

        } catch (Exception $e) {
            Log::error('❌ Widget deletion failed: '.$e->getMessage());

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete widget: '.$e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to delete widget.');
        }
    }

    /**
     * Bulk update widget keys
     */
    public function bulkUpdateKeys(Request $request, Device $device)
    {
        if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'renames' => 'required|array',
            'renames.*' => 'required|string|distinct|alpha_dash', // new key
        ]);

        if (!$device->widget) {
             return response()->json(['success' => false, 'message' => 'No widgets found'], 404);
        }

        $widgetConfig = $device->widget;
        $results = [];
        $errors = [];

        // We process renames one by one.
        // limitation: swapping keys (A->B, B->A) directly might fail if intermediate state conflicts.
        foreach ($validated['renames'] as $oldKey => $newKey) {
            if ($oldKey === $newKey) continue;
            
            try {
                $widgetConfig->renameWidget($oldKey, $newKey);
                $results[$oldKey] = $newKey;
            } catch (Exception $e) {
                $errors[$oldKey] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($errors) === 0 ? 'Keys updated successfully' : 'Some keys failed to update',
            'results' => $results,
            'errors' => $errors
        ]);
    }

    /**
     * Update widget positions (for drag & drop)
     */
    public function updatePositions(Request $request, Device $device)
    {
        if ($device->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$device->widget) {
            return response()->json([
                'success' => false,
                'message' => 'Widget configuration not found'
            ], 404);
        }

        $validated = $request->validate([
            'positions' => 'required|array',
            'positions.*.key' => 'required|string',
            'positions.*.x' => 'required|integer|min:0',
            'positions.*.y' => 'required|integer|min:0',
            'positions.*.w' => 'sometimes|integer|min:1|max:12',
            'positions.*.h' => 'sometimes|integer|min:1|max:12',
        ]);

        try {
            $device->widget->updatePositions($validated['positions']);

            Log::info('✅ Widget positions updated', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'widgets_count' => count($validated['positions'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Layout saved successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('❌ Failed to update widget positions: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save layout: '.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Update widget value (FROM UI - PUBLISH TO MQTT)
     * ✅ THIS IS THE KEY METHOD THAT SENDS CONTROL TO DEVICE
     */
    public function updateValue(Request $request, Device $device, $widgetKey)
    {
        try {
            $user = auth()->user();
            // Allow: device owner, admin, OR shared user with 'control' permission
            $isOwnerOrAdmin = $device->user_id === $user->id || $user->isAdmin();
            $isSharedControl = $device->isSharedWith($user) && $device->getPermissionFor($user) === 'control';

            if (!$isOwnerOrAdmin && !$isSharedControl) {
                abort(403, 'You do not have control access to this device.');
            }

            if (!$device->widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget configuration not found'
                ], 404);
            }

            $widgetConfig = $device->widget;
            $widget = $widgetConfig->getWidget($widgetKey);

            if (!$widget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Widget not found'
                ], 404);
            }

            $validated = $request->validate([
                'value'  => 'required',
                'silent' => 'nullable|boolean',
            ]);

            // [SECURITY FIX H-5] Validate value based on widget type to prevent
            // Stored XSS, out-of-range values, or abnormally large payloads.
            $rawValue = $validated['value'];
            $widgetType = $widget['type'] ?? 'text';
            $wMin = $widget['min'] ?? 0;
            $wMax = $widget['max'] ?? 100;

            switch ($widgetType) {
                case 'toggle':
                    if (!in_array((string) $rawValue, ['0', '1', 'true', 'false', 'on', 'off'], true)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid value for toggle widget. Expected: 0 or 1.",
                        ], 422);
                    }
                    break;

                case 'slider':
                case 'gauge':
                    if (!is_numeric($rawValue)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid value for {$widgetType} widget. Expected a numeric value.",
                        ], 422);
                    }
                    $numVal = (float) $rawValue;
                    if ($numVal < (float) $wMin || $numVal > (float) $wMax) {
                        return response()->json([
                            'success' => false,
                            'message' => "Value {$rawValue} is out of range [{$wMin}, {$wMax}] for {$widgetType} widget.",
                        ], 422);
                    }
                    break;

                case 'text':
                case 'chart':
                default:
                    // Limit text length to prevent DB abuse
                    if (is_string($rawValue) && mb_strlen($rawValue) > 500) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Value exceeds maximum allowed length of 500 characters.',
                        ], 422);
                    }
                    break;
            }

            $oldValue = $widget['value'];

            // (1) Update database
            $widgetConfig->updateWidgetValue($widgetKey, $validated['value']);

            Log::info('✅ Widget value updated (web UI)', [
                'user' => auth()->user()->name,
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'widget_key' => $widgetKey,
                'widget_name' => $widget['name'],
                'old_value' => $oldValue,
                'new_value' => $validated['value']
            ]);

            // (2) ✅ PUBLISH TO MQTT (Skip if silent is requested)
            $mqttResult = ['success' => false, 'skipped' => false];

            if ($request->boolean('silent')) {
                $mqttResult['skipped'] = true;
                Log::info('ℹ️ MQTT publish skipped (silent request)', [
                    'user' => auth()->user()->name,
                    'device_id' => $device->id,
                    'widget_key' => $widgetKey
                ]);
            } else {
                try {
                    $mqttService = app(\App\Services\MqttService::class);

                    $mqttResult = $mqttService->publishWidgetControl(
                        $device->user_id,
                        $device->device_code,
                        $widgetKey,
                        $validated['value']
                    );

                    if ($mqttResult['success']) {
                        Log::info('✅ MQTT control published', [
                            'topic' => $mqttResult['topic'],
                            'payload' => $mqttResult['payload']
                        ]);
                    } else {
                        Log::warning('⚠️ MQTT publish returned failure', $mqttResult);
                    }

                } catch (\Exception $e) {
                    Log::error('❌ MQTT publish exception', [
                        'error' => $e->getMessage(),
                        'widget_key' => $widgetKey,
                        'device_code' => $device->device_code
                    ]);

                    $mqttResult['error'] = $e->getMessage();
                }
            }

            // Get updated widget
            $updatedWidget = $widgetConfig->getWidget($widgetKey);

            return response()->json([
                'success' => true,
                'message' => 'Widget value updated successfully!',
                'widget' => [
                    'key' => $widgetKey,
                    'name' => $updatedWidget['name'],
                    'value' => $updatedWidget['value'],
                    'type' => $updatedWidget['type']
                ],
                'mqtt_published' => $mqttResult['success'] ?? false,
                'mqtt_topic' => $mqttResult['topic'] ?? null,
                'mqtt_error' => $mqttResult['error'] ?? null
            ]);

        } catch (Exception $e) {
            Log::error('❌ Widget value update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update widget value: '.$e->getMessage()
            ], 500);
        }
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
}