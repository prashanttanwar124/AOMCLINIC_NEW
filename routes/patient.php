<?php

use App\Http\Controllers\Patient\Auth\AuthenticatedPatientSessionController;
use App\Http\Controllers\Patient\Auth\RegisteredPatientController;
use App\Http\Controllers\Patient\PatientAppointmentController;
use App\Http\Controllers\Patient\PatientDashboardController;
use App\Http\Controllers\Patient\PatientDependentController;
use App\Http\Controllers\Patient\PatientLiveStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->name('patient.')->group(function () {
    Route::middleware('guest:patient')->group(function () {
        Route::get('login', [AuthenticatedPatientSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedPatientSessionController::class, 'store'])->name('login.store');

        Route::get('register', [RegisteredPatientController::class, 'create'])->name('register');
        Route::post('register', [RegisteredPatientController::class, 'store'])->name('register.store');
    });

    Route::get('live-status', [PatientLiveStatusController::class, 'show'])->name('live-status');

    Route::middleware('auth.patient:patient')->group(function () {
        Route::get('dashboard', PatientDashboardController::class)->name('dashboard');
        Route::get('appointments/create', [PatientAppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');
        
        Route::get('dependents', [PatientDependentController::class, 'index'])->name('dependents');
        Route::post('dependents', [PatientDependentController::class, 'store'])->name('dependents.store');
        Route::patch('dependents/{dependent}', [PatientDependentController::class, 'update'])->name('dependents.update');
        
        Route::post('logout', [AuthenticatedPatientSessionController::class, 'destroy'])->name('logout');
    });
});
