<?php

use App\Models\BookingSetting;
use App\Models\ClinicSetting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $clinic = ClinicSetting::current();
    $booking = BookingSetting::current();

    return Inertia::render('Welcome', [
        'clinic' => [
            'name' => $clinic->clinic_name ?: 'AOM Homeopathic Clinic',
            'doctorName' => $clinic->doctor_name,
            'doctorTitle' => $clinic->doctor_title,
            'doctorQualifications' => $clinic->doctor_qualifications,
            'address' => $clinic->address,
            'phone' => $clinic->phone,
            'email' => $clinic->email,
            'logoUrl' => $clinic->logoUrl(),
        ],
        'schedule' => [
            'bookingEnabled' => (bool) $booking->booking_enabled,
            'morningOpening' => $booking->morning_opening_time ?: '09:30',
            'morningClosing' => $booking->morning_closing_time ?: '13:30',
            'eveningOpening' => $booking->evening_opening_time ?: '17:00',
            'eveningClosing' => $booking->evening_closing_time ?: '20:30',
            'noticeEnabled' => (bool) $booking->notice_enabled,
            'noticeText' => $booking->notice_text,
        ],
    ]);
})->name('home');

require __DIR__.'/admin.php';
require __DIR__.'/patient.php';
