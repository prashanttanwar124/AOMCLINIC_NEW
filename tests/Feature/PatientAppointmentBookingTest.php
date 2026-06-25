<?php

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Patient;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated patients can open the booking page', function () {
    $patient = Patient::factory()->create();
    $bookingDate = today()->toDateString();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 3,
        'evening_slot_capacity' => 2,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    Appointment::factory()->create([
        'appointment_date' => $bookingDate,
        'appointment_number' => '2',
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_MORNING,
    ]);

    $response = $this->actingAs($patient, 'patient')->get(route('patient.appointments.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Booking')
            ->has('appointmentTypes', 2)
            ->has('dateOptions', 3)
            ->where('dateOptions.0.value', $bookingDate)
            ->where('dateOptions.0.sessions.0.availableTokens', [1, 3]));
});

test('patient booking stores the selected available token for the chosen slot', function () {
    Illuminate\Support\Facades\Event::fake([
        App\Events\QueueUpdated::class,
    ]);

    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    Appointment::factory()->create([
        'appointment_date' => today()->toDateString(),
        'appointment_number' => '1',
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
    ]);

    $response = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 4,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Recurring headache and fatigue',
    ]);

    $response->assertRedirect(route('patient.dashboard', absolute: false));

    $appointment = Appointment::query()
        ->whereBelongsTo($patient)
        ->latest('id')
        ->first();

    expect($appointment)->not->toBeNull()
        ->and($appointment->appointment_number)->toBe('4')
        ->and((int) $appointment->appointment_order)->toBe(4)
        ->and($appointment->slot)->toBe(Appointment::SLOT_MORNING)
        ->and($appointment->appointment_type)->toBe('New')
        ->and($appointment->purpose_of_appointment)->toBe('Recurring headache and fatigue');

    Illuminate\Support\Facades\Event::assertDispatched(App\Events\QueueUpdated::class, function ($event) {
        return $event->session === 'Morning';
    });
});

test('patient booking rejects a token that is already taken for the selected slot', function () {
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'evening_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    Appointment::factory()->create([
        'appointment_date' => today()->toDateString(),
        'appointment_number' => '3',
        'appointment_order' => 3,
        'slot' => Appointment::SLOT_MORNING,
    ]);

    $response = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 3,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Need a same-day review',
    ]);

    $response->assertSessionHasErrors('appointment_number');

    expect(Appointment::query()->whereBelongsTo($patient)->count())->toBe(0);
});

test('patient booking is blocked for closed clinic slots', function () {
    $patient = Patient::factory()->create();
    $closedDate = today()->addDay()->toDateString();

    BookingSetting::query()->create([
        'booking_enabled' => true,
        'booking_open_days' => 3,
        'clinic_closures' => [
            ['date' => $closedDate, 'slot' => ['Morning']],
        ],
    ]);

    $response = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => $closedDate,
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'follow_up',
        'reason_for_visit' => 'Review previous prescription',
    ]);

    $response->assertSessionHasErrors('appointment_session');

    expect(Appointment::query()->whereBelongsTo($patient)->count())->toBe(0);
});

test('bookingEnabled and noticeEnabled settings are passed to booking and dashboard pages', function () {
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'booking_enabled' => false,
        'notice_enabled' => true,
        'notice_text' => 'Some notice',
        'booking_open_days' => 2,
    ]);

    $response = $this->actingAs($patient, 'patient')->get(route('patient.appointments.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Booking')
            ->where('bookingEnabled', false)
            ->where('noticeEnabled', true)
            ->where('noticeText', 'Some notice'));

    $dashboardResponse = $this->actingAs($patient, 'patient')->get(route('patient.dashboard'));
    $dashboardResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Dashboard')
            ->where('bookingEnabled', false)
            ->has('appointments')
            ->has('pastAppointments')
            ->has('latestPrescription')
            ->has('clinicStaff')
            ->has('billingInfo'));
});

test('dates with all sessions closed are marked as disabled in dateOptions', function () {
    $patient = Patient::factory()->create();
    $closedDate = today()->addDay()->toDateString();

    BookingSetting::query()->create([
        'booking_enabled' => true,
        'booking_open_days' => 2,
        'clinic_closures' => [
            ['date' => $closedDate, 'slot' => ['Morning', 'Evening']],
        ],
    ]);

    $response = $this->actingAs($patient, 'patient')->get(route('patient.appointments.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Booking')
            ->where('dateOptions.0.disabled', false)
            ->where('dateOptions.1.value', $closedDate)
            ->where('dateOptions.1.disabled', true));
});

test('patient booking is blocked when online booking is disabled globally', function () {
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'booking_enabled' => false,
        'booking_open_days' => 2,
    ]);

    $response = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup',
    ]);

    $response->assertSessionHasErrors('appointment_date');
    expect(Appointment::query()->whereBelongsTo($patient)->count())->toBe(0);
});

test('patient booking is blocked when selected token exceeds capacity or session is full', function () {
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 1,
        'booking_enabled' => true,
        'booking_open_days' => 2,
    ]);

    // Token 2 is outside of morning slot capacity (which is 1)
    $responseExceedToken = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 2,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup',
    ]);

    $responseExceedToken->assertSessionHasErrors('appointment_number');

    // Now book token 1
    $responseValid = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup',
    ]);

    $responseValid->assertRedirect();
    expect(Appointment::query()->count())->toBe(1);

    // Try booking another appointment when capacity (1) is already full
    $anotherPatient = Patient::factory()->create();
    $responseFull = $this->actingAs($anotherPatient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup',
    ]);

    $responseFull->assertSessionHasErrors('appointment_session');
});

test('dates falling on weekly closed days are marked as disabled in dateOptions', function () {
    $patient = Patient::factory()->create();

    BookingSetting::query()->create([
        'booking_enabled' => true,
        'booking_open_days' => 8,
        'closed_days' => [0], // Sunday is closed
    ]);

    $response = $this->actingAs($patient, 'patient')->get(route('patient.appointments.create'));

    $response->assertOk();

    $dateOptions = $response->original->getData()['page']['props']['dateOptions'];

    // Find the Sunday option
    $sundayOption = collect($dateOptions)->first(function ($option) {
        return Carbon\Carbon::parse($option['value'])->isSunday();
    });

    expect($sundayOption)->not->toBeNull()
        ->and($sundayOption['disabled'])->toBeTrue();

    // Also verify that attempting to store an appointment on Sunday fails validation
    $responseStore = $this->actingAs($patient, 'patient')->post(route('patient.appointments.store'), [
        'appointment_date' => $sundayOption['value'],
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup',
    ]);

    $responseStore->assertSessionHasErrors('appointment_session');
});

test('booking page includes bookable family members in props', function () {
    $parent = Patient::factory()->create(['name' => 'Parent Name']);
    $dependent = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Child Dependent',
    ]);

    BookingSetting::query()->create([
        'booking_enabled' => true,
        'booking_open_days' => 1,
    ]);

    // Parent sees themselves and their child
    $responseParent = $this->actingAs($parent, 'patient')->get(route('patient.appointments.create'));
    $responseParent->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Booking')
            ->has('bookablePatients', 2)
            ->where('bookablePatients.0.name', 'Child Dependent')
            ->where('bookablePatients.1.name', 'Parent Name (Self)')
        );

    // Dependent sees themselves and their parent
    $responseChild = $this->actingAs($dependent, 'patient')->get(route('patient.appointments.create'));
    $responseChild->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Booking')
            ->has('bookablePatients', 2)
            ->where('bookablePatients.0.name', 'Child Dependent (Self)')
            ->where('bookablePatients.1.name', 'Parent Name (Account Holder)')
        );
});

test('patient can book an appointment for their dependent', function () {
    $parent = Patient::factory()->create();
    $dependent = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Child Name',
    ]);

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    $response = $this->actingAs($parent, 'patient')->post(route('patient.appointments.store'), [
        'patient_id' => $dependent->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
        'reason_for_visit' => 'Checkup for child',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Verify appointment was created for the dependent
    $appointment = Appointment::query()
        ->where('patient_id', $dependent->id)
        ->first();

    expect($appointment)->not->toBeNull()
        ->and($appointment->patient_id)->toBe($dependent->id)
        ->and($appointment->purpose_of_appointment)->toBe('Checkup for child');
});

test('patient cannot book an appointment for an unrelated patient account', function () {
    $parent = Patient::factory()->create();
    $unrelatedPatient = Patient::factory()->create();

    BookingSetting::query()->create([
        'morning_slot_capacity' => 5,
        'booking_enabled' => true,
        'booking_open_days' => 3,
    ]);

    $response = $this->actingAs($parent, 'patient')->post(route('patient.appointments.store'), [
        'patient_id' => $unrelatedPatient->id,
        'appointment_date' => today()->toDateString(),
        'appointment_session' => 'morning',
        'appointment_number' => 1,
        'appointment_type' => 'new',
    ]);

    $response->assertSessionHasErrors('patient_id');
    expect(Appointment::query()->count())->toBe(0);
});
