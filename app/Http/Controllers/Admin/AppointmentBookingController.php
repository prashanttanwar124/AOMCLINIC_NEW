<?php

namespace App\Http\Controllers\Admin;

use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminAppointmentRequest;
use App\Models\Appointment;
use App\Models\BookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentBookingController extends Controller
{
    /**
     * Show admin booking form.
     */
    public function create(Request $request): Response
    {
        $settings = BookingSetting::current();

        return Inertia::render('admin/BookAppointment', [
            'appointmentTypes' => [
                ['label' => 'New consultation', 'value' => 'new'],
                ['label' => 'Follow up', 'value' => 'follow_up'],
            ],
            'dateOptions' => $this->dateOptions($settings),
        ]);
    }

    /**
     * Store admin booking.
     */
    public function store(StoreAdminAppointmentRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $appointment = DB::transaction(function () use ($validated): Appointment {
            $slot = Appointment::slotForSession($validated['appointment_session']);
            $selectedToken = (int) $validated['appointment_number'];
            $patientId = (int) $validated['patient_id'];

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

            if ($slotAppointments->contains('patient_id', $patientId)) {
                throw ValidationException::withMessages([
                    'appointment_session' => 'This patient already has a booking for this date and session.',
                ]);
            }

            return Appointment::query()->create([
                'patient_id' => $patientId,
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
                'redirect' => route('booking'),
            ]);
        }

        Inertia::flash('toast', $toast);

        return to_route('booking');
    }

    /**
     * Build date options with remaining capacity for admin.
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
                        $closed = $settings->isClosedOn($date, $value);
                        $bookedTokens = $bookedSlots->get($key, collect())
                            ->pluck('appointment_order')
                            ->map(fn (mixed $sequence): int => (int) $sequence)
                            ->all();

                        $maxBooked = count($bookedTokens) > 0 ? max($bookedTokens) : 0;
                        
                        $availableTokens = collect(range(1, max($capacity, $maxBooked)))
                            ->reject(fn (int $token): bool => in_array($token, $bookedTokens, true))
                            ->values()
                            ->all();

                        if (empty($availableTokens)) {
                            $availableTokens[] = $maxBooked + 1;
                        }

                        $remaining = count($availableTokens);

                        return [
                            'availableTokens' => $availableTokens,
                            'closed' => $closed,
                            'disabled' => false,
                            'label' => $label,
                            'remaining' => $remaining,
                            'value' => $value,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'label' => $date->format('D, d M Y'),
                    'value' => $date->toDateString(),
                    'sessions' => $sessions,
                    'disabled' => false,
                ];
            })
            ->all();
    }
}
