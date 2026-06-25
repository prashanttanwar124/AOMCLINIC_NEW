<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalCertificateRequest extends FormRequest
{
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
            'certificate_type_id' => ['required', 'integer', 'exists:certificate_types,id'],
            'issue_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'diagnosis' => ['nullable', 'string'],
            'charge_amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', 'string', Rule::in(['paid', 'unpaid'])],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'void'])],
        ];
    }
}
