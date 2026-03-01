<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->string('source')->default('System')->after('event_type'); // 'System', 'AI Service', 'Manual'
        });
    }

    public function down()
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
