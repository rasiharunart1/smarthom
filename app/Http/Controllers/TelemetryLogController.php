<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceLog5Min;
use App\Models\DeviceLogHourly;
use App\Models\DeviceLogDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelemetryLogController extends Controller
{
    /**
     * Return aggregated OHLC-style telemetry data for a widget.
     *
     * Resolution options:
     *   5min  → device_log_5min   (last 24h)
     *   1h    → device_log_hourly (last 7 days)
     *   1d    → device_log_daily  (last 90 days)
     *
     * Response format (array of):
     *   { time, open, high, low, close, avg }
     */
    public function aggregated(Request $request, Device $device, string $widgetKey)
    {
        $user = Auth::user();

        // --- Access control ---
        $isOwner   = $device->user_id === $user->id;
        $isAdmin   = $user->isAdmin();
        $isShared  = $device->isSharedWith($user);

        if (!$isOwner && !$isAdmin && !$isShared) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // --- Tier check: only Gold / Enterprise / Admin users ---
        if (!$user->canViewLogs()) {
            return response()->json([
                'error'   => 'upgrade_required',
                'message' => 'Log history is available on Gold and Enterprise plans.',
            ], 403);
        }

        $resolution = $request->input('resolution', '5min');

        switch ($resolution) {
            case '1h':
                $rows = DeviceLogHourly::where('device_id', $device->id)
                    ->where('widget_key', $widgetKey)
                    ->where('bucket_time', '>=', now()->subDays(7))
                    ->orderBy('bucket_time')
                    ->get(['bucket_time', 'avg_value', 'min_value', 'max_value']);
                break;

            case '1d':
                $rows = DeviceLogDaily::where('device_id', $device->id)
                    ->where('widget_key', $widgetKey)
                    ->where('bucket_time', '>=', now()->subDays(90))
                    ->orderBy('bucket_time')
                    ->get(['bucket_time', 'avg_value', 'min_value', 'max_value']);
                break;

            default: // 5min
                $rows = DeviceLog5Min::where('device_id', $device->id)
                    ->where('widget_key', $widgetKey)
                    ->where('bucket_time', '>=', now()->subHours(24))
                    ->orderBy('bucket_time')
                    ->get(['bucket_time', 'avg_value', 'min_value', 'max_value']);
                break;
        }

        // Format for Lightweight Charts: candlestick series expects {time, open, high, low, close}
        // Since we store avg/min/max (not true OHLC), we simulate:
        //   open  = avg of previous bucket (or avg for first)
        //   close = current avg
        //   high  = max_value
        //   low   = min_value
        $data = [];
        $prevAvg = null;

        foreach ($rows as $row) {
            $avg  = (float) $row->avg_value;
            $open = $prevAvg ?? $avg;

            $data[] = [
                'time'  => $row->bucket_time->timestamp,
                'open'  => round($open, 4),
                'high'  => round((float) $row->max_value, 4),
                'low'   => round((float) $row->min_value, 4),
                'close' => round($avg, 4),
                'avg'   => round($avg, 4),
            ];
            $prevAvg = $avg;
        }

        return response()->json([
            'widget_key' => $widgetKey,
            'resolution' => $resolution,
            'count'      => count($data),
            'data'       => $data,
        ]);
    }
}
