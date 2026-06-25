<?php

namespace App\Http\Controllers\Admin;

use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Appointments\UpdateAppointmentHoldRequest;
use App\Http\Requests\Admin\Appointments\UpdateAppointmentRequest;
use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CurrentAppointmentController extends Controller
{
    /**
     * Show the active appointments for the admin booking desk.
     *
     * Defaults to today, but the admin can inspect any date that has
     * appointments by passing a `date` query parameter.
     */
    public function index(Request $request): Response
    {
        $today = today();

        $appointments = Appointment::query()
            ->with([
                'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
                'patient.appointments',
                'patient.appointments.vital',
                'vital',
            ])
            ->whereDate('appointment_date', $today->toDateString())
            ->orderByRaw(
                "CASE slot
                    WHEN '".Appointment::SLOT_MORNING."' THEN 0
                    WHEN '".Appointment::SLOT_EVENING."' THEN 1
                    ELSE 2
                END",
            )
            ->orderBy('appointment_order')
            ->get();

        $currentAppointmentIds = $this->currentAppointmentIds($appointments);

        $appointmentPayload = $appointments
            ->map(fn (Appointment $appointment): array => $this->transformAppointment($appointment, $currentAppointmentIds))
            ->values();

        return Inertia::render('admin/Booking', [
            'appointmentDate' => $today->toDateString(),
            'today' => $today->toDateString(),
            'appointments' => $appointmentPayload,
            'currentAppointmentIds' => $currentAppointmentIds,
            'summary' => $this->summary($appointmentPayload),
        ]);
    }

    /**
     * Move an appointment to or from the hold column.
     */
    public function toggleHold(UpdateAppointmentHoldRequest $request, Appointment $appointment): RedirectResponse|JsonResponse
    {
        $onHold = $request->validated('on_hold');

        if ($onHold) {
            $nextHoldOrder = (int) Appointment::query()
                ->whereDate('appointment_date', $appointment->appointment_date)
                ->where('slot', $appointment->slot)
                ->where('on_hold', true)
                ->max('hold_order');

            $appointment->update([
                'on_hold' => true,
                'hold_order' => $nextHoldOrder + 1,
            ]);
        } else {
            $appointment->update([
                'on_hold' => false,
                'hold_order' => null,
            ]);
        }

        $toast = [
            'type' => 'success',
            'message' => $onHold
                ? "Appointment {$appointment->appointment_number} moved to hold."
                : "Appointment {$appointment->appointment_number} returned to queue.",
        ];

        $sessionLabel = Appointment::sessionLabelForSlot($appointment->slot);
        broadcast(new QueueUpdated($sessionLabel));

        if ($request->expectsJson()) {
            return response()->json(['toast' => $toast]);
        }

        Inertia::flash('toast', $toast);

        return to_route('booking', [
            'date' => $appointment->appointment_date?->toDateString(),
        ]);
    }

    /**
     * Save the editable clinical form, optionally marking the case complete.
     *
     * Completing a case clears any hold and drops it out of the live queue.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse|JsonResponse
    {
        $data = $request->safe()->except('complete');
        $complete = $request->boolean('complete');

        $vitalsFields = ['temperature', 'weight', 'blood_pressure', 'pulse_rate', 'spo2', 'notes'];
        $vitalsData = array_intersect_key($data, array_flip($vitalsFields));
        $appointmentData = array_diff_key($data, array_flip($vitalsFields));

        if ($complete) {
            $appointmentData['status'] = 'complete';
            $appointmentData['on_hold'] = false;
            $appointmentData['hold_order'] = null;
        }

        $appointment->update($appointmentData);

        if (! empty($vitalsData) || $request->hasAny($vitalsFields)) {
            $appointment->vital()->updateOrCreate([], [
                'temperature' => $request->input('temperature'),
                'weight' => $request->input('weight'),
                'blood_pressure' => $request->input('blood_pressure'),
                'pulse_rate' => $request->input('pulse_rate'),
                'spo2' => $request->input('spo2'),
                'notes' => $request->input('notes'),
            ]);
        }

        if ($complete) {
            $sessionLabel = Appointment::sessionLabelForSlot($appointment->slot);
            broadcast(new QueueUpdated($sessionLabel));
        }

        $toast = [
            'type' => 'success',
            'message' => $complete
                ? "Appointment {$appointment->appointment_number} marked complete and removed from the queue."
                : "Appointment {$appointment->appointment_number} details saved.",
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'toast' => $toast,
                'redirect' => null,
            ]);
        }

        Inertia::flash('toast', $toast);

        return to_route('booking', [
            'date' => $appointment->appointment_date?->toDateString(),
        ]);
    }

    /**
     * Resolve a requested date, falling back to today when it is missing or invalid.
     */
    private function resolveDate(?string $value, CarbonInterface $fallback): CarbonInterface
    {
        if (blank($value)) {
            return $fallback;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
    /**
     * Resolve the running appointment per session (first eligible card).
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, int|null>
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
     * Build a UI-focused payload for the booking desk.
     *
     * @param  array<string, int|null>  $currentAppointmentIds
     * @return array<string, mixed>
     */
    private function transformAppointment(Appointment $appointment, array $currentAppointmentIds): array
    {
        $patient = $appointment->patient;
        $age = $patient?->date_of_birth?->age;
        $phone = $patient?->phone
            ? trim("{$patient->country_calling_code} {$patient->phone}")
            : null;
        $session = Appointment::sessionLabelForSlot($appointment->slot);
        $queueStatus = match (true) {
            Str::lower((string) $appointment->status) === 'complete' => 'complete',
            $appointment->on_hold => 'on_hold',
            $appointment->id === ($currentAppointmentIds[$session] ?? null) => 'running',
            default => 'pending',
        };

        $patientAppointments = $patient?->appointments ?? collect();
        $patientAppointments = $patientAppointments->sortBy('appointment_date')->values();

        $history = $patientAppointments->map(fn (Appointment $apt): array => [
            'id' => $apt->id,
            'appointmentDate' => $apt->appointment_date?->toDateString(),
            'appointmentDateLabel' => $apt->appointment_date?->format('d M Y'),
            'appointmentType' => $apt->appointment_type,
            'session' => Appointment::sessionLabelForSlot($apt->slot),
            'status' => $apt->status,
            'editable' => [
                'purpose_of_appointment' => $apt->purpose_of_appointment,
                'chief_complaint' => $apt->chief_complaint,
                'present_complaint' => $apt->present_complaint,
                'associated_complaint' => $apt->associated_complaint,
                'past_history' => $apt->past_history,
                'family_history_father_side' => $apt->family_history_father_side,
                'family_history_mother_side' => $apt->family_history_mother_side,
                'history_of_vaccination' => $apt->history_of_vaccination,
                'addiction' => $apt->addiction,
                'diet' => $apt->diet,
                'occupation' => $apt->occupation,
                'number_of_children' => $apt->number_of_children,
                'medicine_taking' => $apt->medicine_taking,
                'appetite' => $apt->appetite,
                'thirst' => $apt->thirst,
                'sleep' => $apt->sleep,
                'urine' => $apt->urine,
                'stool' => $apt->stool,
                'pysical_examination' => $apt->pysical_examination,
                'as_a_person' => $apt->as_a_person,
                'nature_of_person' => $apt->nature_of_person,
                'anxiety' => $apt->anxiety,
                'fear' => $apt->fear,
                'nature' => $apt->nature,
                'dreams' => $apt->dreams,
                'desire' => $apt->desire,
                'craving' => $apt->craving,
                'diagnosis' => $apt->diagnosis,
                'treatment' => $apt->treatment,
                'medication_instructions' => $apt->medication_instructions,
                'follow_up_day' => $apt->follow_up_day?->toDateString(),
                'days_prescription' => $apt->days_prescription,
                'amount' => $apt->amount,
                'medicines' => $apt->parsedMedicines(),
                'temperature' => $apt->vital?->temperature ?? '',
                'weight' => $apt->vital?->weight ?? '',
                'blood_pressure' => $apt->vital?->blood_pressure ?? '',
                'pulse_rate' => $apt->vital?->pulse_rate ?? '',
                'spo2' => $apt->vital?->spo2 ?? '',
                'notes' => $apt->vital?->notes ?? '',
            ],
            'vitals' => $apt->vital ? [
                'temperature' => $apt->vital->temperature,
                'weight' => $apt->vital->weight,
                'blood_pressure' => $apt->vital->blood_pressure,
                'pulse_rate' => $apt->vital->pulse_rate,
                'spo2' => $apt->vital->spo2,
                'notes' => $apt->vital->notes,
            ] : null,
        ])->all();

        return [
            'id' => $appointment->id,
            'history' => $history,
            'appointmentDate' => $appointment->appointment_date?->toDateString(),
            'appointmentNumber' => $appointment->appointment_number,
            'appointmentSequence' => (int) $appointment->appointment_order,
            'appointmentType' => $appointment->appointment_type,
            'amount' => $appointment->amount,
            'patientName' => $patient?->name ?? 'Walk-in patient',
            'patientNumber' => null,
            'patientId' => $patient?->id,
            'gender' => $patient?->gender ? str($patient->gender)->headline()->toString() : null,
            'age' => $age,
            'phone' => $phone ? "+{$phone}" : null,
            'email' => $patient?->email,
            'city' => $patient?->city,
            'session' => $session,
            'onHold' => $appointment->on_hold,
            'holdOrder' => $appointment->hold_order,
            'queueStatus' => $queueStatus,
            'status' => $appointment->status,
            'reasonForVisit' => $appointment->purpose_of_appointment,
            'details' => [
                'associatedComplaint' => $appointment->associated_complaint,
                'chiefComplaint' => $appointment->chief_complaint,
                'presentingComplaint' => $appointment->present_complaint,
                'medicalHistory' => $appointment->past_history,
                'paternalFamilyHistory' => $appointment->family_history_father_side,
                'maternalFamilyHistory' => $appointment->family_history_mother_side,
                'vaccinationHistory' => $appointment->history_of_vaccination,
                'addictionHistory' => $appointment->addiction,
                'dietaryHabits' => $appointment->diet,
                'occupation' => $appointment->occupation,
                'childrenCount' => is_numeric($appointment->number_of_children)
                    ? (int) $appointment->number_of_children
                    : null,
                'currentMedications' => $appointment->medicine_taking,
                'appetite' => $appointment->appetite,
                'thirst' => $appointment->thirst,
                'sleepPattern' => $appointment->sleep,
                'urination' => $appointment->urine,
                'bowelMovements' => $appointment->stool,
                'physicalExamination' => $appointment->pysical_examination,
                'personalityNotes' => $appointment->as_a_person,
                'temperament' => $appointment->nature_of_person,
                'anxietyNotes' => $appointment->anxiety,
                'fearNotes' => $appointment->fear,
                'generalNature' => $appointment->nature,
                'dreamNotes' => $appointment->dreams,
                'desires' => $appointment->desire,
                'cravings' => $appointment->craving,
                'followUpDate' => $this->formatDate($appointment->follow_up_day),
                'prescriptionDays' => $appointment->days_prescription,
                'medicationInstructions' => $appointment->medication_instructions,
                'diagnosisNotes' => $appointment->diagnosis,
                'treatmentNotes' => $appointment->treatment,
                'vitals' => $appointment->vital ? [
                    'temperature' => $appointment->vital->temperature,
                    'weight' => $appointment->vital->weight,
                    'blood_pressure' => $appointment->vital->blood_pressure,
                    'pulse_rate' => $appointment->vital->pulse_rate,
                    'spo2' => $appointment->vital->spo2,
                    'notes' => $appointment->vital->notes,
                ] : null,
            ],
            'editable' => [
                'purpose_of_appointment' => $appointment->purpose_of_appointment,
                'chief_complaint' => $appointment->chief_complaint,
                'present_complaint' => $appointment->present_complaint,
                'associated_complaint' => $appointment->associated_complaint,
                'past_history' => $appointment->past_history,
                'family_history_father_side' => $appointment->family_history_father_side,
                'family_history_mother_side' => $appointment->family_history_mother_side,
                'history_of_vaccination' => $appointment->history_of_vaccination,
                'addiction' => $appointment->addiction,
                'diet' => $appointment->diet,
                'occupation' => $appointment->occupation,
                'number_of_children' => $appointment->number_of_children,
                'medicine_taking' => $appointment->medicine_taking,
                'appetite' => $appointment->appetite,
                'thirst' => $appointment->thirst,
                'sleep' => $appointment->sleep,
                'urine' => $appointment->urine,
                'stool' => $appointment->stool,
                'pysical_examination' => $appointment->pysical_examination,
                'as_a_person' => $appointment->as_a_person,
                'nature_of_person' => $appointment->nature_of_person,
                'anxiety' => $appointment->anxiety,
                'fear' => $appointment->fear,
                'nature' => $appointment->nature,
                'dreams' => $appointment->dreams,
                'desire' => $appointment->desire,
                'craving' => $appointment->craving,
                'diagnosis' => $appointment->diagnosis,
                'treatment' => $appointment->treatment,
                'medication_instructions' => $appointment->medication_instructions,
                'follow_up_day' => $appointment->follow_up_day?->toDateString(),
                'days_prescription' => $appointment->days_prescription,
                'amount' => $appointment->amount,
                'medicines' => $appointment->parsedMedicines(),
                'temperature' => $appointment->vital?->temperature ?? '',
                'weight' => $appointment->vital?->weight ?? '',
                'blood_pressure' => $appointment->vital?->blood_pressure ?? '',
                'pulse_rate' => $appointment->vital?->pulse_rate ?? '',
                'spo2' => $appointment->vital?->spo2 ?? '',
                'notes' => $appointment->vital?->notes ?? '',
            ],
        ];
    }

    /**
     * Build summary counters for the admin header cards.
     *
     * @param  Collection<int, array<string, mixed>>  $appointments
     * @return array<string, int>
     */
    private function summary(Collection $appointments): array
    {
        return [
            'total' => $appointments->count(),
            'running' => $appointments->where('queueStatus', 'running')->count(),
            'pending' => $appointments->where('queueStatus', 'pending')->count(),
            'onHold' => $appointments->where('queueStatus', 'on_hold')->count(),
            'complete' => $appointments->where('queueStatus', 'complete')->count(),
        ];
    }

    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('d M Y');
    }
}
