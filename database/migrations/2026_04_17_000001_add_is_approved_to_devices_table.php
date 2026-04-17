<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Default TRUE agar semua device yang sudah ada tetap bisa digunakan
            $table->boolean('is_approved')->default(true)->after('metadata');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
        });

        // Set default FALSE hanya untuk device yang dibuat SETELAH migration ini
        // (device lama tetap true agar tidak merusak sistem yang berjalan)
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['is_approved', 'approved_at', 'approved_by']);
        });
    }
};
