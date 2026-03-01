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
        Schema::table('device_logs', function (Blueprint $table) {
            $table->string('widget_key')->nullable()->after('widget_id');
            $table->index('widget_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->dropColumn('widget_key');
        });
    }
};
