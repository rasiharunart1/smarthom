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
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'lstm_enabled')) {
                $table->dropColumn('lstm_enabled');
            }
            if (Schema::hasColumn('devices', 'lstm_config')) {
                $table->dropColumn('lstm_config');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lstm_allowed')) {
                $table->dropColumn('lstm_allowed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('lstm_enabled')->default(false)->after('metadata');
            $table->json('lstm_config')->nullable()->after('lstm_enabled');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('lstm_allowed')->default(false)->after('role');
        });
    }
};
