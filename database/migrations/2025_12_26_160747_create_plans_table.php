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
        Schema::create('plans', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // free, pro, enterprise
            $table->string('name');
            $table->integer('max_devices')->default(2);
            $table->integer('max_widgets_per_device')->default(5);
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
