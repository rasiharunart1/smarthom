<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deviceCode;
    public $status;
    public $lastSeenAt;
    public $isOnline;

    public function __construct($deviceCode, $status, $lastSeenAt, $isOnline)
    {
        $this->deviceCode = $deviceCode;
        $this->status = $status;
        $this->lastSeenAt = $lastSeenAt;
        $this->isOnline = $isOnline;
    }

    public function broadcastOn()
    {
        return new Channel('device.' .  $this->deviceCode);
    }

    public function broadcastAs()
    {
        return 'device.status';
    }

    public function broadcastWith()
    {
        return [
            'status' => $this->status,
            'last_seen_at' => $this->lastSeenAt,
            'is_online' => $this->isOnline,
        ];
    }
}