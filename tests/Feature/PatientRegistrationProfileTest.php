<?php

use App\Models\Patient;
use App\Models\User;

test('admin can create a patient with profile fields and portal credentials', function () {
    $staff = User::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.patients.store'), [
        'name' => 'Aarav Sharma',
        'email' => 'aarav@example.com',
        'phone' => '9998887776',
        'date_of_birth' => '1988-11-02',
        'gender' => 'male',
        'address' => '14 Lake View Road',
        'city' => 'Toronto',
        'password' => 'password123',
    ]);

    $patient = Patient::query()->where('email', 'aarav@example.com')->first();

    expect($patient)->not->toBeNull()
        ->and($patient->name)->toBe('Aarav Sharma')
        ->and($patient->phone)->toBe('9998887776')
        ->and($patient->country_code)->toBe('IN')
        ->and($patient->country_calling_code)->toBe('91')
        ->and($patient->gender)->toBe('male')
        ->and($patient->city)->toBe('Toronto');

    $response->assertRedirect(route('admin.patients', ['id' => $patient->id]));
    $response->assertSessionHas('toast');
});

test('admin patient creation requires name and phone', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->post(route('admin.patients.store'), [
        'email' => 'incomplete@example.com',
    ])->assertSessionHasErrors([
        'name',
        'phone',
    ]);
});

test('admin can update an existing patient profile', function () {
    $staff = User::factory()->create();
    $patient = Patient::factory()->create([
        'name' => 'Initial Name',
        'phone' => '9876543210',
        'city' => 'Delhi',
    ]);

    $this->actingAs($staff)->patch(route('admin.patients.update', $patient), [
        'name' => 'Updated Patient Name',
        'phone' => '9123456789',
        'email' => 'updated@example.com',
        'gender' => 'female',
        'city' => 'Mumbai',
        'address' => '12 Marine Drive',
    ])->assertRedirect()
        ->assertSessionHas('toast');

    $patient->refresh();

    expect($patient->name)->toBe('Updated Patient Name')
        ->and($patient->phone)->toBe('9123456789')
        ->and($patient->email)->toBe('updated@example.com')
        ->and($patient->gender)->toBe('female')
        ->and($patient->city)->toBe('Mumbai');
});
