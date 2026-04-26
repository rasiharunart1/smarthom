<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * [SECURITY FIX I-6] Add performance indexes to prevent slow queries
     * under high device/sensor load. Without these, log queries do full table scans.
     */
    public function up(): void
    {
        // device_logs — main telemetry table (grows fastest)
        Schema::table('device_logs', function (Blueprint $table) {
            $table->index(['device_id', 'created_at'], 'idx_device_logs_device_time');
            $table->index('widget_key', 'idx_device_logs_widget_key');
        });

        // device_log_5_mins — aggregated table
        if (Schema::hasTable('device_log_5_mins')) {
            Schema::table('device_log_5_mins', function (Blueprint $table) {
                $table->index(['device_id', 'bucket_time'], 'idx_log5m_device_bucket');
            });
        }

        // device_log_hourlies — aggregated table
        if (Schema::hasTable('device_log_hourlies')) {
            Schema::table('device_log_hourlies', function (Blueprint $table) {
                $table->index(['device_id', 'bucket_time'], 'idx_logh_device_bucket');
            });
        }

        // device_log_dailies — aggregated table
        if (Schema::hasTable('device_log_dailies')) {
            Schema::table('device_log_dailies', function (Blueprint $table) {
                $table->index(['device_id', 'bucket_time'], 'idx_logd_device_bucket');
            });
        }

        // device_shares — permission lookups
        if (Schema::hasTable('device_shares')) {
            Schema::table('device_shares', function (Blueprint $table) {
                $table->index(['device_id', 'shared_with_user_id'], 'idx_shares_device_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->dropIndex('idx_device_logs_device_time');
            $table->dropIndex('idx_device_logs_widget_key');
        });

        if (Schema::hasTable('device_log_5_mins')) {
            Schema::table('device_log_5_mins', function (Blueprint $table) {
                $table->dropIndex('idx_log5m_device_bucket');
            });
        }

        if (Schema::hasTable('device_log_hourlies')) {
            Schema::table('device_log_hourlies', function (Blueprint $table) {
                $table->dropIndex('idx_logh_device_bucket');
            });
        }

        if (Schema::hasTable('device_log_dailies')) {
            Schema::table('device_log_dailies', function (Blueprint $table) {
                $table->dropIndex('idx_logd_device_bucket');
            });
        }

        if (Schema::hasTable('device_shares')) {
            Schema::table('device_shares', function (Blueprint $table) {
                $table->dropIndex('idx_shares_device_user');
            });
        }
    }
};
