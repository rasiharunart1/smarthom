<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes for high-frequency query patterns.
 *
 * Targets:
 *  - device_logs         → most-queried table (MQTT writes + dashboard chart reads)
 *  - device_log_5min     → SSE chart history (24h / 7d queries)
 *  - device_log_hourly   → chart history (30d queries)
 *  - device_log_daily    → chart history (1y queries)
 *  - device_shares       → shared device lookup per user
 *  - devices             → device_code lookup (IoT auth + MQTT listener)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── device_logs ──────────────────────────────────────────────────────
        // Query pattern: WHERE device_id = ? AND widget_key = ? ORDER BY created_at DESC
        // Used by: DeviceLogController::index(), history(), export(), AggregateLogs
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_device_logs_composite
            ON device_logs(device_id, widget_key, created_at DESC)
        ");

        // Query pattern: WHERE device_id = ? ORDER BY created_at DESC LIMIT 20
        // Used by: DeviceLogController::index() without widget_key filter
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_device_logs_device_created
            ON device_logs(device_id, created_at DESC)
        ");

        // Query pattern: WHERE event_type = 'telemetry' (AggregateLogs)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_device_logs_event_type
            ON device_logs(event_type, created_at)
            WHERE event_type = 'telemetry'
        ");

        // ─── device_log_5min ──────────────────────────────────────────────────
        // Query pattern: WHERE device_id = ? AND widget_key IN (?) AND bucket_time >= ?
        // Used by: DeviceLogController::history() for 24h / 7d period
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_log_5min_lookup
            ON device_log_5min(device_id, widget_key, bucket_time)
        ");

        // ─── device_log_hourly ────────────────────────────────────────────────
        // Query pattern: WHERE device_id = ? AND widget_key IN (?) AND bucket_time >= ?
        // Used by: DeviceLogController::history() for 30d period
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_log_hourly_lookup
            ON device_log_hourly(device_id, widget_key, bucket_time)
        ");

        // ─── device_log_daily ─────────────────────────────────────────────────
        // Query pattern: WHERE device_id = ? AND widget_key IN (?) AND bucket_time >= ?
        // Used by: DeviceLogController::history() for 1y period
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_log_daily_lookup
            ON device_log_daily(device_id, widget_key, bucket_time)
        ");

        // ─── device_shares ────────────────────────────────────────────────────
        // Query pattern: WHERE shared_with_user_id = ? (dashboard loads shared devices)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_shares_recipient
            ON device_shares(shared_with_user_id)
        ");

        // Query pattern: WHERE device_id = ? (share management page)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_shares_device
            ON device_shares(device_id)
        ");

        // ─── devices ──────────────────────────────────────────────────────────
        // Most critical: device_code used in EVERY MQTT message + every API call
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_code
            ON devices(device_code)
        ");

        // Query pattern: WHERE user_id = ? (dashboard device list)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_devices_user
            ON devices(user_id)
        ");
    }

    public function down(): void
    {
        $indexes = [
            'idx_device_logs_composite',
            'idx_device_logs_device_created',
            'idx_device_logs_event_type',
            'idx_log_5min_lookup',
            'idx_log_hourly_lookup',
            'idx_log_daily_lookup',
            'idx_shares_recipient',
            'idx_shares_device',
            'idx_devices_code',
            'idx_devices_user',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
