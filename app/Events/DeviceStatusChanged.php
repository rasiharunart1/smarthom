<?php
namespace App\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class DeviceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public function __construct(
        public string $deviceCode,
        public bool $isOnline,
        public ?string $lastSeenAt
    ) {}
    public function broadcastOn(): Channel
    {
        return new Channel('device.' . $this->deviceCode);
    }
    public function broadcastAs(): string
    {
        return 'status.changed';
    }
}