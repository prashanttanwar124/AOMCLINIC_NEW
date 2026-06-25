<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VitalsTrackingController extends Controller
{
    /**
     * Show today's appointments queue for vitals tracking.
     */
    public function index(Request $request): Response
    {
        $today = today()->toDateString();

        $appointments = Appointment::query()
            ->with([
                'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
                'vital',
            ])
            ->whereDate('appointment_date', $today)
            ->where('status', 'pending')
            ->orderByRaw(
                "CASE slot
                    WHEN '".Appointment::SLOT_MORNING."' THEN 0
                    WHEN '".Appointment::SLOT_EVENING."' THEN 1
                    ELSE 2
                END",
            )
            ->orderBy('appointment_order')
            ->get();

        $selectedId = $request->query('selected');
        if (! $selectedId && $appointments->isNotEmpty()) {
            $selectedId = (string) $appointments->first()->id;
        }

        $selectedAppointment = null;

        if ($selectedId) {
            $selectedAppointment = $appointments->firstWhere('id', $selectedId);
            if (! $selectedAppointment) {
                $selectedAppointment = Appointment::with([
                    'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
                    'vital',
                ])->find($selectedId);
            }
        }

        $transformedAppointments = $appointments->map(fn (Appointment $apt): array => [
            'id' => $apt->id,
            'patientId' => $apt->patient?->id,
            'patientName' => $apt->patient?->name ?? 'Walk-in patient',
            'gender' => $apt->patient?->gender ? str($apt->patient->gender)->headline()->toString() : null,
            'age' => $apt->patient?->date_of_birth?->age,
            'phone' => $apt->patient?->phone ? "+{$apt->patient->country_calling_code} {$apt->patient->phone}" : null,
            'session' => Appointment::sessionLabelForSlot($apt->slot),
            'appointmentNumber' => $apt->appointment_number,
            'appointmentSequence' => (int) $apt->appointment_order,
            'status' => $apt->status,
            'hasVitals' => $apt->vital !== null,
        ])->all();

        $transformedSelected = null;
        if ($selectedAppointment) {
            $transformedSelected = [
                'id' => $selectedAppointment->id,
                'patientId' => $selectedAppointment->patient?->id,
                'patientName' => $selectedAppointment->patient?->name ?? 'Walk-in patient',
                'gender' => $selectedAppointment->patient?->gender ? str($selectedAppointment->patient->gender)->headline()->toString() : null,
                'age' => $selectedAppointment->patient?->date_of_birth?->age,
                'phone' => $selectedAppointment->patient?->phone ? "+{$selectedAppointment->patient->country_calling_code} {$selectedAppointment->patient->phone}" : null,
                'session' => Appointment::sessionLabelForSlot($selectedAppointment->slot),
                'appointmentNumber' => $selectedAppointment->appointment_number,
                'appointmentSequence' => (int) $selectedAppointment->appointment_order,
                'status' => $selectedAppointment->status,
                'vitals' => $selectedAppointment->vital ? [
                    'temperature' => $selectedAppointment->vital->temperature,
                    'weight' => $selectedAppointment->vital->weight,
                    'blood_pressure' => $selectedAppointment->vital->blood_pressure,
                    'pulse_rate' => $selectedAppointment->vital->pulse_rate,
                    'spo2' => $selectedAppointment->vital->spo2,
                    'notes' => $selectedAppointment->vital->notes,
                ] : [
                    'temperature' => '',
                    'weight' => '',
                    'blood_pressure' => '',
                    'pulse_rate' => '',
                    'spo2' => '',
                    'notes' => '',
                ],
            ];
        }

        return Inertia::render('admin/VitalsTracking', [
            'appointments' => $transformedAppointments,
            'selectedId' => $selectedId ? (int) $selectedId : null,
            'selectedAppointment' => $transformedSelected,
        ]);
    }

    /**
     * Store or update patient vitals for the appointment.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'temperature' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'blood_pressure' => ['nullable', 'string', 'max:50'],
            'pulse_rate' => ['nullable', 'string', 'max:50'],
            'spo2' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $appointment->vital()->updateOrCreate([], $validated);

        $patientName = $appointment->patient?->name ?? 'Walk-in patient';

        return redirect()->route('admin.vitals-tracking', ['selected' => $appointment->id])->with('toast', [
            'type' => 'success',
            'message' => "Vitals updated for {$patientName}.",
        ]);
    }
}
