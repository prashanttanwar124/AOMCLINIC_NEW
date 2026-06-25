<?php

use App\Models\Appointment;
use App\Models\Patient;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can access the patient live status page', function () {
    $bookingDate = today()->toDateString();

    \App\Models\BookingSetting::query()->create([
        'morning_opening_time' => '09:00:00',
        'morning_closing_time' => '13:00:00',
        'evening_opening_time' => '17:00:00',
        'evening_closing_time' => '21:00:00',
    ]);

    $response = $this->get(route('patient.live-status'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/LiveStatus')
            ->where('today', $bookingDate)
            ->where('patientAppointmentToday', null)
        );
});

test('authenticated patients can access the live status page and see their own token', function () {
    $patient = Patient::factory()->create();

    $bookingDate = today()->toDateString();

    \App\Models\BookingSetting::query()->create([
        'morning_opening_time' => '09:00:00',
        'morning_closing_time' => '13:00:00',
        'evening_opening_time' => '17:00:00',
        'evening_closing_time' => '21:00:00',
    ]);

    // Create a running appointment
    $runningApt = Appointment::factory()->create([
        'appointment_date' => $bookingDate,
        'appointment_number' => '1',
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    // Create patient's own appointment
    $ownApt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => $bookingDate,
        'appointment_number' => '3',
        'appointment_order' => 3,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    // Create another waiting appointment between running and own
    Appointment::factory()->create([
        'appointment_date' => $bookingDate,
        'appointment_number' => '2',
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($patient, 'patient')->get(route('patient.live-status'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/LiveStatus')
            ->where('today', $bookingDate)
            ->where('morningTimings', '9:00 AM - 1:00 PM')
            ->where('eveningTimings', '5:00 PM - 9:00 PM')
            ->where('morningRunningToken', '1')
            ->where('eveningRunningToken', null)
            ->has('morningQueue', 3)
            ->where('morningQueue.0.token', '1')
            ->where('morningQueue.0.status', 'running')
            ->where('morningQueue.0.isPatient', false)
            ->where('morningQueue.1.token', '2')
            ->where('morningQueue.1.status', 'pending')
            ->where('morningQueue.1.isPatient', false)
            ->where('morningQueue.2.token', '3')
            ->where('morningQueue.2.status', 'pending')
            ->where('morningQueue.2.isPatient', true)
            ->where('patientAppointmentToday.token', '3')
            ->where('patientAppointmentToday.session', 'Morning')
            ->where('patientAppointmentToday.status', 'pending')
            ->where('patientAppointmentToday.queuePosition', 1) // Only token 2 is pending and has a lower order (1 is running)
        );
});
