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
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('user')->after('password');
        $table->string('subscription_plan')->default('free')->after('role');
        $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
    });
}
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['role', 'subscription_plan', 'subscription_expires_at']);
    });
}
};
