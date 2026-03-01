<?php
namespace App\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class WidgetUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public string $deviceCode;
    public string $widgetKey;
    public mixed $newValue;
    public string $type;
    public ?string $timestamp;
    public function __construct(
        string $deviceCode,
        string $widgetKey,
        mixed $newValue,
        string $type
    ) {
        $this->deviceCode = $deviceCode;
        $this->widgetKey = $widgetKey;
        $this->newValue = $newValue;
        $this->type = $type;
        $this->timestamp = now()->toIso8601String();
    }
    /**
     * Channel publik untuk device tertentu
     */
    public function broadcastOn(): Channel
    {
        return new Channel('device.' . $this->deviceCode);
    }
    /**
     * Nama event yang dikirim ke frontend
     */
    public function broadcastAs(): string
    {
        return 'widget.updated';
    }
    /**
     * Data yang dikirim ke frontend
     */
    public function broadcastWith(): array
    {
        return [
            'deviceCode' => $this->deviceCode,
            'widgetKey' => $this->widgetKey,
            'value' => $this->newValue,
            'type' => $this->type,
            'timestamp' => $this->timestamp,
        ];
    }
}