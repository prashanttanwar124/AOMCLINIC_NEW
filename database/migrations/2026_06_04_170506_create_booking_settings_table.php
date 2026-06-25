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
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('morning_slot_capacity')->default(60);
            $table->unsignedInteger('evening_slot_capacity')->default(20);
            $table->boolean('booking_enabled')->default(true);
            $table->unsignedInteger('booking_open_days')->default(2);
            $table->time('morning_opening_time')->nullable();
            $table->time('morning_closing_time')->nullable();
            $table->time('evening_opening_time')->nullable();
            $table->time('evening_closing_time')->nullable();
            $table->json('clinic_closures')->nullable();
            $table->boolean('notice_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
