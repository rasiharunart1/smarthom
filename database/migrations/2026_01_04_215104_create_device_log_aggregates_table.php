<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['device_log_5min', 'device_log_hourly', 'device_log_daily'];

        foreach ($tables as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
                $table->string('widget_key');
                $table->double('avg_value')->nullable();
                $table->double('min_value')->nullable();
                $table->double('max_value')->nullable();
                $table->timestamp('bucket_time')->index(); // The start time of the bucket
                $table->timestamps();

                // Unique index to prevent duplicate buckets for same widget
                $table->unique(['device_id', 'widget_key', 'bucket_time'], $table->getTable().'_unique_bucket');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_log_5min');
        Schema::dropIfExists('device_log_hourly');
        Schema::dropIfExists('device_log_daily');
    }
};
