<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\CertificateType;
use App\Models\MedicalCertificate;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login from medical certificates', function () {
    $this->get(route('admin.medical-certificates'))->assertRedirect(route('login'));
});

test('authenticated staff can view medical certificates list and types', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create(['name' => 'Jane Smith']);
    $type = CertificateType::factory()->create(['name' => 'Sick Leave Cert']);

    $cert = MedicalCertificate::create([
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(2)->toDateString(),
        'diagnosis' => 'Viral Influenza',
        'charge_amount' => 25.00,
        'payment_status' => 'paid',
        'notes' => 'Advised complete bed rest',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('admin.medical-certificates'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/MedicalCertificates')
            ->has('certificates.data', 1)
            ->where('certificates.data.0.patient_name', 'Jane Smith')
            ->where('certificates.data.0.certificate_type_name', 'Sick Leave Cert')
            ->where('certificates.data.0.certificate_number', $cert->certificate_number)
            ->has('certificateTypes', 1)
            ->where('certificateTypes.0.name', 'Sick Leave Cert')
        );
});

test('authenticated staff can create a certificate type', function () {
    $user = User::factory()->create();

    $payload = [
        'name' => 'General Fitness Certificate',
        'description' => 'Issued for gym or school fitness clearances',
        'default_charge' => 15.50,
    ];

    $response = $this->actingAs($user)->post(route('admin.certificate-types.store'), $payload);

    $response->assertRedirect();
    $type = CertificateType::first();
    expect($type)->not->toBeNull()
        ->and($type->name)->toBe('General Fitness Certificate')
        ->and($type->description)->toBe('Issued for gym or school fitness clearances')
        ->and((float)$type->default_charge)->toBe(15.50);
});

test('authenticated staff can update a certificate type', function () {
    $user = User::factory()->create();
    $type = CertificateType::factory()->create([
        'name' => 'Old Cert Name',
        'description' => 'Old description',
        'default_charge' => 10.00,
    ]);

    $payload = [
        'name' => 'Updated Cert Name',
        'description' => 'Updated description',
        'default_charge' => 20.00,
    ];

    $response = $this->actingAs($user)->patch(route('admin.certificate-types.update', $type), $payload);

    $response->assertRedirect();
    $freshType = $type->fresh();
    expect($freshType->name)->toBe('Updated Cert Name')
        ->and($freshType->description)->toBe('Updated description')
        ->and((float)$freshType->default_charge)->toBe(20.00);
});

test('authenticated staff can delete a certificate type', function () {
    $user = User::factory()->create();
    $type = CertificateType::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.certificate-types.destroy', $type));

    $response->assertRedirect();
    expect(CertificateType::find($type->id))->toBeNull();
});

test('authenticated staff can issue a medical certificate', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $type = CertificateType::factory()->create();

    $payload = [
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
        'diagnosis' => 'Acute Migraine',
        'charge_amount' => 30.00,
        'payment_status' => 'unpaid',
        'notes' => 'Avoid bright lights',
        'status' => 'active',
    ];

    $response = $this->actingAs($user)->post(route('admin.medical-certificates.store'), $payload);

    $response->assertRedirect();
    $cert = MedicalCertificate::first();
    expect($cert)->not->toBeNull()
        ->and($cert->patient_id)->toBe($patient->id)
        ->and($cert->certificate_type_id)->toBe($type->id)
        ->and($cert->certificate_number)->toBe('MC-00001')
        ->and($cert->diagnosis)->toBe('Acute Migraine')
        ->and((float)$cert->charge_amount)->toBe(30.00)
        ->and($cert->payment_status)->toBe('unpaid')
        ->and($cert->status)->toBe('active');
});

test('authenticated staff can update a medical certificate', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $type = CertificateType::factory()->create();

    $cert = MedicalCertificate::create([
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'charge_amount' => 10.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
    ]);

    $payload = [
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(3)->toDateString(),
        'diagnosis' => 'Common Cold',
        'charge_amount' => 15.00,
        'payment_status' => 'paid',
        'notes' => 'Drink warm fluids',
        'status' => 'active',
    ];

    $response = $this->actingAs($user)->patch(route('admin.medical-certificates.update', $cert), $payload);

    $response->assertRedirect();
    $freshCert = $cert->fresh();
    expect($freshCert->diagnosis)->toBe('Common Cold')
        ->and((float)$freshCert->charge_amount)->toBe(15.00)
        ->and($freshCert->payment_status)->toBe('paid')
        ->and($freshCert->notes)->toBe('Drink warm fluids');
});

test('authenticated staff can delete a medical certificate', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $type = CertificateType::factory()->create();

    $cert = MedicalCertificate::create([
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'charge_amount' => 10.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.medical-certificates.destroy', $cert));

    $response->assertRedirect();
    expect(MedicalCertificate::find($cert->id))->toBeNull();
});

test('authenticated staff can view the printable medical certificate', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create(['name' => 'Jane Smith']);
    $type = CertificateType::factory()->create(['name' => 'Sick Leave Cert']);

    $cert = MedicalCertificate::create([
        'patient_id' => $patient->id,
        'certificate_type_id' => $type->id,
        'issue_date' => today()->toDateString(),
        'charge_amount' => 10.00,
        'payment_status' => 'unpaid',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('admin.medical-certificates.print', $cert));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/medical-certificates/Print')
            ->where('certificate.patient_name', 'Jane Smith')
            ->where('certificate.certificate_number', $cert->certificate_number)
        );
});
