<?php

use App\Models\Patient;
use Inertia\Testing\AssertableInertia as Assert;

test('patient login screen can be rendered', function () {
    $response = $this->get(route('patient.login'));

    $response->assertOk();
});

test('patient registration screen can be rendered', function () {
    $response = $this->get(route('patient.register'));

    $response->assertOk();
});

test('patients can register using their own table and guard', function () {
    $response = $this->post(route('patient.register.store'), [
        'name' => 'Patient User',
        'email' => 'patient@example.com',
        'phone' => '9876543210',
        'date_of_birth' => '1990-05-20',
        'gender' => 'female',
        'address' => '221B Baker Street',
        'city' => 'Mumbai',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $patient = Patient::query()->where('email', 'patient@example.com')->first();

    expect($patient)->not->toBeNull();
    $this->assertAuthenticatedAs($patient, 'patient');
    $response->assertRedirect(route('patient.dashboard', absolute: false));
});

test('patients can authenticate using the patient login screen', function () {
    $patient = Patient::factory()->create();

    $response = $this->post(route('patient.login.store'), [
        'email' => $patient->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($patient, 'patient');
    $response->assertRedirect(route('patient.dashboard', absolute: false));
});

test('guests are redirected away from the patient dashboard', function () {
    $response = $this->get(route('patient.dashboard'));

    $response->assertRedirect(route('patient.login'));
});

test('authenticated patients can visit the patient dashboard', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($patient, 'patient')->get(route('patient.dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Dashboard'));
});

test('patients can log out', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($patient, 'patient')->post(route('patient.logout'));

    $this->assertGuest('patient');
    $response->assertRedirect(route('patient.login'));
});
