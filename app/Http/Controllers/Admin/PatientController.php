<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /**
     * Search patients by name, email, or phone.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->query('query');

        $patients = Patient::query()
            ->when(filled($search), function ($query) use ($search) {
                if (is_numeric($search) && strlen($search) < 7) {
                    $query->where('id', (int) $search);
                } else {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }
            })
            ->limit(20)
            ->get();

        return response()->json(
            $patients->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone ? "+{$patient->country_calling_code} {$patient->phone}" : null,
                'address' => $patient->address,
                'parent_id' => $patient->parent_id,
                'has_dependents' => $patient->children()->exists(),
                'label' => $patient->name . " (#" . $patient->id . ")" . ($patient->phone ? " - +{$patient->country_calling_code}{$patient->phone}" : ($patient->email ? " - {$patient->email}" : "")),
            ])
        );
    }

    /**
     * Get the consolidated clinical and delivery history of a patient.
     */
    public function history(Patient $patient): \Illuminate\Http\JsonResponse
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
                'title' => 'Appointment: ' . $apt->appointment_number,
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

        $query = Patient::query();

        if (filled($id)) {
            $query->where('id', $id);
        } elseif (filled($search)) {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search) && strlen($search) < 7) {
                    $q->where('id', (int) $search);
                } else {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }
            });
        }

        $paginated = $query->with(['appointments', 'children', 'parent'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $paginated->getCollection()->transform(function (Patient $patient): array {
            $age = $patient->date_of_birth?->age;
            $phone = $patient->phone
                ? trim("{$patient->country_calling_code} {$patient->phone}")
                : null;

            $appointments = $patient->appointments->sortBy('appointment_date')->values()->map(function (Appointment $appointment) use ($patient, $age, $phone): array {
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
                    'city' => $patient->city,
                    'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                    'address' => $patient->address,
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
                'gender' => $patient->gender ? str($patient->gender)->headline()->toString() : null,
                'age' => $age,
                'city' => $patient->city,
                'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                'address' => $patient->address,
                'appointments' => $appointments,
                'children' => $patient->children->map(fn (Patient $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'email' => $child->email,
                    'phone' => $child->phone ? trim("+{$child->country_calling_code} {$child->phone}") : null,
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
            ],
        ]);
    }

    /**
     * Join another patient (dependent) to this patient (parent) account.
     */
    public function join(Request $request, Patient $patient): \Illuminate\Http\RedirectResponse
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
    public function unlink(Patient $patient): \Illuminate\Http\RedirectResponse
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

