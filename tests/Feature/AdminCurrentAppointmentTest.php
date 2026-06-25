<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view today current appointments with queue summary', function () {
    $user = User::factory()->create();
    $today = today()->toDateString();

    $runningPatient = Patient::factory()->create([
        'name' => 'Asha Gupta',
        'phone' => '9876543210',
        'country_calling_code' => '91',
    ]);

    $holdPatient = Patient::factory()->create([
        'name' => 'Ravi Patel',
        'phone' => '9991112233',
        'country_calling_code' => '91',
    ]);

    $runningAppointment = Appointment::factory()->create([
        'patient_id' => $runningPatient->id,
        'appointment_date' => $today,
        'appointment_number' => '1',
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
        'on_hold' => false,
        'purpose_of_appointment' => 'Migraine review',
    ]);

    Appointment::factory()->create([
        'patient_id' => $holdPatient->id,
        'appointment_date' => $today,
        'appointment_number' => '2',
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
        'on_hold' => true,
        'hold_order' => 1,
        'purpose_of_appointment' => 'Detailed checkup',
    ]);

    Appointment::factory()->create([
        'appointment_date' => today()->addDay()->toDateString(),
        'appointment_number' => '7',
        'appointment_order' => 7,
        'slot' => Appointment::SLOT_EVENING,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('booking'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Booking')
            ->where('summary.total', 2)
            ->where('summary.running', 1)
            ->where('summary.onHold', 1)
            ->where('currentAppointmentIds.Morning', $runningAppointment->id)
            ->has('appointments', 2)
            ->where('appointments.0.patientName', 'Asha Gupta')
            ->where('appointments.0.queueStatus', 'running')
            ->where('appointments.1.queueStatus', 'on_hold'));
});

test('admin booking desk ignores custom date query and only returns today appointments', function () {
    $user = User::factory()->create();
    $today = today()->toDateString();
    $tomorrow = today()->addDay()->toDateString();

    $todayPatient = Patient::factory()->create(['name' => 'Today Patient']);

    Appointment::factory()->create([
        'patient_id' => $todayPatient->id,
        'appointment_date' => $today,
        'appointment_number' => '1',
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    $tomorrowPatient = Patient::factory()->create(['name' => 'Future Patient']);

    Appointment::factory()->create([
        'patient_id' => $tomorrowPatient->id,
        'appointment_date' => $tomorrow,
        'appointment_number' => '2',
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_EVENING,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('booking', ['date' => $tomorrow]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Booking')
            ->where('appointmentDate', $today)
            ->where('today', $today)
            ->has('appointments', 1)
            ->where('appointments.0.patientName', 'Today Patient')
            ->missing('availableDates'));
});

test('invalid date falls back to today', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('booking', ['date' => 'not-a-date']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointmentDate', today()->toDateString()));
});

test('admin can save edited clinical details without completing', function () {
    $user = User::factory()->create();
    $appointmentDate = today()->toDateString();
    $appointment = Appointment::factory()->create([
        'appointment_date' => $appointmentDate,
        'appointment_number' => '5',
        'appointment_order' => 5,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch(route('booking.update', $appointment), [
        'chief_complaint' => 'Severe migraine since Monday',
        'present_complaint' => 'Throbbing pain',
        'medication_instructions' => 'Take twice daily',
        'medicines' => [
            ['id' => '1473 | 5 | 3', 'label' => 'SL FOR 15 DAYS | 0'],
        ],
        'diagnosis' => 'Tension headache',
        'days_prescription' => 7,
        'amount' => '450.00',
    ]);

    $response->assertRedirect(route('booking', ['date' => $appointmentDate], absolute: false));

    expect($appointment->fresh())
        ->chief_complaint->toBe('Severe migraine since Monday')
        ->present_complaint->toBe('Throbbing pain')
        ->medication_instructions->toBe('Take twice daily')
        ->diagnosis->toBe('Tension headache')
        ->days_prescription->toBe(7)
        ->amount->toBe('450.00')
        ->status->toBe('pending');
});

test('admin can mark an appointment complete which removes it from the queue', function () {
    Illuminate\Support\Facades\Event::fake([
        App\Events\QueueUpdated::class,
    ]);

    $user = User::factory()->create();
    $appointmentDate = today()->toDateString();
    $appointment = Appointment::factory()->create([
        'appointment_date' => $appointmentDate,
        'appointment_number' => '6',
        'appointment_order' => 6,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
        'on_hold' => true,
        'hold_order' => 1,
    ]);

    $response = $this->actingAs($user)->patch(route('booking.update', $appointment), [
        'chief_complaint' => 'Severe headache',
        'present_complaint' => 'Throbbing pain',
        'medication_instructions' => 'Take with warm water',
        'medicines' => [
            ['id' => '1473 | 5 | 3', 'label' => 'SL FOR 15 DAYS | 0'],
        ],
        'amount' => '300.00',
        'treatment' => 'Rest and hydration',
        'complete' => true,
    ]);

    $response->assertRedirect(route('booking', ['date' => $appointmentDate], absolute: false));

    expect($appointment->fresh())
        ->status->toBe('complete')
        ->on_hold->toBeFalse()
        ->hold_order->toBeNull()
        ->treatment->toBe('Rest and hydration')
        ->chief_complaint->toBe('Severe headache')
        ->present_complaint->toBe('Throbbing pain')
        ->medication_instructions->toBe('Take with warm water')
        ->amount->toBe('300.00');

    Illuminate\Support\Facades\Event::assertDispatched(App\Events\QueueUpdated::class, function ($event) {
        return $event->session === 'Morning';
    });
});

test('admin can put an appointment on hold from the current queue', function () {
    Illuminate\Support\Facades\Event::fake([
        App\Events\QueueUpdated::class,
    ]);

    $user = User::factory()->create();
    $appointmentDate = today()->toDateString();
    $appointment = Appointment::factory()->create([
        'appointment_date' => $appointmentDate,
        'appointment_number' => '5',
        'appointment_order' => 5,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
        'on_hold' => false,
    ]);

    $response = $this->actingAs($user)->patch(route('booking.hold.toggle', $appointment), [
        'on_hold' => true,
    ]);

    $response->assertRedirect(route('booking', ['date' => $appointmentDate], absolute: false));

    expect($appointment->fresh())->on_hold->toBeTrue()
        ->and($appointment->fresh()->hold_order)->toBe(1);

    Illuminate\Support\Facades\Event::assertDispatched(App\Events\QueueUpdated::class, function ($event) {
        return $event->session === 'Morning';
    });
});

test('admin can save edited clinical details with medicines array', function () {
    $user = User::factory()->create();
    $appointmentDate = today()->toDateString();
    $appointment = Appointment::factory()->create([
        'appointment_date' => $appointmentDate,
        'appointment_number' => '5',
        'appointment_order' => 5,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch(route('booking.update', $appointment), [
        'chief_complaint' => 'Severe migraine since Monday',
        'present_complaint' => 'Throbbing pain',
        'medication_instructions' => 'Take twice daily',
        'diagnosis' => 'Tension headache',
        'days_prescription' => 7,
        'amount' => '450.00',
        'medicines' => [
            ['id' => '1473 | 5 | 3', 'label' => 'SL FOR 15 DAYS | 0'],
            ['id' => '1474 | 6 | 4', 'label' => 'Nux Vomica 200C 2 dram | 1'],
        ],
    ]);

    $response->assertRedirect(route('booking', ['date' => $appointmentDate], absolute: false));

    $fresh = $appointment->fresh();
    expect($fresh->medicines)->toBeArray()
        ->toHaveCount(2)
        ->and($fresh->medicines[0]['id'])->toBe('1473 | 5 | 3')
        ->and($fresh->medicines[1]['id'])->toBe('1474 | 6 | 4');
});

test('validation fails when required fields are missing on update', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'appointment_date' => today()->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch(route('booking.update', $appointment), []);

    $response->assertSessionHasErrors([
        'chief_complaint',
        'present_complaint',
        'medication_instructions',
        'medicines',
        'amount',
    ]);
});

test('validation fails when required fields are missing on update via json request', function () {
    $user = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'appointment_date' => today()->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patchJson(route('booking.update', $appointment), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'chief_complaint',
            'present_complaint',
            'medication_instructions',
            'medicines',
            'amount',
        ]);
});
