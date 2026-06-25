<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BookingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PatientLiveStatusController extends Controller
{
    /**
     * Show the live status board for today's appointments.
     */
    public function show(Request $request): Response
    {
        $patient = $request->user('patient');
        $today = today();

        $appointments = Appointment::query()
            ->whereDate('appointment_date', $today->toDateString())
            ->orderBy('appointment_order')
            ->get();

        $currentAppointmentIds = $this->currentAppointmentIds($appointments);

        $morningRunningApt = $appointments->first(fn ($apt) => $apt->id === ($currentAppointmentIds['Morning'] ?? null));
        $eveningRunningApt = $appointments->first(fn ($apt) => $apt->id === ($currentAppointmentIds['Evening'] ?? null));

        $morningQueue = $appointments
            ->filter(fn (Appointment $apt) => Appointment::sessionLabelForSlot($apt->slot) === 'Morning')
            ->map(fn (Appointment $apt) => [
                'token' => $apt->appointment_number,
                'sequence' => (int) $apt->appointment_order,
                'status' => $this->getQueueStatus($apt, $currentAppointmentIds),
                'isPatient' => $patient && $apt->patient_id === $patient->id,
            ])
            ->values()
            ->all();

        $eveningQueue = $appointments
            ->filter(fn (Appointment $apt) => Appointment::sessionLabelForSlot($apt->slot) === 'Evening')
            ->map(fn (Appointment $apt) => [
                'token' => $apt->appointment_number,
                'sequence' => (int) $apt->appointment_order,
                'status' => $this->getQueueStatus($apt, $currentAppointmentIds),
                'isPatient' => $patient && $apt->patient_id === $patient->id,
            ])
            ->values()
            ->all();

        $patientAptToday = $appointments->first(fn (Appointment $apt) => $patient && $apt->patient_id === $patient->id);

        $patientAppointmentToday = null;
        if ($patientAptToday) {
            $session = Appointment::sessionLabelForSlot($patientAptToday->slot);
            $queuePosition = $appointments
                ->filter(function (Appointment $apt) use ($patientAptToday, $session, $currentAppointmentIds) {
                    if (Appointment::sessionLabelForSlot($apt->slot) !== $session) {
                        return false;
                    }
                    $status = $this->getQueueStatus($apt, $currentAppointmentIds);
                    if ($status !== 'pending') {
                        return false;
                    }

                    return $apt->appointment_order < $patientAptToday->appointment_order;
                })
                ->count();

            $patientAppointmentToday = [
                'id' => $patientAptToday->id,
                'token' => $patientAptToday->appointment_number,
                'session' => $session,
                'status' => $this->getQueueStatus($patientAptToday, $currentAppointmentIds),
                'queuePosition' => $queuePosition,
                'onHold' => (bool) $patientAptToday->on_hold,
            ];
        }

        $settings = BookingSetting::current();
        $now = now();
        $currentTime = $now->toTimeString();

        $morningOpen = $settings->morning_opening_time;
        $morningClose = $settings->morning_closing_time;
        $eveningOpen = $settings->evening_opening_time;
        $eveningClose = $settings->evening_closing_time;

        $currentSession = 'Morning';

        if ($morningOpen && $morningClose && $currentTime >= $morningOpen && $currentTime <= $morningClose) {
            $currentSession = 'Morning';
        } elseif ($eveningOpen && $eveningClose && $currentTime >= $eveningOpen && $currentTime <= $eveningClose) {
            $currentSession = 'Evening';
        } else {
            if ($morningOpen && $currentTime < $morningOpen) {
                $currentSession = 'Morning';
            } elseif ($morningClose && $eveningOpen && $currentTime > $morningClose && $currentTime < $eveningOpen) {
                $currentSession = 'Evening';
            } elseif ($eveningClose && $currentTime > $eveningClose) {
                $currentSession = 'Morning';
            } else {
                $currentSession = $now->hour < 14 ? 'Morning' : 'Evening';
            }
        }

        $morningTimings = null;
        if ($morningOpen && $morningClose) {
            $morningTimings = \Carbon\Carbon::parse($morningOpen)->format('g:i A') . ' - ' . \Carbon\Carbon::parse($morningClose)->format('g:i A');
        }

        $eveningTimings = null;
        if ($eveningOpen && $eveningClose) {
            $eveningTimings = \Carbon\Carbon::parse($eveningOpen)->format('g:i A') . ' - ' . \Carbon\Carbon::parse($eveningClose)->format('g:i A');
        }

        $isMorningClosed = $settings->isClosedOn($today, 'Morning');
        $isEveningClosed = $settings->isClosedOn($today, 'Evening');
        $noticeEnabled = (bool) $settings->notice_enabled;
        $noticeText = $settings->notice_text;

        // Set the custom root Blade file for this live layout view
        Inertia::setRootView('live');

        return Inertia::render('patient/LiveStatus', [
            'today' => $today->toDateString(),
            'todayFormatted' => $today->format('l, d M Y'),
            'currentSession' => $currentSession,
            'morningTimings' => $morningTimings,
            'eveningTimings' => $eveningTimings,
            'isMorningClosed' => $isMorningClosed,
            'isEveningClosed' => $isEveningClosed,
            'noticeEnabled' => $noticeEnabled,
            'noticeText' => $noticeText,
            'morningRunningToken' => $morningRunningApt ? $morningRunningApt->appointment_number : null,
            'eveningRunningToken' => $eveningRunningApt ? $eveningRunningApt->appointment_number : null,
            'morningQueue' => $morningQueue,
            'eveningQueue' => $eveningQueue,
            'patientAppointmentToday' => $patientAppointmentToday,
        ]);
    }

    /**
     * Resolve the running appointment ID per session.
     */
    private function currentAppointmentIds(Collection $appointments): array
    {
        return collect(['Morning', 'Evening'])
            ->mapWithKeys(function (string $session) use ($appointments): array {
                $id = $appointments
                    ->filter(fn (Appointment $appointment): bool => Appointment::sessionLabelForSlot($appointment->slot) === $session)
                    ->first(fn (Appointment $appointment): bool => ! $appointment->on_hold && Str::lower((string) $appointment->status) !== 'complete')
                    ?->id;

                return [$session => $id];
            })
            ->all();
    }

    /**
     * Determine the status of an appointment in the live queue.
     */
    private function getQueueStatus(Appointment $appointment, array $currentAppointmentIds): string
    {
        $session = Appointment::sessionLabelForSlot($appointment->slot);

        return match (true) {
            Str::lower((string) $appointment->status) === 'complete' => 'complete',
            $appointment->on_hold => 'on_hold',
            $appointment->id === ($currentAppointmentIds[$session] ?? null) => 'running',
            default => 'pending',
        };
    }
}
