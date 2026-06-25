<?php

namespace App\Http\Controllers\Patient;

use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Appointments\StorePatientAppointmentRequest;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientAppointmentController extends Controller
{
    /**
     * Show patient booking form.
     */
    public function create(): Response
    {
        $settings = BookingSetting::current();
        $patient = request()->user('patient');
        $ownerId = $patient->parent_id ?? $patient->id;

        $bookablePatients = Patient::where('parent_id', $ownerId)
            ->orWhere('id', $ownerId)
            ->orderBy('name')
            ->get()
            ->map(fn (Patient $p): array => [
                'id' => $p->id,
                'name' => $p->name . ($p->id === $patient->id ? ' (Self)' : ($p->id === $ownerId ? ' (Account Holder)' : '')),
            ]);

        return Inertia::render('patient/Booking', [
            'appointmentTypes' => [
                ['label' => 'New consultation', 'value' => 'new'],
                ['label' => 'Follow up', 'value' => 'follow_up'],
            ],
            'bookablePatients' => $bookablePatients,
            'defaultPatientId' => $patient->id,
            'dateOptions' => $this->dateOptions($settings),
            'bookingEnabled' => (bool) $settings->booking_enabled,
            'noticeEnabled' => (bool) $settings->notice_enabled,
            'noticeText' => $settings->notice_text,
        ]);
    }

    /**
     * Store patient booking.
     */
    public function store(StorePatientAppointmentRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $patient = $request->user('patient');
        $selectedPatientId = (int) $validated['patient_id'];

        $appointment = DB::transaction(function () use ($validated, $selectedPatientId): Appointment {
            $slot = Appointment::slotForSession($validated['appointment_session']);
            $selectedToken = (int) $validated['appointment_number'];
            $settings = BookingSetting::current();

            if ($selectedToken > $settings->capacityForSession($validated['appointment_session'])) {
                throw ValidationException::withMessages([
                    'appointment_number' => 'Selected token is no longer available. Please choose another token.',
                ]);
            }

            $slotAppointments = Appointment::query()
                ->select('appointment_order', 'patient_id')
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->where('slot', $slot)
                ->lockForUpdate()
                ->get();

            if ($slotAppointments->contains('appointment_order', $selectedToken)) {
                throw ValidationException::withMessages([
                    'appointment_number' => 'Selected token is no longer available. Please choose another token.',
                ]);
            }

            if ($slotAppointments->contains('patient_id', $selectedPatientId)) {
                throw ValidationException::withMessages([
                    'appointment_session' => 'This patient already has a booking for this date and session.',
                ]);
            }

            return Appointment::query()->create([
                'patient_id' => $selectedPatientId,
                'appointment_date' => $validated['appointment_date'],
                'appointment_number' => (string) $selectedToken,
                'appointment_order' => $selectedToken,
                'slot' => $slot,
                'appointment_type' => Str::of($validated['appointment_type'])->replace('_', ' ')->title()->toString(),
                'purpose_of_appointment' => $validated['reason_for_visit'] ?? null,
                'status' => 'pending',
            ]);
        });

        $sessionLabel = Appointment::sessionLabelForSlot($appointment->slot);

        broadcast(new QueueUpdated($sessionLabel));

        $toast = [
            'type' => 'success',
            'message' => "Appointment booked for {$appointment->appointment_date->format('d M Y')} ({$sessionLabel}) with token {$appointment->appointment_number}.",
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'toast' => $toast,
                'redirect' => route('patient.dashboard'),
            ]);
        }

        Inertia::flash('toast', $toast);

        return to_route('patient.dashboard');
    }

    /**
     * Build date options with remaining capacity.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dateOptions(BookingSetting $settings): array
    {
        $startDate = today();
        $daysToShow = max((int) $settings->booking_open_days, 1);
        $lastDate = $startDate->copy()->addDays($daysToShow - 1);

        $bookedSlots = Appointment::query()
            ->select(['appointment_date', 'slot', 'appointment_order'])
            ->whereBetween('appointment_date', [$startDate->toDateString(), $lastDate->toDateString()])
            ->get()
            ->groupBy(
                fn (Appointment $appointment): string => "{$appointment->appointment_date->toDateString()}-".Str::lower(Appointment::sessionLabelForSlot($appointment->slot))
            );

        return collect(range(0, $daysToShow - 1))
            ->map(function (int $offset) use ($bookedSlots, $settings, $startDate): array {
                $date = CarbonImmutable::instance($startDate->copy()->addDays($offset));

                $sessions = collect(['morning' => 'Morning', 'evening' => 'Evening'])
                    ->map(function (string $label, string $value) use ($bookedSlots, $date, $settings): array {
                        $key = "{$date->toDateString()}-{$value}";
                        $capacity = $settings->capacityForSession($value);
                        $closed = ! $settings->booking_enabled || $settings->isClosedOn($date, $value);
                        $bookedTokens = $bookedSlots->get($key, collect())
                            ->pluck('appointment_order')
                            ->map(fn (mixed $sequence): int => (int) $sequence)
                            ->all();
                        $availableTokens = $closed || $capacity < 1
                            ? []
                            : collect(range(1, $capacity))
                                ->reject(fn (int $token): bool => in_array($token, $bookedTokens, true))
                                ->values()
                                ->all();
                        $remaining = count($availableTokens);

                        return [
                            'availableTokens' => $availableTokens,
                            'closed' => $closed,
                            'disabled' => $closed || $remaining < 1,
                            'label' => $label,
                            'remaining' => $remaining,
                            'value' => $value,
                        ];
                    })
                    ->values()
                    ->all();

                $disabled = collect($sessions)->every(fn (array $session): bool => (bool) $session['disabled']);

                return [
                    'label' => $date->format('D, d M Y'),
                    'value' => $date->toDateString(),
                    'sessions' => $sessions,
                    'disabled' => $disabled,
                ];
            })
            ->all();
    }
}
