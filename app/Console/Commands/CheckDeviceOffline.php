<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDeviceOffline extends Command
{
    protected $signature = 'devices:check-offline
                            {--minutes=5 : Minutes without heartbeat before marking offline}';

    protected $description = 'Mark devices as offline if no heartbeat received within the threshold';

    public function handle(): void
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);

        // Find devices that are currently online but haven't sent heartbeat recently
        $staleDevices = Device::where('status', 'online')
            ->where(function ($q) use ($threshold) {
                $q->where('last_seen_at', '<', $threshold)
                  ->orWhereNull('last_seen_at');
            })
            ->get();

        if ($staleDevices->isEmpty()) {
            $this->info("✅ All online devices are up-to-date.");
            return;
        }

        foreach ($staleDevices as $device) {
            $device->update(['status' => 'offline']);

            $lastSeen = $device->last_seen_at
                ? $device->last_seen_at->diffForHumans()
                : 'never';

            $this->warn("📴 [{$device->device_code}] {$device->name} → offline (last seen: {$lastSeen})");

            Log::info("Device auto-marked offline", [
                'device_code' => $device->device_code,
                'last_seen_at' => $device->last_seen_at,
                'threshold_minutes' => $minutes,
            ]);
        }

        $count = $staleDevices->count();
        $this->info("Done. {$count} device(s) marked offline.");
    }
}
