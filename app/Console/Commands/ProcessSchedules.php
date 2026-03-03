<?php

namespace App\Console\Commands;

use App\Models\Widget;
use App\Services\MqttService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process widget schedules and trigger actions via MQTT';

    /**
     * Execute the console command.
     */
    public function handle(MqttService $mqttService)
    {
        $now = now();
        $currentTime = $now->format('H:i');
        $currentDay = $now->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

        $this->info("Processing schedules for {$currentTime} (Day: {$currentDay}) [TZ: " . config('app.timezone') . "]");

        $widgetConfigs = Widget::with('device.user')->get();
        $triggeredCount = 0;

        foreach ($widgetConfigs as $config) {
            $device = $config->device;
            if (!$device) continue;

            $widgetsData = $config->widgets_data;
            $changed = false;

            foreach ($widgetsData as $key => &$widget) {
                if (!isset($widget['config']['schedules']) || !is_array($widget['config']['schedules'])) {
                    continue;
                }

                foreach ($widget['config']['schedules'] as $schedule) {
                    // Check if enabled
                    if (!($schedule['enabled'] ?? false)) {
                        continue;
                    }

                    // Check time (strip seconds if any)
                    $scheduleTime = date('H:i', strtotime($schedule['time']));
                    
                    // DEBUG: Log mismatch
                    if ($scheduleTime !== $currentTime) {
                        $this->line("   -> Skip: schedule={$scheduleTime} != now={$currentTime}");
                        continue;
                    }

                    // Check day
                    if (isset($schedule['days']) && is_array($schedule['days'])) {
                        if (!in_array((string)$currentDay, $schedule['days'])) {
                            continue;
                        }
                    }

                    // Trigger action!
                    $value = $schedule['value'];
                    
                    $this->info("Triggering schedule for widget [{$key}] in device [{$device->device_code}]: Value = {$value}");
                    Log::info("⏰ Scheduled automation triggered", [
                        'device' => $device->device_code,
                        'widget' => $key,
                        'value' => $value
                    ]);

                    // (1) Update DB
                    $widget['value'] = (string)$value;
                    $widget['updated_at'] = now()->toISOString();
                    $changed = true;

                    // (2) Publish CONTROL to MQTT (for device)
                    try {
                        $mqttService->publishWidgetControl(
                            $device->user_id,
                            $device->device_code,
                            $key,
                            $value
                        );
                        
                        // (3) SIMULATE DEVICE FEEDBACK (Publish to SENSORS topic)
                        // This allows the dashboard to update in real-time even without a physical device connected
                        // echoing the state back.
                        $sensorTopic = "users/{$device->user_id}/devices/{$device->device_code}/sensors/{$key}";
                        $mqttService->publishToTopic(
                            $sensorTopic,
                            (string)$value
                        );

                        $triggeredCount++;
                    } catch (\Exception $e) {
                        Log::error("❌ Failed to publish scheduled MQTT message", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            if ($changed) {
                $config->widgets_data = $widgetsData;
                $config->save();
            }
        }

        $this->info("Done. Triggered {$triggeredCount} schedules.");
    }
}
