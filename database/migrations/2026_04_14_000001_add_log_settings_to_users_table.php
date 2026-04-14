<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds admin-controllable log settings to each user:
     *  - log_enabled   : whether telemetry logging is active for this user
     *  - log_interval  : minimum seconds between log entries per widget (0 = every change)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('log_enabled')->default(true)->after('lstm_allowed');
            $table->unsignedSmallInteger('log_interval')->default(0)->after('log_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['log_enabled', 'log_interval']);
        });
    }
};
