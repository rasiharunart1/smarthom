<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->foreignId('widget_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // status_change, value_update, connection, etc
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'created_at']);
            $table->index('event_type');
        });

        // Convert to TimescaleDB hypertable (optional, jika sudah install TimescaleDB)
        // DB::statement("SELECT create_hypertable('device_logs', 'created_at')");
    }

    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
