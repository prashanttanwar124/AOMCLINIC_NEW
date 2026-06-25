<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AppointmentBookingController;
use App\Http\Controllers\Admin\BookingSettingController;
use App\Http\Controllers\Admin\CourierParcelController;
use App\Http\Controllers\Admin\CurrentAppointmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MedicineController;
use App\Http\Controllers\Admin\MedicineTrackingController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ClinicSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VitalsTrackingController;
use App\Http\Controllers\Admin\CertificateTypeController;
use App\Http\Controllers\Admin\MedicalCertificateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('booking', [CurrentAppointmentController::class, 'index'])->name('booking');
    Route::patch('booking/{appointment}/hold', [CurrentAppointmentController::class, 'toggleHold'])
        ->name('booking.hold.toggle');
    Route::patch('booking/{appointment}', [CurrentAppointmentController::class, 'update'])
        ->name('booking.update');

    Route::get('appointments/book', [AppointmentBookingController::class, 'create'])->name('admin.appointments.book');
    Route::post('appointments/book', [AppointmentBookingController::class, 'store'])->name('admin.appointments.book.store');
    Route::get('appointments/{appointment}/receipt', [AppointmentController::class, 'receipt'])->name('admin.appointments.receipt');
    Route::get('appointments', [AppointmentController::class, 'index'])->name('admin.appointments');

    Route::get('patients/search', [PatientController::class, 'search'])->name('admin.patients.search');
    Route::get('patients/{patient}/history', [PatientController::class, 'history'])->name('admin.patients.history');
    Route::post('patients/{patient}/join', [PatientController::class, 'join'])->name('admin.patients.join');
    Route::post('patients/{patient}/unlink', [PatientController::class, 'unlink'])->name('admin.patients.unlink');
    Route::get('patients', [PatientController::class, 'index'])->name('admin.patients');

    Route::get('medicine-tracking', [MedicineTrackingController::class, 'index'])->name('admin.medicine-tracking');
    Route::patch('medicine-tracking/{appointment}', [MedicineTrackingController::class, 'update'])->name('admin.medicine-tracking.update');

    Route::get('vitals-tracking', [VitalsTrackingController::class, 'index'])->name('admin.vitals-tracking');
    Route::patch('vitals-tracking/{appointment}', [VitalsTrackingController::class, 'update'])->name('admin.vitals-tracking.update');

    Route::get('courier-parcels', [CourierParcelController::class, 'index'])->name('admin.courier-parcels');
    Route::post('courier-parcels', [CourierParcelController::class, 'store'])->name('admin.courier-parcels.store');
    Route::patch('courier-parcels/{courierParcel}', [CourierParcelController::class, 'update'])->name('admin.courier-parcels.update');
    Route::delete('courier-parcels/{courierParcel}', [CourierParcelController::class, 'destroy'])->name('admin.courier-parcels.destroy');

    Route::post('certificate-types', [CertificateTypeController::class, 'store'])->name('admin.certificate-types.store');
    Route::patch('certificate-types/{certificateType}', [CertificateTypeController::class, 'update'])->name('admin.certificate-types.update');
    Route::delete('certificate-types/{certificateType}', [CertificateTypeController::class, 'destroy'])->name('admin.certificate-types.destroy');

    Route::get('medical-certificates', [MedicalCertificateController::class, 'index'])->name('admin.medical-certificates');
    Route::post('medical-certificates', [MedicalCertificateController::class, 'store'])->name('admin.medical-certificates.store');
    Route::get('medical-certificates/{medicalCertificate}/print', [MedicalCertificateController::class, 'print'])->name('admin.medical-certificates.print');
    Route::patch('medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'update'])->name('admin.medical-certificates.update');
    Route::delete('medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'destroy'])->name('admin.medical-certificates.destroy');

    Route::get('medicines/search', [MedicineController::class, 'search'])->name('admin.medicines.search');
    Route::get('medicines', [MedicineController::class, 'index'])->name('admin.medicines');
    Route::post('medicines', [MedicineController::class, 'store'])->name('admin.medicines.store');
    Route::patch('medicines/{medicine}/quantity', [MedicineController::class, 'updateQuantity'])->name('admin.medicines.quantity.update');
    Route::delete('medicines/{medicine}', [MedicineController::class, 'destroy'])->name('admin.medicines.destroy');

    Route::post('categories', [MedicineController::class, 'storeCategory'])->name('admin.categories.store');
    Route::patch('categories/{category}', [MedicineController::class, 'updateCategory'])->name('admin.categories.update');
    Route::delete('categories/{category}', [MedicineController::class, 'destroyCategory'])->name('admin.categories.destroy');

    Route::post('sizes', [MedicineController::class, 'storeSize'])->name('admin.sizes.store');
    Route::patch('sizes/{size}', [MedicineController::class, 'updateSize'])->name('admin.sizes.update');
    Route::delete('sizes/{size}', [MedicineController::class, 'destroySize'])->name('admin.sizes.destroy');

    Route::middleware(['permission:manage staff'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('admin.users');
        Route::post('users', [UserController::class, 'store'])->name('admin.users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('roles', [RoleController::class, 'index'])->name('admin.roles');
        Route::post('roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');

        Route::get('booking-settings', [BookingSettingController::class, 'index'])->name('admin.booking-settings');
        Route::patch('booking-settings', [BookingSettingController::class, 'update'])->name('admin.booking-settings.update');

        Route::get('clinic-settings', [ClinicSettingController::class, 'index'])->name('admin.clinic-settings');
        Route::post('clinic-settings', [ClinicSettingController::class, 'update'])->name('admin.clinic-settings.update');
    });
});

require __DIR__.'/settings.php';
