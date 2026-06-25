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
        Schema::create('medical_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_type_id')->constrained('certificate_types')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->date('issue_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('diagnosis')->nullable();
            $table->decimal('charge_amount', 10, 2)->default(0.00);
            $table->string('payment_status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_certificates');
    }
};
