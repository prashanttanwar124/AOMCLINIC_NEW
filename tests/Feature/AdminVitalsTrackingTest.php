<?php

use App\Models\Appointment;
use App\Models\AppointmentVital;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login from vitals tracking', function () {
    $this->get(route('admin.vitals-tracking'))->assertRedirect(route('login'));
});

test('authenticated staff can view today vitals tracking workspace', function () {
    $user = User::factory()->create();
    $today = today()->toDateString();

    $patient1 = Patient::factory()->create(['name' => 'John Doe']);
    $patient2 = Patient::factory()->create(['name' => 'Jane Smith']);

    $apt1 = Appointment::factory()->create([
        'patient_id' => $patient1->id,
        'appointment_date' => $today,
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    $apt2 = Appointment::factory()->withVitals([
        'temperature' => '98.5',
        'weight' => '70',
    ])->create([
        'patient_id' => $patient2->id,
        'appointment_date' => $today,
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    // A completed appointment which should be filtered out
    $patient3 = Patient::factory()->create(['name' => 'Bob Johnson']);
    $apt3 = Appointment::factory()->create([
        'patient_id' => $patient3->id,
        'appointment_date' => $today,
        'appointment_order' => 3,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'complete',
    ]);

    $response = $this->actingAs($user)->get(route('admin.vitals-tracking'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/VitalsTracking')
            ->has('appointments', 2)
            ->where('appointments.0.patientName', 'John Doe')
            ->where('appointments.0.hasVitals', false)
            ->where('appointments.1.patientName', 'Jane Smith')
            ->where('appointments.1.hasVitals', true)
            ->where('selectedId', $apt1->id)
        );
});

test('authenticated staff can record and update patient vitals', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $apt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'pending',
    ]);

    $vitalsPayload = [
        'temperature' => '98.6',
        'weight' => '65.2',
        'blood_pressure' => '120/80',
        'pulse_rate' => '72',
        'spo2' => '98',
        'notes' => 'Patient looks healthy.',
    ];

    $response = $this->actingAs($user)->patch(route('admin.vitals-tracking.update', $apt), $vitalsPayload);

    $response->assertRedirect(route('admin.vitals-tracking', ['selected' => $apt->id]));

    $vital = AppointmentVital::where('appointment_id', $apt->id)->first();
    expect($vital)->not->toBeNull()
        ->and($vital->temperature)->toBe('98.6')
        ->and($vital->weight)->toBe('65.2')
        ->and($vital->blood_pressure)->toBe('120/80')
        ->and($vital->pulse_rate)->toBe('72')
        ->and($vital->spo2)->toBe('98')
        ->and($vital->notes)->toBe('Patient looks healthy.');
});

test('vitals are loaded correctly on the doctor booking desk', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    
    $apt = Appointment::factory()->withVitals([
        'temperature' => '99.1',
        'weight' => '80',
        'blood_pressure' => '130/85',
        'pulse_rate' => '80',
        'spo2' => '96',
        'notes' => 'Slight fever',
    ])->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('booking'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Booking')
            ->has('appointments', 1)
            ->where('appointments.0.details.vitals.temperature', '99.1')
            ->where('appointments.0.details.vitals.notes', 'Slight fever')
            ->where('appointments.0.editable.temperature', '99.1')
            ->where('appointments.0.editable.notes', 'Slight fever')
        );
});

test('doctors can update patient vitals from the booking desk', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    
    $apt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'pending',
    ]);

    $updatePayload = [
        'chief_complaint' => 'Headache',
        'present_complaint' => 'Fever',
        'medication_instructions' => 'Take paracetamol',
        'medicines' => [
            ['id' => '1473 | 5 | 3', 'label' => 'SL FOR 15 DAYS | 0'],
        ],
        'amount' => '200.00',
        'temperature' => '100.2',
        'weight' => '75',
        'blood_pressure' => '120/80',
        'pulse_rate' => '78',
        'spo2' => '99',
        'notes' => 'Fever recorded',
    ];

    $response = $this->actingAs($user)->patch(route('booking.update', $apt), $updatePayload);

    $response->assertRedirect(route('booking', ['date' => today()->toDateString()]));

    $freshApt = $apt->fresh();
    expect($freshApt->chief_complaint)->toBe('Headache')
        ->and($freshApt->present_complaint)->toBe('Fever')
        ->and($freshApt->medication_instructions)->toBe('Take paracetamol')
        ->and($freshApt->amount)->toBe('200.00');
    
    $vital = AppointmentVital::where('appointment_id', $apt->id)->first();
    expect($vital)->not->toBeNull()
        ->and($vital->temperature)->toBe('100.2')
        ->and($vital->weight)->toBe('75')
        ->and($vital->notes)->toBe('Fever recorded');
});
