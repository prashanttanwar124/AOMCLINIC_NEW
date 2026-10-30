<?php

use App\Events\QueueUpdated;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Event;
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

test('authenticated staff can search patients by partial phone number, last name, or any mix of name and phone', function (string $searchQuery, string $expectedName) {
    $user = User::factory()->create();

    Patient::factory()->create([
        'name' => 'Prashant Kumar Tanwar',
        'phone' => '9876543210',
        'country_calling_code' => '91',
        'email' => 'prashant@example.com',
    ]);

    Patient::factory()->create([
        'name' => 'Rohan Tanwar',
        'phone' => '9123456789',
        'country_calling_code' => '91',
        'email' => 'rohan@example.com',
    ]);

    Patient::factory()->create([
        'name' => 'Meera Sharma',
        'phone' => '9988776655',
        'country_calling_code' => '91',
        'email' => 'meera@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('admin.patients', ['search' => $searchQuery]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Patients')
            ->has('patients.data', 1)
            ->where('patients.data.0.name', $expectedName));

    $this->actingAs($user)
        ->getJson(route('admin.patients.search', ['query' => $searchQuery]))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => $expectedName]);
})->with([
    'partial phone start' => ['9876', 'Prashant Kumar Tanwar'],
    'partial phone end' => ['3210', 'Prashant Kumar Tanwar'],
    'first and last name skipping middle name' => ['Prashant Tanwar', 'Prashant Kumar Tanwar'],
    'reversed last and first name' => ['Tanwar Prashant', 'Prashant Kumar Tanwar'],
    'last name and partial phone' => ['Tanwar 3210', 'Prashant Kumar Tanwar'],
    'partial phone and first name' => ['9876 Prashant', 'Prashant Kumar Tanwar'],
    'other shared last name with partial phone' => ['Tanwar 6789', 'Rohan Tanwar'],
    'last name only unique' => ['Sharma', 'Meera Sharma'],
]);

test('completing an appointment from the booking desk broadcasts QueueUpdated and adds it to medicine tracking', function () {
    Event::fake([QueueUpdated::class]);

    $user = User::factory()->create();
    $patient = Patient::factory()->create(['name' => 'Kiran Verma']);

    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'appointment_date' => today()->toDateString(),
        'slot' => Appointment::SLOT_MORNING,
        'appointment_number' => '1',
        'appointment_order' => 1,
        'status' => 'pending',
        'medicine_status' => false,
    ]);

    $this->actingAs($user)
        ->patchJson(route('booking.update', $appointment), [
            'complete' => true,
            'chief_complaint' => 'Sneezing',
            'present_complaint' => 'Runny nose for 3 days',
            'diagnosis' => 'Seasonal allergy',
            'treatment' => 'Antihistamine course',
            'medicines' => [
                ['id' => 'CETIRIZINE | TABLET | 10MG', 'label' => 'CETIRIZINE | TABLET | 10MG'],
            ],
            'medication_instructions' => 'Take once daily at night',
            'amount' => '250',
        ])
        ->assertSuccessful();

    Event::assertDispatched(QueueUpdated::class, fn (QueueUpdated $event) => $event->session === 'Morning');

    $this->actingAs($user)
        ->get(route('admin.medicine-tracking'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/MedicineTracking')
            ->has('appointments', 1)
            ->where('appointments.0.id', $appointment->id)
            ->where('selectedAppointment.id', $appointment->id));

    Event::fake([QueueUpdated::class]);

    $this->actingAs($user)
        ->patch(route('admin.medicine-tracking.update', $appointment), [
            'medicines' => [],
            'amount' => 250,
            'payment_type' => 'Cash',
        ])
        ->assertRedirect(route('admin.medicine-tracking'));

    Event::assertDispatched(QueueUpdated::class, fn (QueueUpdated $event) => $event->session === 'Morning');
    expect($appointment->fresh()->medicine_status)->toBeTrue();
});
