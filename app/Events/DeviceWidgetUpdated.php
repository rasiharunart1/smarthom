<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceWidgetUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;
    public $widgets;
    public $timestamp;
    public $isOnline;
    public $lastSeenAt;

    // Force sync broadcast
    public $connection = 'sync';

    public function __construct(Device $device)
    {
        $this->device = $device;
        $this->widgets = $device->widget->widgets_data ??  [];
        $this->timestamp = now()->toIso8601String();
        $this->isOnline = $device->isOnline();
        $this->lastSeenAt = $device->last_seen_at ?  
            $device->last_seen_at->toIso8601String() : null;

        Log::info('Event Created', [
            'device_code' => $device->device_code,
            'channel' => 'device.' .$device->device_code
        ]);
    }

    public function broadcastOn()
    {
        return new Channel('device.' .$this->device->device_code);
    }

    public function broadcastAs()
    {
        return 'widget.updated';
    }

    public function broadcastWith()
    {
        return [
            'device_code' => $this->device->device_code,
            'device_name' => $this->device->name,
            'widgets' => $this->widgets,
            'timestamp' => $this->timestamp,
            'is_online' => $this->isOnline,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}