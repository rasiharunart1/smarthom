<?php

namespace App\Console\Commands;

    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;

    class AggregateLogs extends Command
    {
        protected $signature = 'logs:aggregate {level=all : The aggregation level (5min, hourly, daily, all)}';
        protected $description = 'Aggregate device logs into time buckets (5min, hourly, daily)';

        public function handle()
        {
            $level = $this->argument('level');

            if ($level === 'all' || $level === '5min') {
                $this->aggregate5Min();
            }
            if ($level === 'all' || $level === 'hourly') {
                $this->aggregateHourly();
            }
            if ($level === 'all' || $level === 'daily') {
                $this->aggregateDaily();
            }

            $this->info("Aggregation [$level] completed successfully.");
        }

        private function aggregate5Min()
        {
            // Summarize raw logs created in the last 10 minutes into 5-minute buckets
            // We focus on completed buckets, so we look at data strictly before the current started bucket.
            $start = now()->subMinutes(15)->format('Y-m-d H:i:00'); 
            $end = now()->format('Y-m-d H:i:00');

            // Insert ignore to skip already aggregated buckets
            // Insert ignore to skip already aggregated buckets
            $query = "
                INSERT INTO device_log_5min (device_id, widget_key, avg_value, min_value, max_value, bucket_time, created_at, updated_at)
                SELECT 
                    device_id,
                    widget_key,
                    AVG(CAST(new_value AS DECIMAL(10,2))) as avg_val,
                    MIN(CAST(new_value AS DECIMAL(10,2))) as min_val,
                    MAX(CAST(new_value AS DECIMAL(10,2))) as max_val,
                    -- Create 5-min bucket time: Round down to nearest 5 min
                    to_timestamp(floor((extract('epoch' from created_at) / 300 )) * 300) as bucket_time,
                    NOW(),
                    NOW()
                FROM device_logs
                WHERE created_at >= ? AND created_at < ?
                  AND event_type = 'telemetry' -- Only aggregate telemetry data
                GROUP BY device_id, widget_key, bucket_time
                ON CONFLICT (device_id, widget_key, bucket_time) DO NOTHING;
            ";

            DB::statement($query, [$start, $end]);
            $this->info("Processed 5-min aggregation.");
        }

        private function aggregateHourly()
        {
            // Summarize 5-min logs into 1-hour buckets for the last 2 hours
            $start = now()->subHours(6)->format('Y-m-d H:00:00');
            $end = now()->format('Y-m-d H:00:00');

            $query = "
                INSERT INTO device_log_hourly (device_id, widget_key, avg_value, min_value, max_value, bucket_time, created_at, updated_at)
                SELECT 
                    device_id,
                    widget_key,
                    AVG(avg_value) as avg_val,
                    MIN(min_value) as min_val,
                    MAX(max_value) as max_val,
                    date_trunc('hour', bucket_time) as bucket_time,
                    NOW(),
                    NOW()
                FROM device_log_5min
                WHERE bucket_time >= ? AND bucket_time < ?
                GROUP BY device_id, widget_key, date_trunc('hour', bucket_time)
                ON CONFLICT (device_id, widget_key, bucket_time) DO NOTHING;
            ";

            DB::statement($query, [$start, $end]);
            $this->info("Processed hourly aggregation.");
        }

        private function aggregateDaily()
        {
            // Summarize hourly logs into daily buckets for the last 2 days
            $start = now()->subDays(2)->format('Y-m-d 00:00:00');
            $end = now()->format('Y-m-d 00:00:00');

            $query = "
                INSERT INTO device_log_daily (device_id, widget_key, avg_value, min_value, max_value, bucket_time, created_at, updated_at)
                SELECT 
                    device_id,
                    widget_key,
                    AVG(avg_value) as avg_val,
                    MIN(min_value) as min_val,
                    MAX(max_value) as max_val,
                    date_trunc('day', bucket_time) as bucket_time,
                    NOW(),
                    NOW()
                FROM device_log_hourly
                WHERE bucket_time >= ? AND bucket_time < ?
                GROUP BY device_id, widget_key, date_trunc('day', bucket_time)
                ON CONFLICT (device_id, widget_key, bucket_time) DO NOTHING;
            ";

            DB::statement($query, [$start, $end]);
            $this->info("Processed daily aggregation.");
        }
    }
