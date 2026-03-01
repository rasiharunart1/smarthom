<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\DeviceLog;
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
        $this->info('🎯 Starting MQTT Listener (Recording Enabled)');

        try {
            $this->connectToMqtt();
            $this->subscribeToTopics();
            $this->mqtt->loop(true);
        } catch (\Exception $e) {
            $this->error('❌ MQTT Error: ' . $e->getMessage());
            Log::error('MQTT Listener error: ' . $e->getMessage());
        }
    }

    private function connectToMqtt()
    {
        $host = env('MQTT_HOST');
        $port = (int) env('MQTT_PORT', 1883);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');

        $settings = (new ConnectionSettings())
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls((bool) env('MQTT_USE_TLS', false));

        $this->mqtt = new MqttClient($host, $port, 'laravel-listener-' . time());
        $this->mqtt->connect($settings);

        $this->info("✅ Connected to MQTT {$host}:{$port}");
    }

    private function subscribeToTopics()
    {
        // Topik sensors fleksibel
        $sensorsTopic = 'users/+/devices/+/sensors/+';
        $this->mqtt->subscribe($sensorsTopic, function ($topic, $message) {
            $this->handleMessage($topic, $message);
        }, 1);
        $this->info("📡 Subscribed to: {$sensorsTopic}");

        // Topik control (Sekarang dihandle juga untuk update status widget di DB)
        $controlTopic = 'users/+/devices/+/control/+';
        $this->mqtt->subscribe($controlTopic, function ($topic, $message) {
            $this->handleMessage($topic, $message);
        }, 1);
        $this->info("📡 Subscribed to: {$controlTopic}");

        $this->info("🔄 Listening & Recording...\n");
    }

    private function handleMessage($topic, $message)
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->line("[$timestamp] 📥 {$topic} = {$message}");

        try {
            // Regex topic: users/{userId}/devices/{deviceCode}/{type}/{widgetName}
            if (!preg_match('/^users\/(\d+)\/devices\/([^\/]+)\/(sensors|control)\/(.+)$/', $topic, $matches)) {
                $this->warn("⚠️ Invalid topic format");
                return;
            }

            $userId = (int)$matches[1];
            $deviceCode = $matches[2];
            $topicType = $matches[3]; // sensors or control
            $widgetPart = trim($matches[4]);

            $device = Device::with('widget')
                ->where('device_code', $deviceCode)
                ->where('user_id', $userId)
                ->first();

            if (!$device) {
                $this->warn("❌ Device not found: {$deviceCode}");
                return;
            }

            if (!$device->widget) {
                $this->warn("❌ No widget config for: {$device->name}");
                return;
            }

            $device->markAsOnline();

            $widgetsData = $device->widget->widgets_data ?? [];

            $topicKeyOrNameOrIndex = trim($matches[4]); // widgetPart dari regex
            $widgetKey = null;

            // 1. Cek apakah topic part terakhir adalah KEY widget (ID)
            if (isset($widgetsData[$topicKeyOrNameOrIndex])) {
                $widgetKey = $topicKeyOrNameOrIndex;
            } else {
                // 2. Jika tidak, fallback cari berdasarkan type_index atau NAMA widget
                foreach ($widgetsData as $key => $w) {
                    // Cek explicit type_index (misal: toggle1)
                    if (isset($w['type_index']) && strtolower(trim($w['type_index'])) === strtolower($topicKeyOrNameOrIndex)) {
                        $widgetKey = $key;
                        break;
                    }
                    
                    // Fallback lagi ke NAMA (legacy)
                    if (strtolower(trim($w['name'] ?? '')) === strtolower($topicKeyOrNameOrIndex)) {
                        $widgetKey = $key;
                        break;
                    }
                }
            }

            if (!$widgetKey) {
                // Log failed attempt but don't crash
                // Maybe create a log without widget key? No, that's useless.
                $availableNames = implode(', ', array_map(fn($w) => ($w['name'] ?? '-') . " (" . ($w['type_index'] ?? 'no-idx') . ")", $widgetsData));
                $this->warn("❌ Widget '{$topicKeyOrNameOrIndex}' not found");
                return;
            }

            $widget = $widgetsData[$widgetKey];
            $type = $widget['type'] ?? 'text';
            $oldValue = $widget['value'] ?? null;

            $newValue = $this->formatValueForType($type, $message, $widget);

            if ($newValue === false) {
                $this->warn("❌ Invalid value format for type: {$type}");
                return;
            }

            // Update widget Value (Last Known State)
            $device->widget->updateWidgetValue($widgetKey, $newValue);

            // Update cache
            Cache::put("device:{$deviceCode}:updates", [
                'widgetKey' => $widgetKey,
                'newValue' => $newValue,
                'type' => $widget['type'] ?? 'text',
                'timestamp' => now()->toISOString(),
            ], 60);

            // ==========================================
            // INSERT INTO DEVICE LOGS (HISTORY)
            // ==========================================
            // If LSTM is active for this device, SKIP saving here.
            // usage: $device->isLstmActive()
            // The Python Service (server.py) will handle the storage to avoid double logging.
            if ($device->isLstmActive()) {
                 $this->info("⏩ Skipped Log (Handled by LSTM Service): {$widgetKey} -> {$newValue}");
            } else {
                DeviceLog::create([
                    'device_id' => $device->id,
                    'widget_key' => $widgetKey,
                    'event_type' => $topicType === 'control' ? 'control' : 'telemetry',
                    'new_value' => (string)$newValue,
                    'source' => 'MQTT Listener',
                ]);
                $this->info("✅ Log Saved: {$widgetKey} -> {$newValue}");
            }

            $device->refresh();

        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
        }
    }

    private function formatValueForType($type, $value, $widget)
    {
        switch ($type) {
            case 'toggle':
                $lower = strtolower(trim($value));
                if (in_array($lower, ['1', 'true', 'on', 'yes', 'high'])) return '1';
                if (in_array($lower, ['0', 'false', 'off', 'no', 'low'])) return '0';
                return false;

            case 'slider':
            case 'gauge':
            case 'chart': // Chart often is numeric
                if (!is_numeric($value)) return false;

                $numValue = (float)$value;
                $min = $widget['min'] ?? null;
                $max = $widget['max'] ?? null;

                // Optional: Clamp or Reject? For logs, maybe just log it even if OOB?
                // The user logic rejected it. I'll stick to their logic.
                if ($min !== null && $numValue < $min) $this->warn("Value $numValue < min $min");
                if ($max !== null && $numValue > $max) $this->warn("Value $numValue > max $max");
                // Allowing OOB for logging is often better, but let's trust existing logic for now.
                
                $precision = $widget['config']['precision'] ?? 0;
                return number_format($numValue, $precision, '.', '');

            case 'text':
            default:
                return substr((string)$value, 0, 255);
        }
    }
}
