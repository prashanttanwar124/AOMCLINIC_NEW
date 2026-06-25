<?php

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Patient;
use App\Models\Size;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login from medicine tracking', function () {
    $this->get(route('admin.medicine-tracking'))->assertRedirect(route('login'));
});

test('authenticated staff can view today medicine tracking workspace', function () {
    $user = User::factory()->create();
    $today = today()->toDateString();

    $patient1 = Patient::factory()->create(['name' => 'John Doe']);
    $patient2 = Patient::factory()->create(['name' => 'Jane Smith']);

    $apt1 = Appointment::factory()->create([
        'patient_id' => $patient1->id,
        'appointment_date' => $today,
        'appointment_order' => 1,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'complete',
        'medicine_status' => false,
    ]);

    $apt2 = Appointment::factory()->create([
        'patient_id' => $patient2->id,
        'appointment_date' => $today,
        'appointment_order' => 2,
        'slot' => Appointment::SLOT_MORNING,
        'status' => 'complete',
        'medicine_status' => true,
        'amount' => '400',
        'payment_type' => 'Card',
    ]);

    $response = $this->actingAs($user)->get(route('admin.medicine-tracking'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/MedicineTracking')
            ->has('appointments', 1)
            ->where('appointments.0.patientName', 'John Doe')
            ->where('appointments.0.medicineStatus', false)
            ->where('selectedId', $apt1->id)
        );
});

test('authenticated staff can view patient previous medicines history', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    // Previous complete appointment with medicines
    $pastApt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->subDays(5)->toDateString(),
        'status' => 'complete',
        'medicines' => [
            ['name' => 'Arnica Montana', 'potency' => '200C', 'size' => '30ml', 'quantity' => 1],
        ],
    ]);

    // Today's appointment
    $todayApt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'complete',
        'medicine_status' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.medicine-tracking', ['selected' => $todayApt->id]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/MedicineTracking')
            ->where('selectedId', $todayApt->id)
            ->has('previousMedicines', 1)
            ->where('previousMedicines.0.id', $pastApt->id)
            ->where('previousMedicines.0.medicines.0.name', 'Arnica Montana')
        );
});

test('authenticated staff can update medicine tracking and payment details', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $category = Category::factory()->create(['name' => '30C']);
    $size = Size::factory()->create(['name' => '2 dram']);

    $medicine = Medicine::factory()->create([
        'name' => 'Sulphur',
    ]);

    $stock = MedicineStock::factory()->create([
        'medicine_id' => $medicine->id,
        'category_id' => $category->id,
        'size_id' => $size->id,
        'quantity' => 10,
    ]);

    $apt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'complete',
        'medicine_status' => false,
    ]);

    $medicinesPayload = [
        ['name' => 'Sulphur', 'category' => '30C', 'size' => '2 dram', 'quantity' => 2],
    ];

    $response = $this->actingAs($user)->patch(route('admin.medicine-tracking.update', $apt), [
        'medicines' => $medicinesPayload,
        'amount' => 350.50,
        'payment_type' => 'UPI',
    ]);

    $response->assertRedirect(route('admin.medicine-tracking'));

    $freshApt = $apt->fresh();
    expect($freshApt->medicine_status)->toBeTrue()
        ->and($freshApt->amount)->toBe('350.5') // stored as string
        ->and($freshApt->payment_type)->toBe('UPI')
        ->and($freshApt->medicines)->toHaveCount(1)
        ->and($freshApt->medicines[0]['id'])->toBe("{$medicine->id} | {$stock->category_id} | {$stock->size_id}")
        ->and($freshApt->medicines[0]['quantity'])->toBe(2);
});

test('updating medicine tracking reduces inventory stock and handles edits/restorations correctly', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    $category = Category::firstOrCreate(['name' => '30C']);
    $size = Size::firstOrCreate(['name' => '2 dram']);
    $medicine = Medicine::firstOrCreate([
        'name' => 'Sulphur',
    ]);
    $stock = MedicineStock::updateOrCreate(
        [
            'medicine_id' => $medicine->id,
            'category_id' => $category->id,
            'size_id' => $size->id,
        ],
        [
            'quantity' => 10,
        ]
    );

    $apt = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'status' => 'complete',
        'medicine_status' => false,
    ]);

    // Dispatch 2 units
    $this->actingAs($user)->patch(route('admin.medicine-tracking.update', $apt), [
        'medicines' => [
            ['name' => 'Sulphur', 'category' => '30C', 'size' => '2 dram', 'quantity' => 2],
        ],
        'amount' => 350.50,
        'payment_type' => 'UPI',
    ]);

    // Inventory stock should be reduced to 8
    expect($stock->fresh()->quantity)->toBe(8);

    // Update prescription to 3 units (diff of 1)
    $this->actingAs($user)->patch(route('admin.medicine-tracking.update', $apt), [
        'medicines' => [
            ['name' => 'Sulphur', 'category' => '30C', 'size' => '2 dram', 'quantity' => 3],
        ],
        'amount' => 350.50,
        'payment_type' => 'UPI',
    ]);

    // Inventory stock should be reduced to 7
    expect($stock->fresh()->quantity)->toBe(7);

    // Remove medicine completely (restore stock to 10)
    $this->actingAs($user)->patch(route('admin.medicine-tracking.update', $apt), [
        'medicines' => [],
        'amount' => 350.50,
        'payment_type' => 'UPI',
    ]);

    expect($stock->fresh()->quantity)->toBe(10);
});
