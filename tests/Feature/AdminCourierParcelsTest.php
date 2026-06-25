<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\CourierParcel;
use App\Models\Appointment;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login from courier parcels', function () {
    $this->get(route('admin.courier-parcels'))->assertRedirect(route('login'));
});

test('authenticated staff can view courier parcels list with options', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create(['name' => 'John Doe']);
    
    CourierParcel::create([
        'patient_id' => $patient->id,
        'parcel_status' => 'order_received',
        'parcel_date' => today()->toDateString(),
        'amount' => 120.50,
        'payment_status' => 'unpaid',
        'medicines' => ['ARS ALB | 200'],
        'address' => 'Test Address',
        'notes' => 'Some test notes',
    ]);

    $response = $this->actingAs($user)->get(route('admin.courier-parcels'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CourierParcels')
            ->has('parcels.data', 1)
            ->where('parcels.data.0.patient_name', 'John Doe')
            ->where('parcels.data.0.parcel_status', 'order_received')
            ->has('medicinesInventory')
        );
});

test('authenticated staff can create a courier parcel', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    $payload = [
        'patient_id' => $patient->id,
        'parcel_status' => 'packed',
        'parcel_date' => today()->toDateString(),
        'amount' => 150.00,
        'payment_status' => 'paid',
        'medicines' => ['BELLADONNA | 30C'],
        'address' => 'New Address',
        'notes' => 'Needs fast shipping',
    ];

    $response = $this->actingAs($user)->post(route('admin.courier-parcels.store'), $payload);

    $response->assertRedirect(route('admin.courier-parcels'));

    $parcel = CourierParcel::first();
    expect($parcel)->not->toBeNull()
        ->and($parcel->patient_id)->toBe($patient->id)
        ->and($parcel->parcel_status)->toBe('packed')
        ->and($parcel->amount)->toBe('150.00')
        ->and($parcel->payment_status)->toBe('paid')
        ->and($parcel->medicines)->toBe(['BELLADONNA | 30C'])
        ->and($parcel->address)->toBe('New Address')
        ->and($parcel->notes)->toBe('Needs fast shipping');
});

test('authenticated staff can update a courier parcel', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    
    $parcel = CourierParcel::create([
        'patient_id' => $patient->id,
        'parcel_status' => 'order_received',
        'parcel_date' => today()->toDateString(),
        'amount' => 100.00,
        'payment_status' => 'unpaid',
        'medicines' => [],
        'address' => 'Old Address',
    ]);

    $payload = [
        'patient_id' => $patient->id,
        'parcel_status' => 'delivered',
        'parcel_date' => today()->addDay()->toDateString(),
        'amount' => 125.50,
        'payment_status' => 'paid',
        'medicines' => ['SULPHUR | 200'],
        'address' => 'Updated Address',
        'notes' => 'Shipped via DTDC',
        'delivered_date' => today()->addDay()->toDateString(),
        'instructions_given' => true,
        'instruction_note' => 'Take once in morning.',
    ];

    $response = $this->actingAs($user)->patch(route('admin.courier-parcels.update', $parcel), $payload);

    $response->assertRedirect(route('admin.courier-parcels'));

    $freshParcel = $parcel->fresh();
    expect($freshParcel->parcel_status)->toBe('delivered')
        ->and($freshParcel->amount)->toBe('125.50')
        ->and($freshParcel->payment_status)->toBe('paid')
        ->and($freshParcel->medicines)->toBe(['SULPHUR | 200'])
        ->and($freshParcel->address)->toBe('Updated Address')
        ->and($freshParcel->notes)->toBe('Shipped via DTDC')
        ->and($freshParcel->delivered_date->toDateString())->toBe(today()->addDay()->toDateString())
        ->and($freshParcel->instructions_given)->toBeTrue()
        ->and($freshParcel->instruction_note)->toBe('Take once in morning.');
});

test('authenticated staff can delete a courier parcel', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    
    $parcel = CourierParcel::create([
        'patient_id' => $patient->id,
        'parcel_status' => 'order_received',
        'parcel_date' => today()->toDateString(),
        'amount' => 100.00,
        'payment_status' => 'unpaid',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.courier-parcels.destroy', $parcel));

    $response->assertRedirect(route('admin.courier-parcels'));

    expect(CourierParcel::find($parcel->id))->toBeNull();
});

test('guest users are redirected from patient history endpoint', function () {
    $patient = Patient::factory()->create();
    $this->get(route('admin.patients.history', $patient))->assertRedirect(route('login'));
});

test('authenticated staff can view patient consolidated history timeline', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    // Create an appointment for patient
    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->subDays(5)->toDateString(),
        'appointment_number' => 'APT-001',
        'chief_complaint' => 'Fever and cold',
        'treatment' => 'Rest and hydration',
        'medicines' => ['Aconitum 30C'],
        'status' => 'complete',
        'amount' => 500.00,
    ]);

    // Create a courier parcel for patient
    $patient->courierParcels()->create([
        'parcel_status' => 'delivered',
        'parcel_date' => today()->toDateString(),
        'amount' => 150.00,
        'payment_status' => 'paid',
        'medicines' => ['Aconitum 200C'],
        'address' => '123 Main St',
        'delivered_date' => today()->toDateString(),
        'instructions_given' => true,
        'instruction_note' => 'Take twice daily',
    ]);

    $response = $this->actingAs($user)->get(route('admin.patients.history', $patient));

    $response->assertOk()
        ->assertJsonStructure([
            'patient' => ['id', 'name', 'email', 'phone'],
            'timeline' => [
                '*' => ['type', 'id', 'date', 'title', 'medicines']
            ]
        ]);

    $data = $response->json();
    expect($data['timeline'])->toHaveCount(2)
        ->and($data['timeline'][0]['type'])->toBe('parcel')
        ->and($data['timeline'][0]['medicines'])->toBe(['Aconitum 200C'])
        ->and($data['timeline'][1]['type'])->toBe('appointment')
        ->and($data['timeline'][1]['complaint'])->toBe('Fever and cold');
});

