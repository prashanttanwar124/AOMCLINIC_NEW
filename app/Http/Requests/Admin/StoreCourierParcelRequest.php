<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourierParcelRequest extends FormRequest
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
            'parcel_status' => ['required', 'string', Rule::in(['order_received', 'packed', 'dispatched', 'in_transit', 'delivered', 'returned'])],
            'parcel_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', 'string', Rule::in(['unpaid', 'paid'])],
            'medicines' => ['nullable', 'array'],
            'medicines.*' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'delivered_date' => ['nullable', 'date'],
            'instructions_given' => ['nullable', 'boolean'],
            'instruction_note' => ['nullable', 'string'],
        ];
    }
}
