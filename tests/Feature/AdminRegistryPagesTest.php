<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated staff can view the patients registry with dependents and appointment history', function () {
    $user = User::factory()->create();

    $patient = Patient::factory()->create([
        'name' => 'Asha Gupta',
        'phone' => '9876543210',
        'country_calling_code' => '91',
    ]);

    $child = Patient::factory()->create([
        'name' => 'Ira Gupta',
        'parent_id' => $patient->id,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => '2026-06-01',
        'appointment_number' => '12',
        'appointment_order' => 12,
        'appointment_type' => 'Follow Up',
    ]);

    $this->actingAs($user)
        ->get(route('admin.patients'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Patients')
            ->has('patients.data', 2)
            ->where('patients.data.0.name', 'Asha Gupta')
            ->where('patients.data.0.children.0.name', 'Ira Gupta')
            ->where('patients.data.0.appointments.0.appointmentNumber', '12')
            ->where('patients.data.0.appointments.0.appointmentType', 'Follow Up')
            ->where('filters.search', null));
});

test('authenticated staff can search the appointments ledger by patient name', function () {
    $user = User::factory()->create();

    $matchingPatient = Patient::factory()->create(['name' => 'Ravi Patel']);
    $otherPatient = Patient::factory()->create(['name' => 'Meera Shah']);

    Appointment::factory()->create([
        'patient_id' => $matchingPatient->id,
        'appointment_date' => '2026-06-03',
        'appointment_number' => '7',
        'appointment_order' => 7,
        'appointment_type' => 'New',
    ]);

    Appointment::factory()->create([
        'patient_id' => $otherPatient->id,
        'appointment_date' => '2026-06-02',
        'appointment_number' => '8',
        'appointment_order' => 8,
        'appointment_type' => 'Follow Up',
    ]);

    $this->actingAs($user)
        ->get(route('admin.appointments', ['search' => 'Ravi']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Appointments')
            ->has('appointments.data', 1)
            ->where('appointments.data.0.patientName', 'Ravi Patel')
            ->where('appointments.data.0.appointmentNumber', '7')
            ->where('filters.search', 'Ravi'));
});
