<?php

namespace App\Http\Requests\Patient\Auth;

use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePatientRegistrationRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->input('country_code', 'IN'),
            'country_calling_code' => $this->input('country_calling_code', '91'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Patient::class)],
            'phone' => ['required', 'string', 'max:30'],
            'country_code' => ['required', 'string', 'size:2'],
            'country_calling_code' => ['required', 'string', 'max:6'],
            'date_of_birth' => ['required', Rule::date()->beforeToday()],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
