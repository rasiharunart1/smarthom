<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen for MQTT messages, update widgets, and log data to database';

    private $mqtt;

    public function handle()
    {
        $this->info("🚀 MQTT Listener Started");

        while (true) {
            try {

                $this->connectToMqtt();
                $this->subscribeToTopics();

                $this->mqtt->loop(true);

            } catch (\Throwable $e) {

                $this->error("❌ MQTT Error: " . $e->getMessage());
                Log::error("MQTT Listener error", [
                    'message' => $e->getMessage()
                ]);

                sleep(5);

                $this->info("🔄 Reconnecting MQTT...");
            }
        }
    }

    private function connectToMqtt()
    {
        $host = env('MQTT_HOST');
        $port = (int) env('MQTT_PORT', 1883);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');

        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls((bool) env('MQTT_USE_TLS', false))
            ->setKeepAliveInterval(60);

        $clientId = "laravel-listener-" . uniqid();

        $this->mqtt = new MqttClient($host, $port, $clientId);

        $this->mqtt->connect($settings, true);

        $this->info("✅ Connected MQTT {$host}:{$port}");
    }

    private function subscribeToTopics()
    {

        $topics = [

            'users/+/devices/+/sensors/+' => 'sensors',
            'users/+/devices/+/control/+' => 'control',
            'users/+/devices/+/heartbeat' => 'heartbeat'

        ];

        foreach ($topics as $topic => $type) {

            $this->mqtt->subscribe($topic, function ($topic, $message) use ($type) {

                if ($type === 'heartbeat') {
                    $this->handleHeartbeat($topic, $message);
                } else {
                    $this->handleMessage($topic, $message, $type);
                }

            }, 1);

            $this->info("📡 Subscribed: {$topic}");
        }

        $this->info("👂 Listening MQTT...\n");
    }

    private function handleHeartbeat(string $topic, string $message)
    {

        if (!preg_match('/^users\/(\d+)\/devices\/([^\/]+)\/heartbeat$/', $topic, $m)) {
            return;
        }

        $userId = $m[1];
        $deviceCode = $m[2];

        $device = Device::where('device_code', $deviceCode)
            ->where('user_id', $userId)
            ->first();

        if (!$device) {
            $this->warn("❌ Heartbeat device not found: {$deviceCode}");
            return;
        }

        $device->markAsOnline();

        $this->info("💓 Heartbeat {$deviceCode}");
    }

    private function handleMessage(string $topic, string $message, string $direction)
    {

        $timestamp = now()->format('H:i:s');
        $this->line("[$timestamp] {$topic} -> {$message}");

        $data = json_decode($message, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($data['value'])) {
            $message = $data['value'];
        }

        if (!preg_match('/^users\/(\d+)\/devices\/([^\/]+)\/(sensors|control)\/(.+)$/', $topic, $m)) {
            $this->warn("⚠️ Invalid topic format");
            return;
        }

        $userId = $m[1];
        $deviceCode = $m[2];
        $widgetPart = trim($m[4]);

        $device = Device::with(['widget', 'user'])
            ->where('device_code', $deviceCode)
            ->where('user_id', $userId)
            ->first();

        if (!$device) {
            $this->warn("❌ Device not found: {$deviceCode}");
            return;
        }

        // 🔒 Approval check — silently ignore messages from unapproved devices
        if (!$device->isApproved()) {
            $this->warn("⛔ Device {$deviceCode} not approved — message ignored");
            return;
        }

        if (!$device->widget) {
            $this->warn("❌ Widget config missing");
            return;
        }

        if ($direction === 'sensors') {
            $device->markAsOnline();
        }

        $widgetsData = $device->widget->widgets_data ?? [];

        $widgetKey = $this->findWidgetKey($widgetsData, $widgetPart);

        if (!$widgetKey) {
            $this->warn("❌ Widget {$widgetPart} not found");
            return;
        }

        $widget = $widgetsData[$widgetKey];
        $type = $widget['type'] ?? 'text';

        $oldValue = $widget['value'] ?? null;

        $newValue = $this->formatValueForType($type, $message, $widget);

        if ($newValue === false) {
            $this->warn("❌ Invalid value type {$type}");
            return;
        }

        if ((string)$oldValue === (string)$newValue) {
            return;
        }

        $device->widget->updateWidgetValue($widgetKey, $newValue);

        Cache::put("device:{$deviceCode}:updates", [
            'widgetKey' => $widgetKey,
            'value' => $newValue,
            'timestamp' => now()->timestamp
        ], 60);

        if (!$device->isLstmActive()) {

            // --- Admin Log Control ---
            // 1. Check if logging is enabled for this user
            $user = $device->user ?? User::find($device->user_id);

            if ($user && !$user->isLogEnabled()) {
                $this->line("⏭️  Log skipped (disabled for user #{$device->user_id})");
            } else {
                // 2. Check log interval throttle (per device + widget)
                $interval = $user ? $user->getLogInterval() : 0;
                $shouldLog = true;

                if ($interval > 0) {
                    $throttleKey = "log_throttle:{$device->id}:{$widgetKey}";
                    if (Cache::has($throttleKey)) {
                        $shouldLog = false;
                        $this->line("⏱️  Log throttled [{$widgetKey}] interval={$interval}s");
                    } else {
                        // Mark this widget as "just logged", expires after interval seconds
                        Cache::put($throttleKey, true, $interval);
                    }
                }

                if ($shouldLog) {
                    DeviceLog::create([
                        'device_id' => $device->id,
                        'widget_key' => $widgetKey,
                        'event_type' => $direction === 'control' ? 'control' : 'telemetry',
                        'new_value' => (string)$newValue,
                        'source' => 'mqtt'
                    ]);
                }
            }
        }

        $this->info("✅ {$widgetKey} -> {$newValue}");
    }

    private function findWidgetKey($widgetsData, $topicKey)
    {

        if (isset($widgetsData[$topicKey])) {
            return $topicKey;
        }

        foreach ($widgetsData as $key => $w) {

            if (
                isset($w['type_index']) &&
                strtolower($w['type_index']) === strtolower($topicKey)
            ) {
                return $key;
            }

            if (
                isset($w['name']) &&
                strtolower($w['name']) === strtolower($topicKey)
            ) {
                return $key;
            }

        }

        return null;
    }

    private function formatValueForType($type, $value, $widget)
    {

        switch ($type) {

            case 'toggle':

                $v = strtolower(trim($value));

                if (in_array($v, ['1','true','on','yes'])) return '1';
                if (in_array($v, ['0','false','off','no'])) return '0';

                return false;

            case 'slider':
            case 'gauge':
            case 'chart':

                if (!is_numeric($value)) return false;

                $precision = $widget['config']['precision'] ?? 0;

                return number_format((float)$value, $precision, '.', '');

            case 'text':
            default:

                return substr((string)$value,0,255);
        }
    }
}