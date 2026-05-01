<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-device log interval override to devices table.
     * When set (not null), this overrides the user-level log_interval.
     * When null, the device falls back to its owner's log_interval setting.
     *
     *   Priority: device.log_interval (not null) > user.log_interval
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('log_interval')
                  ->nullable()
                  ->default(null)
                  ->after('is_approved')
                  ->comment('Per-device log throttle (seconds). Null = inherit from user setting.');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('log_interval');
        });
    }
};
