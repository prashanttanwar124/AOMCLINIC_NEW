<?php

namespace App\Http\Requests\Patient\Appointments;

use App\Models\Appointment;
use App\Models\BookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePatientAppointmentRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'patient_id' => $this->filled('patient_id')
                ? (int) $this->input('patient_id')
                : ($this->user('patient')?->id),
            'appointment_session' => Str::lower((string) $this->input('appointment_session')),
            'appointment_type' => Str::lower((string) $this->input('appointment_type')),
            'appointment_number' => $this->filled('appointment_number')
                ? (int) $this->input('appointment_number')
                : null,
            'reason_for_visit' => $this->filled('reason_for_visit')
                ? trim((string) $this->input('reason_for_visit'))
                : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('patient') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_session' => ['required', 'string', Rule::in(['morning', 'evening'])],
            'appointment_number' => ['required', 'integer', 'min:1'],
            'appointment_type' => ['required', 'string', Rule::in(['new', 'follow_up'])],
            'reason_for_visit' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure validator after callbacks.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $settings = BookingSetting::current();

                if (! $settings->booking_enabled) {
                    $validator->errors()->add('appointment_date', 'Appointment booking is currently closed.');

                    return;
                }

                $selectedPatientId = (int) $this->input('patient_id');
                $patient = $this->user('patient');
                $ownerId = $patient->parent_id ?? $patient->id;

                // Validate that the selected patient belongs to this account hierarchy
                $isValid = \App\Models\Patient::where('id', $selectedPatientId)
                    ->where(function ($query) use ($ownerId) {
                        $query->where('id', $ownerId)
                              ->orWhere('parent_id', $ownerId);
                    })
                    ->exists();

                if (! $isValid) {
                    $validator->errors()->add('patient_id', 'Invalid patient selected.');
                    return;
                }

                $appointmentDate = CarbonImmutable::parse((string) $this->input('appointment_date'));
                $appointmentSession = (string) $this->input('appointment_session');
                $maxBookableDate = today()->addDays(max(((int) $settings->booking_open_days) - 1, 0));

                if ($appointmentDate->gt($maxBookableDate)) {
                    $validator->errors()->add(
                        'appointment_date',
                        "Appointments can only be booked until {$maxBookableDate->toDateString()}.",
                    );
                }

                if ($settings->isClosedOn($appointmentDate, $appointmentSession)) {
                    $validator->errors()->add(
                        'appointment_session',
                        'Selected slot is unavailable for this date.',
                    );
                }

                $capacity = $settings->capacityForSession($appointmentSession);
                $appointmentsForSlot = Appointment::query()
                    ->whereDate('appointment_date', $appointmentDate)
                    ->where('slot', Appointment::slotForSession($appointmentSession));
                $selectedToken = (int) $this->input('appointment_number');

                if ($capacity < 1 || (clone $appointmentsForSlot)->count() >= $capacity) {
                    $validator->errors()->add('appointment_session', 'Selected slot is already full.');
                }

                if ($selectedToken > $capacity || (clone $appointmentsForSlot)
                    ->where('appointment_order', $selectedToken)
                    ->exists()) {
                    $validator->errors()->add(
                        'appointment_number',
                        'Selected token is not available for this slot.',
                    );
                }

                if ((clone $appointmentsForSlot)
                    ->where('patient_id', $selectedPatientId)
                    ->exists()) {
                    $validator->errors()->add(
                        'appointment_session',
                        'This patient already has a booking for this date and session.',
                    );
                }
            },
        ];
    }
}
