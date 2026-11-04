<?php

namespace App\Http\Requests\Admin;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
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
            'second_appointment_number' => $this->filled('second_appointment_number')
                ? (int) $this->input('second_appointment_number')
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_session' => ['required', 'string', Rule::in(['morning', 'evening'])],
            'appointment_number' => ['required', 'integer', 'min:1'],
            'second_appointment_number' => ['nullable', 'integer', 'min:1', 'different:appointment_number'],
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

                $appointmentDate = CarbonImmutable::parse((string) $this->input('appointment_date'));
                $appointmentSession = (string) $this->input('appointment_session');

                $appointmentsForSlot = Appointment::query()
                    ->whereDate('appointment_date', $appointmentDate)
                    ->where('slot', Appointment::slotForSession($appointmentSession));
                $selectedToken = (int) $this->input('appointment_number');
                $secondToken = $this->filled('second_appointment_number')
                    ? (int) $this->input('second_appointment_number')
                    : null;

                if ((clone $appointmentsForSlot)
                    ->where('appointment_order', $selectedToken)
                    ->exists()) {
                    $validator->errors()->add(
                        'appointment_number',
                        'Selected token is already taken.'
                    );
                }

                if ($secondToken !== null && (clone $appointmentsForSlot)
                    ->where('appointment_order', $secondToken)
                    ->exists()) {
                    $validator->errors()->add(
                        'second_appointment_number',
                        'Selected second token is already taken.'
                    );
                }
            },
        ];
    }
}
