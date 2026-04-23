<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-device MQTT credentials to devices table.
 *
 * Architecture: Each device gets its own MQTT username/password
 * provisioned via HiveMQ Cloud REST API. This prevents credential
 * sharing — if one device is compromised, only that device is affected.
 *
 * Falls back to shared credentials (config mqtt.*) if not provisioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Per-device MQTT credentials (nullable = falls back to shared creds)
            $table->string('mqtt_username', 100)->nullable()->after('device_code');
            $table->string('mqtt_password', 255)->nullable()->after('mqtt_username');

            // API token for device-to-server REST calls (Sanctum)
            // Stored in personal_access_tokens table — this tracks last token issuance
            $table->timestamp('token_issued_at')->nullable()->after('mqtt_password');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['mqtt_username', 'mqtt_password', 'token_issued_at']);
        });
    }
};
