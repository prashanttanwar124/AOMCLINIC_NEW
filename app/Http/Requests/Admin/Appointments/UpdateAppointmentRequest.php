<?php

namespace App\Http\Requests\Admin\Appointments;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Editable clinical / history columns the admin can write to from the
     * booking desk detail form. Patient identity fields are intentionally
     * excluded—they belong to the patient profile.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'purpose_of_appointment',
        'chief_complaint',
        'present_complaint',
        'associated_complaint',
        'past_history',
        'family_history_father_side',
        'family_history_mother_side',
        'history_of_vaccination',
        'addiction',
        'diet',
        'occupation',
        'number_of_children',
        'medicine_taking',
        'appetite',
        'thirst',
        'sleep',
        'urine',
        'stool',
        'pysical_examination',
        'as_a_person',
        'nature_of_person',
        'anxiety',
        'fear',
        'nature',
        'dreams',
        'desire',
        'craving',
        'diagnosis',
        'treatment',
        'medication_instructions',
        'amount',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'follow_up_day' => ['nullable', 'date'],
            'days_prescription' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'complete' => ['sometimes', 'boolean'],
            'medicines' => ['required', 'array', 'min:1'],
            'medicines.*.id' => ['required', 'string', 'max:255'],
            'medicines.*.label' => ['required', 'string', 'max:500'],
            'medicines.*.medicine_id' => ['nullable', 'string', 'max:50'],
            'medicines.*.category_id' => ['nullable', 'string', 'max:50'],
            'medicines.*.size_id' => ['nullable', 'string', 'max:50'],
            'medicines.*.quantity' => ['nullable', 'integer', 'min:0'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'string', 'max:50'],
            'blood_pressure' => ['nullable', 'string', 'max:50'],
            'pulse_rate' => ['nullable', 'string', 'max:50'],
            'spo2' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'chief_complaint' => ['required', 'string', 'max:5000'],
            'present_complaint' => ['required', 'string', 'max:5000'],
            'medication_instructions' => ['required', 'string', 'max:5000'],
            'amount' => ['required', 'string', 'max:255'],
        ];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (! in_array($field, ['chief_complaint', 'present_complaint', 'medication_instructions', 'amount'], true)) {
                $rules[$field] = ['nullable', 'string', 'max:5000'];
            }
        }

        return $rules;
    }
}
