<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicalCertificateRequest;
use App\Http\Requests\Admin\UpdateMedicalCertificateRequest;
use App\Models\CertificateType;
use App\Models\MedicalCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicalCertificateController extends Controller
{
    /**
     * Display a listing of medical certificates and types.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');
        $typeId = $request->query('type_id');

        $query = MedicalCertificate::query()->with(['patient', 'certificateType']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($qp) use ($search) {
                    $qp->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                    ->orWhere('certificate_number', 'like', "%{$search}%");
            });
        }

        if (filled($status)) {
            $query->where('status', $status);
        }

        if (filled($paymentStatus)) {
            $query->where('payment_status', $paymentStatus);
        }

        if (filled($typeId)) {
            $query->where('certificate_type_id', $typeId);
        }

        $paginated = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $paginated->getCollection()->transform(function (MedicalCertificate $cert): array {
            $patient = $cert->patient;
            return [
                'id' => $cert->id,
                'patient_id' => $cert->patient_id,
                'patient_name' => $patient?->name ?? 'N/A',
                'patient_phone' => $this->formatPatientPhone($patient?->phone, $patient?->country_calling_code),
                'patient_email' => $patient?->email,
                'certificate_type_id' => $cert->certificate_type_id,
                'certificate_type_name' => $cert->certificateType?->name ?? 'N/A',
                'certificate_number' => $cert->certificate_number,
                'issue_date' => $cert->issue_date?->toDateString(),
                'start_date' => $cert->start_date?->toDateString(),
                'end_date' => $cert->end_date?->toDateString(),
                'diagnosis' => $cert->diagnosis,
                'charge_amount' => $cert->charge_amount,
                'payment_status' => $cert->payment_status,
                'notes' => $cert->notes,
                'status' => $cert->status,
            ];
        });

        $certificateTypes = CertificateType::query()->orderBy('name')->get();
        $settings = \App\Models\ClinicSetting::current();
        $clinic = [
            'clinic_name' => $settings->clinic_name,
            'doctor_name' => $settings->doctor_name,
            'doctor_qualifications' => $settings->doctor_qualifications,
            'doctor_title' => $settings->doctor_title,
            'doctor_registration_no' => $settings->doctor_registration_no,
            'clinic_registration_no' => $settings->clinic_registration_no,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'logo_url' => $settings->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) : null,
        ];

        return Inertia::render('admin/MedicalCertificates', [
            'certificates' => $paginated,
            'certificateTypes' => $certificateTypes,
            'clinic' => $clinic,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'type_id' => $typeId,
            ],
        ]);
    }

    /**
     * Store a newly created medical certificate in storage.
     */
    public function store(StoreMedicalCertificateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $cert = MedicalCertificate::query()->create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Medical certificate {$cert->certificate_number} created successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Show the printable view for a medical certificate.
     */
    public function print(MedicalCertificate $medicalCertificate): Response
    {
        $medicalCertificate->load(['patient', 'certificateType']);
        $settings = \App\Models\ClinicSetting::current();

        return Inertia::render('admin/medical-certificates/Print', [
            'certificate' => [
                'id' => $medicalCertificate->id,
                'patient_name' => $medicalCertificate->patient?->name ?? 'N/A',
                'patient_phone' => $this->formatPatientPhone($medicalCertificate->patient?->phone, $medicalCertificate->patient?->country_calling_code),
                'certificate_number' => $medicalCertificate->certificate_number,
                'issue_date' => $medicalCertificate->issue_date?->format('d M Y'),
                'start_date' => $medicalCertificate->start_date?->format('d M Y'),
                'end_date' => $medicalCertificate->end_date?->format('d M Y'),
                'diagnosis' => $medicalCertificate->diagnosis,
                'notes' => $medicalCertificate->notes,
            ],
            'clinic' => [
                'clinic_name' => $settings->clinic_name,
                'doctor_name' => $settings->doctor_name,
                'doctor_qualifications' => $settings->doctor_qualifications,
                'doctor_title' => $settings->doctor_title,
                'doctor_registration_no' => $settings->doctor_registration_no,
                'clinic_registration_no' => $settings->clinic_registration_no,
                'address' => $settings->address,
                'phone' => $settings->phone,
                'email' => $settings->email,
                'logo_url' => $settings->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) : null,
            ],
        ]);
    }

    /**
     * Update the specified medical certificate in storage.
     */
    public function update(UpdateMedicalCertificateRequest $request, MedicalCertificate $medicalCertificate): RedirectResponse
    {
        $validated = $request->validated();

        $medicalCertificate->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Medical certificate {$medicalCertificate->certificate_number} updated successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified medical certificate from storage.
     */
    public function destroy(MedicalCertificate $medicalCertificate): RedirectResponse
    {
        $number = $medicalCertificate->certificate_number;
        $medicalCertificate->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Medical certificate {$number} deleted successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Format patient phone numbers cleanly to avoid duplicate country calling codes.
     */
    private function formatPatientPhone(?string $phone, ?string $cc): ?string
    {
        if (!$phone) {
            return null;
        }
        $phone = trim($phone);
        $cc = $cc ? trim($cc) : '';
        while (true) {
            if (str_starts_with($phone, '+')) {
                $phone = substr($phone, 1);
                continue;
            }
            if ($cc !== '' && str_starts_with($phone, $cc)) {
                $phone = substr($phone, strlen($cc));
                continue;
            }
            break;
        }
        return $cc !== '' ? "+{$cc} {$phone}" : "+{$phone}";
    }
}
