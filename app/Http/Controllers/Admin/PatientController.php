<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /**
     * Search patients by name, email, or phone.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->query('query');

        $patients = Patient::query()
            ->with(['parent', 'children'])
            ->search($search)
            ->orderBy('name')
            ->limit(25)
            ->get();

        return response()->json(
            $patients->map(function (Patient $patient): array {
                $effectivePhone = $patient->phone
                    ? "+{$patient->country_calling_code} {$patient->phone}"
                    : ($patient->parent?->phone ? "+{$patient->parent->country_calling_code} {$patient->parent->phone}" : null);

                $rawAddress = $patient->address ?: $patient->parent?->address;
                $rawCity = $patient->city ?: $patient->parent?->city;
                $deliveryAddress = collect([$rawAddress, $rawCity])
                    ->filter(fn ($v) => filled($v))
                    ->unique()
                    ->implode(', ');

                $compactPhone = $patient->phone
                    ? "+{$patient->country_calling_code}{$patient->phone}"
                    : ($patient->parent?->phone ? "+{$patient->parent->country_calling_code}{$patient->parent->phone}" : null);

                $dependentSuffix = $patient->parent ? " [Dependent of {$patient->parent->name}]" : '';

                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email ?: $patient->parent?->email,
                    'phone' => $effectivePhone,
                    'address' => $deliveryAddress !== '' ? $deliveryAddress : $rawAddress,
                    'city' => $rawCity,
                    'delivery_address' => $deliveryAddress !== '' ? $deliveryAddress : $rawAddress,
                    'parent_id' => $patient->parent_id,
                    'parent_name' => $patient->parent?->name,
                    'has_dependents' => $patient->children->isNotEmpty(),
                    'children' => $patient->children->map(fn (Patient $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'phone' => $child->phone ? "+{$child->country_calling_code} {$child->phone}" : $effectivePhone,
                        'address' => collect([$child->address ?: $rawAddress, $child->city ?: $rawCity])->filter(fn ($v) => filled($v))->unique()->implode(', ') ?: $rawAddress,
                        'parent_id' => $patient->id,
                        'parent_name' => $patient->name,
                    ])->values()->all(),
                    'label' => $patient->name.' (#'.$patient->id.')'.$dependentSuffix.($compactPhone ? " - {$compactPhone}" : ($patient->email ? " - {$patient->email}" : '')),
                ];
            })
        );
    }

    /**
     * Get the consolidated clinical and delivery history of a patient.
     */
    public function history(Patient $patient): JsonResponse
    {
        $appointments = $patient->appointments()
            ->with('vital')
            ->orderByDesc('appointment_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($apt) => [
                'type' => 'appointment',
                'id' => $apt->id,
                'date' => $apt->appointment_date?->toDateString(),
                'title' => 'Appointment: '.$apt->appointment_number,
                'appointment_type' => $apt->appointment_type,
                'status' => $apt->status,
                'amount' => $apt->amount,
                'slot' => $apt->slot,
                'purpose' => $apt->purpose_of_appointment,
                'complaint' => $apt->chief_complaint,
                'presenting_complaint' => $apt->present_complaint,
                'associated_complaint' => $apt->associated_complaint,
                'past_history' => $apt->past_history,
                'diagnosis' => $apt->diagnosis,
                'treatment' => $apt->treatment,
                'medication_instructions' => $apt->medication_instructions,
                'medicines' => $apt->medicines ?? [],
                'vitals' => $apt->vital ? [
                    'weight' => $apt->vital->weight,
                    'temp' => $apt->vital->temperature,
                    'bp' => $apt->vital->blood_pressure,
                    'pulse' => $apt->vital->pulse_rate,
                    'spo2' => $apt->vital->spo2,
                ] : null,
            ]);

        $parcels = $patient->courierParcels()
            ->orderByDesc('parcel_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($parcel) => [
                'type' => 'parcel',
                'id' => $parcel->id,
                'date' => $parcel->parcel_date?->toDateString(),
                'title' => 'Courier Parcel',
                'parcel_status' => $parcel->parcel_status,
                'payment_status' => $parcel->payment_status,
                'amount' => $parcel->amount,
                'medicines' => $parcel->medicines ?? [],
                'address' => $parcel->address,
                'complaints' => $parcel->complaints,
                'days_prescription' => $parcel->days_prescription,
                'notes' => $parcel->notes,
                'delivered_date' => $parcel->delivered_date?->toDateString(),
                'instructions_given' => (bool) $parcel->instructions_given,
                'instruction_note' => $parcel->instruction_note,
            ]);

        $timeline = $appointments->concat($parcels)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone ? "+{$patient->country_calling_code} {$patient->phone}" : null,
            ],
            'timeline' => $timeline,
        ]);
    }

    /**
     * Show the paginated list of all patients with search filter.
     */
    public function index(Request $request): Response
    {
        $id = $request->query('id');
        $search = $request->query('search');
        $filter = $request->query('filter', 'all');

        $query = Patient::query();

        if ($filter === 'dependents') {
            $query->whereNotNull('parent_id');
        } elseif ($filter === 'account_holders') {
            $query->whereNull('parent_id');
        }

        if (filled($search)) {
            $query->search($search);
        }

        if (filled($id)) {
            $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [(int) $id]);
        }

        $paginated = $query->with(['appointments', 'children', 'parent'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $paginated->getCollection()->transform(function (Patient $patient): array {
            $age = $patient->date_of_birth?->age;
            $phone = $patient->phone
                ? trim("{$patient->country_calling_code} {$patient->phone}")
                : ($patient->parent?->phone ? trim("{$patient->parent->country_calling_code} {$patient->parent->phone}") : null);
            $effectiveAddress = $patient->address ?: $patient->parent?->address;
            $effectiveCity = $patient->city ?: $patient->parent?->city;

            $todayDateString = today()->toDateString();
            $appointments = $patient->appointments
                ->sortByDesc(fn (Appointment $appointment): array => [
                    $appointment->appointment_date?->toDateString() === $todayDateString ? 1 : 0,
                    $appointment->appointment_date?->toDateString() ?? '',
                    $appointment->id,
                ])
                ->values()
                ->map(function (Appointment $appointment) use ($patient, $age, $phone, $effectiveAddress, $effectiveCity): array {
                    $session = Appointment::sessionLabelForSlot($appointment->slot);
                    $queueStatus = match (true) {
                        Str::lower((string) $appointment->status) === 'complete' => 'complete',
                        $appointment->on_hold => 'on_hold',
                        default => 'pending',
                    };

                    return [
                        'id' => $appointment->id,
                        'appointmentDate' => $appointment->appointment_date?->toDateString(),
                        'appointmentDateLabel' => $appointment->appointment_date?->format('d M Y'),
                        'appointmentNumber' => $appointment->appointment_number,
                        'appointmentSequence' => (int) $appointment->appointment_order,
                        'appointmentType' => $appointment->appointment_type,
                        'amount' => $appointment->amount,
                        'patientName' => $patient->name,
                        'patientNumber' => null,
                        'patientId' => $patient->id,
                        'gender' => $patient->gender ? str($patient->gender)->headline()->toString() : null,
                        'age' => $age,
                        'phone' => $phone ? "+{$phone}" : null,
                        'email' => $patient->email,
                        'city' => $effectiveCity,
                        'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                        'address' => $effectiveAddress,
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
                            'childrenCount' => is_numeric($appointment->number_of_children) ? (int) $appointment->number_of_children : null,
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
                            'followUpDate' => $appointment->follow_up_day?->format('d M Y'),
                            'prescriptionDays' => $appointment->days_prescription,
                            'medicationInstructions' => $appointment->medication_instructions,
                            'diagnosisNotes' => $appointment->diagnosis,
                            'treatmentNotes' => $appointment->treatment,
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
                        ],
                    ];
                })->all();

            $history = collect($appointments)->map(fn (array $apt): array => [
                'id' => $apt['id'],
                'appointmentDate' => $apt['appointmentDate'],
                'appointmentDateLabel' => $apt['appointmentDateLabel'] ?? '',
                'appointmentType' => $apt['appointmentType'],
                'session' => $apt['session'],
                'status' => $apt['status'],
                'editable' => $apt['editable'],
            ])->all();

            $appointments = collect($appointments)->map(function (array $apt) use ($history): array {
                $apt['history'] = $history;

                return $apt;
            })->all();

            return [
                'id' => $patient->id,
                'patientNumber' => null,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $phone ? "+{$phone}" : null,
                'rawPhone' => $patient->phone ?: $patient->parent?->phone,
                'countryCode' => $patient->country_code ?? $patient->parent?->country_code ?? 'IN',
                'countryCallingCode' => $patient->country_calling_code ?? $patient->parent?->country_calling_code ?? '91',
                'gender' => $patient->gender ? str($patient->gender)->headline()->toString() : null,
                'genderValue' => $patient->gender,
                'age' => $age,
                'city' => $effectiveCity,
                'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                'address' => $effectiveAddress,
                'appointments' => $appointments,
                'children' => $patient->children->map(fn (Patient $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'email' => $child->email,
                    'phone' => $child->phone
                        ? trim("+{$child->country_calling_code} {$child->phone}")
                        : ($phone ? "+{$phone}" : null),
                    'gender' => $child->gender ? str($child->gender)->headline()->toString() : null,
                    'age' => $child->date_of_birth?->age,
                ])->all(),
                'parent' => $patient->parent ? [
                    'id' => $patient->parent->id,
                    'name' => $patient->parent->name,
                    'email' => $patient->parent->email,
                    'phone' => $patient->parent->phone ? trim("+{$patient->parent->country_calling_code} {$patient->parent->phone}") : null,
                ] : null,
            ];
        });

        return Inertia::render('admin/Patients', [
            'patients' => $paginated,
            'filters' => [
                'id' => $id,
                'search' => $search,
                'filter' => $filter,
            ],
        ]);
    }

    /**
     * Store a newly created patient from the admin registry.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $isDependent = filled($request->input('parent_id'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(Patient::class, 'email')],
            'phone' => [$isDependent ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'country_calling_code' => ['nullable', 'string', 'max:6'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'city' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'address' => ['nullable', 'string', 'max:1000', 'not_regex:/[<>]/'],
            'password' => ['nullable', 'string', Password::default()],
            'parent_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        $parent = filled($validated['parent_id'] ?? null)
            ? Patient::find((int) $validated['parent_id'])
            : null;

        if ($parent && $parent->parent_id !== null) {
            $parent = Patient::find($parent->parent_id) ?? $parent;
        }

        $callingCode = ltrim((string) ($validated['country_calling_code'] ?? $parent?->country_calling_code ?? '91'), '+') ?: '91';
        $rawPhone = trim((string) ($validated['phone'] ?? ''));

        if ($rawPhone !== '' && str_starts_with($rawPhone, "+{$callingCode}")) {
            $rawPhone = trim(substr($rawPhone, strlen("+{$callingCode}")));
        }

        $payload = [
            'name' => $validated['name'],
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
            'phone' => $rawPhone !== '' ? $rawPhone : null,
            'country_code' => strtoupper((string) ($validated['country_code'] ?? $parent?->country_code ?? 'IN')) ?: 'IN',
            'country_calling_code' => $callingCode,
            'date_of_birth' => filled($validated['date_of_birth'] ?? null) ? $validated['date_of_birth'] : null,
            'gender' => filled($validated['gender'] ?? null) ? $validated['gender'] : null,
            'city' => filled($validated['city'] ?? null) ? $validated['city'] : $parent?->city,
            'address' => filled($validated['address'] ?? null) ? $validated['address'] : $parent?->address,
            'parent_id' => $parent?->id,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $patient = Patient::create($payload);

        if ($request->expectsJson()) {
            $effectivePhone = $patient->phone
                ? "+{$patient->country_calling_code} {$patient->phone}"
                : ($parent?->phone ? "+{$parent->country_calling_code} {$parent->phone}" : null);

            $deliveryAddress = collect([$patient->address, $patient->city])
                ->filter(fn ($v) => filled($v))
                ->unique()
                ->implode(', ');

            return response()->json([
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => $effectivePhone,
                    'address' => $deliveryAddress !== '' ? $deliveryAddress : $patient->address,
                    'city' => $patient->city,
                    'delivery_address' => $deliveryAddress !== '' ? $deliveryAddress : $patient->address,
                    'parent_id' => $patient->parent_id,
                    'parent_name' => $parent?->name,
                    'has_dependents' => false,
                    'children' => [],
                    'label' => $patient->name.' (#'.$patient->id.')'.($effectivePhone ? " - {$effectivePhone}" : ''),
                ],
                'message' => "Patient {$patient->name} added successfully.",
            ]);
        }

        return to_route('admin.patients', ['id' => $patient->id])->with('toast', [
            'type' => 'success',
            'message' => "Patient {$patient->name} added successfully.",
        ]);
    }

    /**
     * Update an existing patient's profile from the admin registry.
     */
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $isDependent = $patient->parent_id !== null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(Patient::class, 'email')->ignore($patient->id)],
            'phone' => [$isDependent ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'country_calling_code' => ['nullable', 'string', 'max:6'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'city' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'address' => ['nullable', 'string', 'max:1000', 'not_regex:/[<>]/'],
            'password' => ['nullable', 'string', Password::default()],
        ]);

        $callingCode = ltrim((string) ($validated['country_calling_code'] ?? $patient->country_calling_code ?? '91'), '+') ?: '91';
        $rawPhone = trim((string) ($validated['phone'] ?? ''));

        if ($rawPhone !== '' && str_starts_with($rawPhone, "+{$callingCode}")) {
            $rawPhone = trim(substr($rawPhone, strlen("+{$callingCode}")));
        }

        $payload = [
            'name' => $validated['name'],
            'email' => filled($validated['email'] ?? null) ? $validated['email'] : null,
            'phone' => $rawPhone !== '' ? $rawPhone : null,
            'country_code' => strtoupper((string) ($validated['country_code'] ?? $patient->country_code ?? 'IN')) ?: 'IN',
            'country_calling_code' => $callingCode,
            'date_of_birth' => filled($validated['date_of_birth'] ?? null) ? $validated['date_of_birth'] : null,
            'gender' => filled($validated['gender'] ?? null) ? $validated['gender'] : null,
            'city' => filled($validated['city'] ?? null) ? $validated['city'] : null,
            'address' => filled($validated['address'] ?? null) ? $validated['address'] : null,
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        }

        $patient->update($payload);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Patient {$patient->name} updated successfully.",
        ]);
    }

    /**
     * Join another patient (dependent) to this patient (parent) account.
     */
    public function join(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'dependent_id' => ['required', 'integer', 'exists:patients,id'],
        ]);

        $dependentId = $validated['dependent_id'];

        if ($dependentId === $patient->id) {
            return back()->withErrors(['dependent_id' => 'A patient cannot be joined to themselves.']);
        }

        $dependent = Patient::findOrFail($dependentId);

        if ($dependent->parent_id !== null) {
            return back()->withErrors(['dependent_id' => 'This patient is already linked to another account. Please unlink them first.']);
        }

        if ($dependent->children()->exists()) {
            return back()->withErrors(['dependent_id' => 'This patient has their own dependents and cannot be linked as a dependent.']);
        }

        if ($patient->parent_id !== null) {
            return back()->withErrors(['dependent_id' => 'A dependent patient account cannot have dependents.']);
        }

        // Check for circular dependency
        $current = $patient;
        while ($current !== null) {
            if ($current->id === $dependent->id) {
                return back()->withErrors(['dependent_id' => 'This would create a circular relationship.']);
            }
            $current = $current->parent;
        }

        $dependent->parent_id = $patient->id;
        $dependent->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$dependent->name} successfully added as a dependent under {$patient->name}'s account.",
        ]);
    }

    /**
     * Unlink this patient from their parent account.
     */
    public function unlink(Patient $patient): RedirectResponse
    {
        $parent = $patient->parent;

        $patient->parent_id = null;
        $patient->save();

        $parentName = $parent ? $parent->name : 'parent';

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$patient->name} unlinked from {$parentName}'s account successfully.",
        ]);
    }
}
