<?php

use App\Models\ClinicSetting;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('unauthenticated guests are redirected to login from clinic settings and receipts', function () {
    $response = $this->get(route('admin.clinic-settings'));
    $response->assertRedirect(route('login'));

    $response2 = $this->post(route('admin.clinic-settings.update'), []);
    $response2->assertRedirect(route('login'));

    $appointment = Appointment::factory()->create();
    $response3 = $this->get(route('admin.appointments.receipt', $appointment));
    $response3->assertRedirect(route('login'));
});

test('unauthorized users without manage staff permission cannot access clinic settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.clinic-settings'));
    $response->assertForbidden();

    $response2 = $this->actingAs($user)->post(route('admin.clinic-settings.update'), []);
    $response2->assertForbidden();
});

test('authorized users with manage staff permission can view clinic settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    ClinicSetting::query()->create([
        'clinic_name' => 'My Health Clinic',
        'doctor_name' => 'Dr. Jane Doe',
        'doctor_qualifications' => 'MBBS, MD',
        'doctor_title' => 'Senior Consultant',
        'doctor_registration_no' => 'REG12345',
        'clinic_registration_no' => 'CLINIC67890',
        'address' => '123 Health Ave, Clinic City',
        'phone' => '1234567890',
        'email' => 'jane@clinic.com',
        'logo_path' => 'clinic/test-logo.png',
    ]);

    $response = $this->actingAs($user)->get(route('admin.clinic-settings'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ClinicSettings')
            ->where('settings.clinic_name', 'My Health Clinic')
            ->where('settings.doctor_name', 'Dr. Jane Doe')
            ->where('settings.doctor_qualifications', 'MBBS, MD')
            ->where('settings.doctor_title', 'Senior Consultant')
            ->where('settings.doctor_registration_no', 'REG12345')
            ->where('settings.clinic_registration_no', 'CLINIC67890')
            ->where('settings.address', '123 Health Ave, Clinic City')
            ->where('settings.phone', '1234567890')
            ->where('settings.email', 'jane@clinic.com')
            ->has('settings.logo_url')
        );
});

test('authorized users can update clinic settings and upload logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $logo = UploadedFile::fake()->image('logo.jpg');

    $payload = [
        'clinic_name' => 'New Clinic Name',
        'doctor_name' => 'Dr. John Smith',
        'doctor_qualifications' => 'BAMS',
        'doctor_title' => 'Acupuncture Expert',
        'doctor_registration_no' => 'REG999',
        'clinic_registration_no' => 'CLINIC999',
        'address' => '456 New Street',
        'phone' => '0987654321',
        'email' => 'john@clinic.com',
        'logo' => $logo,
    ];

    $response = $this->actingAs($user)->post(route('admin.clinic-settings.update'), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = ClinicSetting::current();
    expect($settings->clinic_name)->toBe('New Clinic Name')
        ->and($settings->doctor_name)->toBe('Dr. John Smith')
        ->and($settings->doctor_qualifications)->toBe('BAMS')
        ->and($settings->doctor_title)->toBe('Acupuncture Expert')
        ->and($settings->doctor_registration_no)->toBe('REG999')
        ->and($settings->clinic_registration_no)->toBe('CLINIC999')
        ->and($settings->address)->toBe('456 New Street')
        ->and($settings->phone)->toBe('0987654321')
        ->and($settings->email)->toBe('john@clinic.com')
        ->and($settings->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($settings->logo_path);
});

test('clinic settings validation prevents invalid emails or images', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $payload = [
        'email' => 'invalid-email',
        'logo' => UploadedFile::fake()->create('document.pdf', 500, 'application/pdf'),
    ];

    $response = $this->actingAs($user)->post(route('admin.clinic-settings.update'), $payload);
    $response->assertSessionHasErrors(['email', 'logo']);
});

test('authenticated staff can view appointment receipt', function () {
    $user = User::factory()->create();
    
    $patient = Patient::factory()->create(['name' => 'Test Patient']);
    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'diagnosis' => 'Chronic pain',
        'days_prescription' => 5,
        'amount' => '150.00',
    ]);

    ClinicSetting::query()->create([
        'clinic_name' => 'Test Clinic',
        'doctor_name' => 'Dr. Test',
    ]);

    $response = $this->actingAs($user)->get(route('admin.appointments.receipt', $appointment));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/appointments/Receipt')
            ->where('appointment.id', $appointment->id)
            ->where('appointment.patient_name', 'Test Patient')
            ->where('appointment.diagnosis', 'Chronic pain')
            ->where('appointment.days_prescription', 5)
            ->where('appointment.amount', '150.00')
            ->where('clinic.clinic_name', 'Test Clinic')
            ->where('clinic.doctor_name', 'Dr. Test')
        );
});

test('authorized users can upload an SVG logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $logo = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');

    $payload = [
        'clinic_name' => 'SVG Clinic',
        'logo' => $logo,
    ];

    $response = $this->actingAs($user)->post(route('admin.clinic-settings.update'), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = ClinicSetting::current();
    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);
});

