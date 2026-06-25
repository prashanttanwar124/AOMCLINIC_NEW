<?php

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login from admin booking routes', function () {
    $this->get(route('admin.appointments.book'))->assertRedirect(route('login'));
    $this->post(route('admin.appointments.book.store'), [])->assertRedirect(route('login'));
});

test('authenticated staff can search patients dynamically', function () {
    $user = User::factory()->create();
    $patient1 = Patient::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $patient2 = Patient::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $response = $this->actingAs($user)->get(route('admin.patients.search', ['query' => 'John']));

    $response->assertSuccessful();
    $response->assertJsonCount(1);
    $response->assertJsonFragment([
        'id' => $patient1->id,
        'name' => 'John Doe',
    ]);
});

test('authenticated staff can load admin booking form', function () {
    $user = User::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    $response = $this->actingAs($user)->get(route('admin.appointments.book'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BookAppointment')
            ->has('appointmentTypes', 2)
            ->has('dateOptions', 3)
        );
});

test('admin booking creates appointment successfully', function () {
    Illuminate\Support\Facades\Event::fake([
        App\Events\QueueUpdated::class,
    ]);

    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    $response = $this->actingAs($user)->post(route('admin.appointments.book.store'), [
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Staff booked appointment',
    ]);

    $response->assertRedirect(route('booking'));

    $appointment = Appointment::where('patient_id', $patient->id)->first();
    expect($appointment)->not->toBeNull()
        ->and($appointment->appointment_number)->toBe('1')
        ->and($appointment->slot)->toBe(Appointment::SLOT_MORNING)
        ->and($appointment->purpose_of_appointment)->toBe('Staff booked appointment');

    Illuminate\Support\Facades\Event::assertDispatched(App\Events\QueueUpdated::class);
});

test('admin booking works even when online patient booking is disabled globally', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => false, // patient cannot book
        'booking_open_days' => 3,
    ]);

    $response = $this->actingAs($user)->post(route('admin.appointments.book.store'), [
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 2,
        'appointment_type' => 'follow_up',
    ]);

    $response->assertRedirect(route('booking'));

    $appointment = Appointment::where('patient_id', $patient->id)->first();
    expect($appointment)->not->toBeNull()
        ->and($appointment->appointment_number)->toBe('2');
});

test('admin booking checks duplicate booking for the same patient', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    // First booking
    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
    ]);

    // Try booking again for the same date/session
    $response = $this->actingAs($user)->post(route('admin.appointments.book.store'), [
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 2,
        'appointment_type' => 'new',
    ]);

    $response->assertSessionHasErrors('appointment_session');
    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

test('admin booking can bypass clinic closures', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $closedDate = today()->addDay()->toDateString();

    BookingSetting::query()->create([
        'booking_enabled' => true,
        'booking_open_days' => 3,
        'clinic_closures' => [
            ['date' => $closedDate, 'slot' => ['Morning']],
        ],
    ]);

    $response = $this->actingAs($user)->post(route('admin.appointments.book.store'), [
        'patient_id' => $patient->id,
        'appointment_date' => $closedDate,
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
    ]);

    $response->assertRedirect(route('booking'));
    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

test('admin booking can overbook beyond capacity', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 1, // Only 1 token allowed for patients
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    // Already 1 appointment booked
    Appointment::factory()->create([
        'appointment_date' => today()->toDateString(),
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
    ]);

    // Admin books token 2 (which is beyond capacity of 1)
    $response = $this->actingAs($user)->post(route('admin.appointments.book.store'), [
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 2,
        'appointment_type' => 'new',
    ]);

    $response->assertRedirect(route('booking'));
    
    $appointment = Appointment::where('patient_id', $patient->id)->first();
    expect($appointment)->not->toBeNull()
        ->and($appointment->appointment_order)->toBe(2);
});
