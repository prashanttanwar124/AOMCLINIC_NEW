<?php

use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('appointments table has expected columns', function () {
    expect(Schema::hasTable('appointments'))->toBeTrue()
        ->and(Schema::hasColumns('appointments', [
            'id',
            'patient_id',
            'appointment_date',
            'appointment_number',
            'appointment_order',
            'slot',
            'appointment_type',
            'payment_type',
            'amount',
            'purpose_of_appointment',
            'medicines',
            'medicine_status',
            'follow_up_day',
            'follow_up',
            'follow_up_message_sent',
            'days_prescription',
            'pysical_examination',
            'status',
            'on_hold',
            'hold_order',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('booking settings table has expected columns and defaults', function () {
    expect(Schema::hasTable('booking_settings'))->toBeTrue()
        ->and(Schema::hasColumns('booking_settings', [
            'id',
            'morning_slot_capacity',
            'evening_slot_capacity',
            'booking_enabled',
            'booking_open_days',
            'morning_opening_time',
            'morning_closing_time',
            'evening_opening_time',
            'evening_closing_time',
            'clinic_closures',
            'closed_days',
            'notice_enabled',
            'notice_text',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    DB::table('booking_settings')->insert([
        'clinic_closures' => json_encode([
            ['slot' => ['Evening', 'Morning'], 'date' => '2026-03-15'],
        ], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $settings = DB::table('booking_settings')->first();

    expect($settings)->not->toBeNull()
        ->and((int) $settings->morning_slot_capacity)->toBe(60)
        ->and((int) $settings->evening_slot_capacity)->toBe(20)
        ->and((int) $settings->booking_enabled)->toBe(1)
        ->and((int) $settings->booking_open_days)->toBe(2)
        ->and((int) $settings->notice_enabled)->toBe(0);
});

test('appointments table stores defaults and allows repeated reference numbers across days', function () {
    $patient = Patient::factory()->create();

    DB::table('appointments')->insert([
        'patient_id' => $patient->id,
        'appointment_date' => '2026-03-22',
        'appointment_number' => '11',
        'appointment_order' => 11,
        'slot' => 'M',
        'appointment_type' => 'Follow Up',
        'amount' => '333',
        'medicines' => json_encode([
            ['id' => '1473 | 5 | 3', 'label' => 'SL FOR 15 DAYS | 0'],
        ], JSON_THROW_ON_ERROR),
        'medicine_status' => true,
        'status' => 'complete',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('appointments')->insert([
        'patient_id' => $patient->id,
        'appointment_date' => '2026-03-23',
        'appointment_number' => '11',
        'appointment_order' => 11,
        'slot' => 'M',
        'appointment_type' => 'Follow Up',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $appointment = DB::table('appointments')
        ->where('appointment_date', '2026-03-22')
        ->where('slot', 'M')
        ->where('appointment_order', 11)
        ->first();

    expect($appointment)->not->toBeNull()
        ->and($appointment->payment_type)->toBe('Cash')
        ->and((float) $appointment->amount)->toBe(333.0)
        ->and((int) $appointment->follow_up)->toBe(0)
        ->and((int) $appointment->follow_up_message_sent)->toBe(0)
        ->and((int) $appointment->days_prescription)->toBe(0)
        ->and((int) $appointment->on_hold)->toBe(0)
        ->and((int) $appointment->medicine_status)->toBe(1)
        ->and(DB::table('appointments')->where('appointment_number', '11')->count())->toBe(2);
});

test('appointments table blocks duplicate slot sequence for same day and session', function () {
    $patient = Patient::factory()->create();

    DB::table('appointments')->insert([
        'patient_id' => $patient->id,
        'appointment_date' => '2026-03-22',
        'appointment_number' => '11',
        'appointment_order' => 11,
        'slot' => 'M',
        'appointment_type' => 'Follow Up',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function () use ($patient): void {
        DB::table('appointments')->insert([
            'patient_id' => $patient->id,
            'appointment_date' => '2026-03-22',
            'appointment_number' => '99',
            'appointment_order' => 11,
            'slot' => 'M',
            'appointment_type' => 'New',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});
