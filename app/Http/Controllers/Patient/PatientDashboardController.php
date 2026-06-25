<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $patient = $request->user('patient');

        $upcomingAppointments = Appointment::query()
            ->whereBelongsTo($patient)
            ->whereNotIn('status', ['complete', 'Complete'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_order')
            ->limit(6)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => $appointment->appointment_date?->format('D, d M Y'),
                'number' => $appointment->appointment_number,
                'reason' => $appointment->purpose_of_appointment,
                'session' => Appointment::sessionLabelForSlot($appointment->slot),
                'status' => $appointment->status,
                'type' => $appointment->appointment_type,
            ]);

        $pastAppointments = Appointment::query()
            ->whereBelongsTo($patient)
            ->whereIn('status', ['complete', 'Complete'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_order')
            ->limit(5)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => $appointment->appointment_date?->format('d M Y'),
                'type' => $appointment->appointment_type,
                'diagnosis' => $appointment->diagnosis,
                'treatment' => $appointment->treatment,
                'medicines' => $appointment->medicines,
            ]);

        $latestPrescriptionAppointment = Appointment::query()
            ->whereBelongsTo($patient)
            ->whereNotNull('treatment')
            ->where('treatment', '!=', '')
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_order')
            ->first();

        $latestPrescription = $latestPrescriptionAppointment ? [
            'date' => $latestPrescriptionAppointment->appointment_date?->format('d M Y'),
            'treatment' => $latestPrescriptionAppointment->treatment,
            'days' => $latestPrescriptionAppointment->days_prescription,
            'medicines' => $latestPrescriptionAppointment->medicines,
        ] : null;

        // Fetch first 3 active staff/doctor users for the Care Circle
        $clinicStaff = User::query()
            ->with('roles')
            ->take(3)
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'role' => $user->roles->first()?->name ?? 'Care Specialist',
            ]);

        // Retrieve the latest completed appointment to extract fee and payment type for billing snapshot
        $latestCompleted = Appointment::query()
            ->whereBelongsTo($patient)
            ->whereIn('status', ['complete', 'Complete'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_order')
            ->first();

        $billingInfo = [
            'hasDue' => $latestCompleted && $latestCompleted->amount > 0,
            'amount' => $latestCompleted ? $latestCompleted->amount : null,
            'paymentType' => $latestCompleted ? $latestCompleted->payment_type : 'Cash',
            'date' => $latestCompleted ? $latestCompleted->appointment_date?->format('d M Y') : null,
        ];

        return Inertia::render('patient/Dashboard', [
            'bookingEnabled' => (bool) BookingSetting::current()->booking_enabled,
            'appointments' => $upcomingAppointments,
            'pastAppointments' => $pastAppointments,
            'latestPrescription' => $latestPrescription,
            'clinicStaff' => $clinicStaff,
            'billingInfo' => $billingInfo,
        ]);
    }
}
