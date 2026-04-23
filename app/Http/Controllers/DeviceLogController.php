<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\DeviceLog5Min;
use App\Models\DeviceLogHourly;
use App\Models\DeviceLogDaily;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class DeviceLogController extends Controller
{
    // View Logs Page
    public function index(Request $request, Device $device)
    {
        // [SECURITY FIX H-6] Ensure the authenticated user owns or has access to this device
        Gate::authorize('view', $device);
        $query = DeviceLog::where('device_id', $device->id);

        if ($request->has('start_date') && $request->has('end_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->has('widget_key') && $request->widget_key != 'all') {
            $query->where('widget_key', $request->widget_key);
        }

        $logs = $query->latest()->paginate(20);
        
        // Get unique widget keys from DEVICE CONFIG (JSON)
        // because keys might not be in logs yet, and we want to show all possible widgets
        // Get widgets with names for dropdown
        // Get widgets with names for dropdown
        $widgets = [];
        $widgetNames = []; // Raw map: key => name (for table display)
        
        if ($device->widget) {
            $rawWidgets = $device->widget->getAllWidgets();
            foreach ($rawWidgets as $key => $data) {
                // Format: "Key" or "Name (Key)"
                $name = $data['name'] ?? $key;
                $widgets[$key] = $name === $key ? $key : "$name ($key)";
                $widgetNames[$key] = $name;
            }
        }
        
        // Pass complete widget array array instead of just keys
        return view('devices.logs', compact('device', 'logs', 'widgets', 'widgetNames'));
    }

    // API for Chart History
    public function history(Request $request, Device $device)
    {
        // [SECURITY FIX H-6] Ensure the authenticated user owns or has access to this device
        Gate::authorize('view', $device);
        $keys = explode(',', $request->query('keys', ''));
        $period = $request->query('period', '24h');

        $now = now();
        $start = $now->copy()->subDay();
        $model = DeviceLog5Min::class;
        $resolution = '5min';
        $timeColumn = 'bucket_time';
        $valueColumn = 'avg_value as value';

        // Determine Table & Range
        if ($request->has('resolution')) {
            $period = 'custom'; 
            // Override resolution based on user selection
            switch ($request->resolution) {
                case 'raw':
                    $model = DeviceLog::class;
                    $resolution = 'raw';
                    $timeColumn = 'created_at';
                    $valueColumn = \DB::raw('CAST(new_value AS DECIMAL(10,2)) as value');
                    break;
                case '5min':
                    $model = DeviceLog5Min::class;
                    $resolution = '5min';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                case 'hourly':
                    $model = DeviceLogHourly::class;
                    $resolution = 'hourly';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                 case 'daily':
                    $model = DeviceLogDaily::class;
                    $resolution = 'daily';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
            }
            
            // Set start/end if standard period
            if ($request->period && $request->period !== 'custom') {
                 // Re-apply start time logic for standard periods if explicit resolution requested
                 // (This part is optional if we assume frontend always sends dates for custom res, but safer to assume mixed)
            }
        }

        // Determine Table & Range
        if ($request->has('start_date') && $request->has('end_date') && $period === 'custom') {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            
            if (!$request->has('resolution')) {
                // Auto-detect resolution based on duration ONLY if not manually set
                $diffInDays = $start->diffInDays($end);
                
                if ($diffInDays <= 2) {
                    //... (existing auto logic)
                    $model = DeviceLog::class;
                    $resolution = 'raw';
                    $timeColumn = 'created_at';
                    $valueColumn = \DB::raw('CAST(new_value AS DECIMAL(10,2)) as value');
                } elseif ($diffInDays <= 7) {
                    $model = DeviceLog5Min::class;
                    $resolution = '5min';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                } elseif ($diffInDays <= 60) {
                     $model = DeviceLogHourly::class;
                     $resolution = 'hourly';
                     $timeColumn = 'bucket_time';
                     $valueColumn = 'avg_value as value';
                } else {
                     $model = DeviceLogDaily::class;
                     $resolution = 'daily';
                     $timeColumn = 'bucket_time';
                     $valueColumn = 'avg_value as value';
                }
            }
        } else {
            // Default logic
            switch ($period) {
                case '1h':
                    $start = $now->copy()->subHour();
                    $end = $now;
                    $model = DeviceLog::class; // Raw table
                    $resolution = 'raw';
                    $timeColumn = 'created_at';
                    $valueColumn = \DB::raw('CAST(new_value AS DECIMAL(10,2)) as value');
                    break;
                case '24h': 
                    $start = $now->copy()->subDay();
                    $end = $now;
                    $model = DeviceLog5Min::class;
                    $resolution = '5min';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                case '7d':
                    $start = $now->copy()->subDays(7);
                    $end = $now;
                    $model = DeviceLog5Min::class; 
                    $resolution = '5min';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                case '30d':
                    $start = $now->copy()->subDays(30);
                    $end = $now;
                    $model = DeviceLogHourly::class;
                    $resolution = 'hourly';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                case '1y':
                    $start = $now->copy()->subYear();
                    $end = $now;
                    $model = DeviceLogDaily::class;
                    $resolution = 'daily';
                    $timeColumn = 'bucket_time';
                    $valueColumn = 'avg_value as value';
                    break;
                default:
                    // Fallback to 24h
                    $start = $now->copy()->subDay();
                    $end = $now;
            }
        }

        $query = $model::where('device_id', $device->id)
            ->whereIn('widget_key', $keys)
            ->where($timeColumn, '>=', $start);
            
        if (isset($end)) {
             $query->where($timeColumn, '<=', $end);
        }

        $data = $query->select('widget_key', $timeColumn. ' as timestamp', $valueColumn)
            ->orderBy($timeColumn, 'asc')
            ->get()
            ->groupBy('widget_key');

        return response()->json([
            'success' => true,
            'resolution' => $resolution,
            'data' => $data
        ]);
    }

    // Export to CSV (Pivoted Format: Timestamp, Widget1, Widget2,...)
    public function export(Request $request, Device $device)
    {
        // [SECURITY FIX H-6] Ensure the authenticated user owns or has access to this device
        Gate::authorize('view', $device);

        $filename = "logs_{$device->device_code}_" . date('Ymd_His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            // [SECURITY FIX M-7] Filename is quoted to prevent header injection
            "Content-Disposition" => "attachment; filename=\"$filename\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        $callback = function() use ($device, $request) {
            $file = fopen('php://output', 'w');
            
            // 1. Get all logs respecting date filter
            $query = DeviceLog::where('device_id', $device->id);
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $start = Carbon::parse($request->start_date)->startOfDay();
                $end = Carbon::parse($request->end_date)->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            }
            
            $logs = $query->orderBy('created_at', 'desc')->get();
            
            // 2. Get all unique widget keys from device config and map to names
            $allWidgetKeys = [];
            $keyToNameMap = []; // Map: widget_key => widget_name
            
            if ($device->widget) {
                $widgets = $device->widget->getAllWidgets();
                $allWidgetKeys = array_keys($widgets);
                
                // Build mapping of key to name
                foreach ($widgets as $key => $widgetData) {
                    $keyToNameMap[$key] = $widgetData['name'] ?? $key;
                }
            }
            
            // If no widgets configured, fallback to keys found in logs
            if (empty($allWidgetKeys)) {
                $allWidgetKeys = $logs->pluck('widget_key')->unique()->values()->toArray();
                // For fallback, use keys as names
                foreach ($allWidgetKeys as $key) {
                    $keyToNameMap[$key] = $key;
                }
            }
            
            // Filter by user-selected widgets (from checkbox selection)
            if ($request->has('widgets') && is_array($request->widgets)) {
                $selectedWidgets = $request->widgets;
                $allWidgetKeys = array_intersect($allWidgetKeys, $selectedWidgets);
            }
            
            // 3. Build Header Row: Timestamp + Widget NAMES (not keys)
            $header = ['Timestamp'];
            foreach ($allWidgetKeys as $key) {
                $header[] = $keyToNameMap[$key] ?? $key;
            }
            fputcsv($file, $header);
            
            // 4. Group logs by timestamp (rounded to nearest second)
            $grouped = [];
            foreach ($logs as $log) {
                // Round timestamp to second for grouping
                $ts = $log->created_at->format('Y-m-d H:i:s');
                
                if (!isset($grouped[$ts])) {
                    $grouped[$ts] = [];
                }
                
                // Store value by widget_key
                $grouped[$ts][$log->widget_key] = $log->new_value;
            }
            
            
            // 5. Write rows (one per unique timestamp)
            // Optional Data Cleaning: Remove rows with missing widget values
            $cleanupEnabled = $request->has('cleanup_data') && $request->cleanup_data == '1';
            
            foreach ($grouped as $timestamp => $values) {
                $row = [$timestamp];
                
                // Collect values for this row
                $rowValues = [];
                foreach ($allWidgetKeys as $key) {
                    $rowValues[] = $values[$key] ?? '';
                }
                
                // Data Cleaning Check: Skip row if ANY value is empty
                if ($cleanupEnabled) {
                    $hasEmptyValue = false;
                    foreach ($rowValues as $val) {
                        if ($val === '' || $val === null) {
                            $hasEmptyValue = true;
                            break;
                        }
                    }
                    
                    if ($hasEmptyValue) {
                        continue; // Skip this row
                    }
                }
                
                // Write complete row
                $row = array_merge([$timestamp], $rowValues);
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    // Store (Manual Log Entry)
    public function store(Request $request, Device $device)
    {
        // [SECURITY FIX H-6] Only device owner or admin can manually add log entries
        Gate::authorize('update', $device);
        $request->validate([
            'widget_key' => 'required|string|max:50',
            'value' => 'required',
            'event_type' => 'nullable|string|max:20'
        ]);

        DeviceLog::create([
            'device_id' => $device->id,
            'widget_key' => $request->widget_key,
            'new_value' => $request->value,
            'event_type' => $request->event_type ?? 'MANUAL',
            'created_at' => now(),
            'metadata' => ['source' => 'web_manual']
        ]);

        return redirect()->back()->with('success', 'Log entry added successfully');
    }

    // Destroy (Single Log)
    public function destroy(Device $device, $logId)
    {
        // [SECURITY FIX H-6] Only device owner or admin can delete logs
        Gate::authorize('update', $device);
        $log = DeviceLog::where('device_id', $device->id)->findOrFail($logId);
        $log->delete();

        return redirect()->back()->with('success', 'Log entry deleted successfully');
    }

    // Clear (Bulk Delete)
    public function clear(Request $request, Device $device)
    {
        // [SECURITY FIX H-6] Only device owner or admin can bulk-clear logs
        Gate::authorize('update', $device);
        $query = DeviceLog::where('device_id', $device->id);

        if ($request->has('start_date') && $request->has('end_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $count = $query->delete();

        return redirect()->back()->with('success', "Cleared {$count} log entries.");
    }
}
