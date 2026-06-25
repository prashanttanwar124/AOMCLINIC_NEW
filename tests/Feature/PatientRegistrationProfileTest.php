<?php

use App\Models\Patient;

test('patient registration persists profile fields', function () {
    $this->post(route('patient.register.store'), [
        'name' => 'Aarav Sharma',
        'email' => 'aarav@example.com',
        'phone' => '9998887776',
        'date_of_birth' => '1988-11-02',
        'gender' => 'male',
        'address' => '14 Lake View Road',
        'city' => 'Toronto',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('patient.dashboard', absolute: false));

    $patient = Patient::query()->where('email', 'aarav@example.com')->first();

    expect($patient)->not->toBeNull()
        ->and($patient->phone)->toBe('9998887776')
        ->and($patient->country_code)->toBe('IN')
        ->and($patient->country_calling_code)->toBe('91')
        ->and($patient->gender)->toBe('male')
        ->and($patient->city)->toBe('Toronto');
});

test('patient registration requires the expanded profile fields', function () {
    $this->post(route('patient.register.store'), [
        'name' => 'Incomplete Patient',
        'email' => 'incomplete@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors([
        'phone',
        'date_of_birth',
        'gender',
    ]);
});
