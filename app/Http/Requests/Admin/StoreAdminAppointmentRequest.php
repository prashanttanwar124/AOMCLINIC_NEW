<?php

namespace App\Http\Requests\Admin;

use App\Models\Appointment;
use App\Models\BookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdminAppointmentRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
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
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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

                $patientId = (int) $this->input('patient_id');
                $appointmentDate = CarbonImmutable::parse((string) $this->input('appointment_date'));
                $appointmentSession = (string) $this->input('appointment_session');

                $appointmentsForSlot = Appointment::query()
                    ->whereDate('appointment_date', $appointmentDate)
                    ->where('slot', Appointment::slotForSession($appointmentSession));
                $selectedToken = (int) $this->input('appointment_number');

                if ((clone $appointmentsForSlot)
                    ->where('appointment_order', $selectedToken)
                    ->exists()) {
                    $validator->errors()->add(
                        'appointment_number',
                        'Selected token is already taken.'
                    );
                }

                if ((clone $appointmentsForSlot)
                    ->where('patient_id', $patientId)
                    ->exists()) {
                    $validator->errors()->add(
                        'appointment_session',
                        'This patient already has a booking for this date and session.'
                    );
                }
            },
        ];
    }
}
