<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Column names mirror the legacy production `appointments` table so the
     * existing 200K+ rows can be imported after a fresh migration. Two extra
     * columns (`on_hold`, `hold_order`) back the admin hold column, and the
     * legacy `pysical_examination` spelling is preserved on purpose.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->date('appointment_date')->nullable()->index();
            $table->string('payment_type')->default('Cash');
            $table->string('amount')->nullable();
            $table->text('purpose_of_appointment')->nullable();
            $table->string('medication_instructions')->nullable();
            $table->text('associated_complaint')->nullable();
            $table->string('past_history')->nullable();
            $table->string('family_history_father_side')->nullable();
            $table->string('family_history_mother_side')->nullable();
            $table->string('addiction')->nullable();
            $table->string('diet')->nullable();
            $table->string('occupation')->nullable();
            $table->string('number_of_children')->nullable();
            $table->string('medicine_taking')->nullable();
            $table->string('appetite')->nullable();
            $table->string('thirst')->nullable();
            $table->string('sleep')->nullable();
            $table->string('urine')->nullable();
            $table->string('stool')->nullable();
            $table->string('pysical_examination')->nullable();
            $table->text('history_of_vaccination')->nullable();
            $table->string('as_a_person')->nullable();
            $table->string('nature_of_person')->nullable();
            $table->string('anxiety')->nullable();
            $table->string('fear')->nullable();
            $table->string('nature')->nullable();
            $table->string('dreams')->nullable();
            $table->string('desire')->nullable();
            $table->string('craving')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('present_complaint')->nullable();
            $table->string('menses_regular')->nullable();
            $table->string('menses_duration')->nullable();
            $table->string('menses_flow_complaints')->nullable();
            $table->string('menses_medicine_taking_menses')->nullable();
            $table->date('follow_up_day')->nullable();
            $table->integer('follow_up')->default(0);
            $table->boolean('follow_up_message_sent')->default(false);
            $table->integer('days_prescription')->default(0);
            $table->text('treatment')->nullable();
            $table->text('diagnosis')->nullable();
            $table->bigInteger('patient_id')->nullable();
            $table->string('appointment_type');
            $table->bigInteger('appointment_order');
            $table->string('appointment_number');
            $table->string('slot')->nullable();
            $table->text('medicines')->nullable();
            $table->boolean('medicine_status')->default(false);
            $table->string('status')->default('pending')->index();

            // Admin hold column: held cards move to the right column.
            $table->boolean('on_hold')->default(false);
            $table->unsignedInteger('hold_order')->nullable();

            $table->timestamps();

            $table->unique(['appointment_date', 'slot', 'appointment_order'], 'appointments_slot_order_unique');
            $table->index(['patient_id', 'appointment_date']);
            $table->index(['appointment_date', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
