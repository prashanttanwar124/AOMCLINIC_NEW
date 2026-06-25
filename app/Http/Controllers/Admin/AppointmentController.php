<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    /**
     * Show the paginated list of all appointments.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $allowedPerPage = [15, 25, 50, 100];
        $perPage = (int) $request->query('perPage', 15);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 15;
        }

        $query = Appointment::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                    ->orWhere('appointment_number', 'like', "%{$search}%");
            });
        }

        $paginated = $query->with([
            'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
            'patient.appointments',
        ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // We transform the paginated items preserving the pagination wrapper metadata
        $paginated->getCollection()->transform(function (Appointment $appointment): array {
            $patient = $appointment->patient;
            $age = $patient?->date_of_birth?->age;
            $phone = $patient?->phone
                ? trim("{$patient->country_calling_code} {$patient->phone}")
                : null;
            $session = Appointment::sessionLabelForSlot($appointment->slot);
            $queueStatus = match (true) {
                Str::lower((string) $appointment->status) === 'complete' => 'complete',
                $appointment->on_hold => 'on_hold',
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
                ],
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
                'dateOfBirth' => $patient?->date_of_birth?->toDateString(),
                'address' => $patient?->address,
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
                    'followUpDate' => $this->formatDate($appointment->follow_up_day),
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
                    'medicines' => $appointment->parsedMedicines(),
                ],
            ];
        });

        return Inertia::render('admin/Appointments', [
            'appointments' => $paginated,
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
            'perPageOptions' => $allowedPerPage,
        ]);
    }

    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('d M Y');
    }

    /**
     * Show the receipt view for an appointment.
     */
    public function receipt(Appointment $appointment): Response
    {
        $appointment->load(['patient', 'vital']);
        $clinic = \App\Models\ClinicSetting::current();

        $logoUrl = $clinic->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($clinic->logo_path) : null;

        return Inertia::render('admin/appointments/Receipt', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_date_label' => $appointment->appointment_date?->format('d M Y'),
                'appointment_time_label' => $appointment->created_at?->format('h:i A'),
                'patient_name' => $appointment->patient?->name ?? 'Walk-in patient',
                'diagnosis' => $appointment->diagnosis ?? '-',
                'days_prescription' => $appointment->days_prescription ?? 0,
                'amount' => $appointment->amount ?? '0.00',
            ],
            'clinic' => [
                'clinic_name' => $clinic->clinic_name,
                'doctor_name' => $clinic->doctor_name,
                'doctor_qualifications' => $clinic->doctor_qualifications,
                'doctor_title' => $clinic->doctor_title,
                'doctor_registration_no' => $clinic->doctor_registration_no,
                'clinic_registration_no' => $clinic->clinic_registration_no,
                'address' => $clinic->address,
                'phone' => $clinic->phone,
                'email' => $clinic->email,
                'logo_url' => $logoUrl,
            ],
        ]);
    }
}
