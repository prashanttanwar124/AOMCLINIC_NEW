<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourierParcelRequest;
use App\Http\Requests\Admin\UpdateCourierParcelRequest;
use App\Models\CourierParcel;
use App\Models\MedicineStock;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierParcelController extends Controller
{
    /**
     * Display a listing of courier parcels.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');

        $query = CourierParcel::query()->with(['patient.parent']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($qp) use ($search) {
                    $qp->search($search);
                })
                    ->orWhere('complaints', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (filled($status)) {
            $query->where('parcel_status', $status);
        }

        if (filled($paymentStatus)) {
            $query->where('payment_status', $paymentStatus);
        }

        $paginated = $query->orderByDesc('parcel_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $paginated->getCollection()->transform(function (CourierParcel $parcel): array {
            $patient = $parcel->patient;
            $effectivePhone = $patient?->phone
                ? "+{$patient->country_calling_code} {$patient->phone}"
                : ($patient?->parent?->phone ? "+{$patient->parent->country_calling_code} {$patient->parent->phone}" : null);

            $fallbackAddress = collect([
                $patient?->address ?: $patient?->parent?->address,
                $patient?->city ?: $patient?->parent?->city,
            ])->filter(fn ($v) => filled($v))->unique()->implode(', ');

            return [
                'id' => $parcel->id,
                'patient_id' => $parcel->patient_id,
                'patient_name' => $patient?->name ?? 'N/A',
                'patient_phone' => $effectivePhone,
                'patient_email' => $patient?->email ?: $patient?->parent?->email,
                'parcel_status' => $parcel->parcel_status,
                'parcel_date' => $parcel->parcel_date?->toDateString(),
                'amount' => $parcel->amount,
                'payment_status' => $parcel->payment_status,
                'medicines' => $parcel->medicines ?? [],
                'address' => filled($parcel->address) ? $parcel->address : ($fallbackAddress !== '' ? $fallbackAddress : null),
                'complaints' => $parcel->complaints,
                'days_prescription' => $parcel->days_prescription,
                'notes' => $parcel->notes,
                'delivered_date' => $parcel->delivered_date?->toDateString(),
                'instructions_given' => (bool) $parcel->instructions_given,
                'instruction_note' => $parcel->instruction_note,
            ];
        });

        // Get medicines inventory for the dropdown list
        $medicinesInventory = MedicineStock::with(['medicine', 'category', 'size'])
            ->get()
            ->map(function (MedicineStock $stock): string {
                $name = $stock->medicine?->name ?? '';
                $cat = $stock->category?->name ?? '';
                $size = $stock->size?->name ?? '';

                return collect([strtoupper($name), strtoupper($cat), strtoupper($size)])
                    ->filter(fn ($v) => $v !== '')
                    ->implode(' | ');
            })
            ->unique()
            ->values()
            ->all();

        $summary = [
            'total' => CourierParcel::count(),
            'delivered' => CourierParcel::where('parcel_status', 'delivered')->count(),
            'in_progress' => CourierParcel::whereIn('parcel_status', ['order_received', 'packed', 'dispatched', 'in_transit'])->count(),
            'unpaid_count' => CourierParcel::where('payment_status', 'unpaid')->count(),
            'unpaid_amount' => (float) CourierParcel::where('payment_status', 'unpaid')->sum('amount'),
            'paid_amount' => (float) CourierParcel::where('payment_status', 'paid')->sum('amount'),
        ];

        return Inertia::render('admin/CourierParcels', [
            'parcels' => $paginated,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ],
            'medicinesInventory' => $medicinesInventory,
        ]);
    }

    /**
     * Store a newly created courier parcel in storage.
     */
    public function store(StoreCourierParcelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (blank($validated['address'] ?? null)) {
            $patient = Patient::with('parent')->find($validated['patient_id']);
            if ($patient) {
                $autoAddress = collect([
                    $patient->address ?: $patient->parent?->address,
                    $patient->city ?: $patient->parent?->city,
                ])->filter(fn ($v) => filled($v))->unique()->implode(', ');

                if ($autoAddress !== '') {
                    $validated['address'] = $autoAddress;
                }
            }
        }

        if (blank($validated['address'] ?? null)) {
            $patient = \App\Models\Patient::with('parent')->find($validated['patient_id']);
            if ($patient) {
                $autoAddress = collect([
                    $patient->address ?: $patient->parent?->address,
                    $patient->city ?: $patient->parent?->city,
                ])->filter(fn ($v) => filled($v))->unique()->implode(', ');

                if ($autoAddress !== '') {
                    $validated['address'] = $autoAddress;
                }
            }
        }

        $parcel = CourierParcel::query()->create($validated);

        $toast = [
            'type' => 'success',
            'message' => "Courier parcel for {$parcel->patient?->name} created successfully.",
        ];

        Inertia::flash('toast', $toast);

        return to_route('admin.courier-parcels');
    }

    /**
     * Update the specified courier parcel in storage.
     */
    public function update(UpdateCourierParcelRequest $request, CourierParcel $courierParcel): RedirectResponse
    {
        $validated = $request->validated();

        $courierParcel->update($validated);

        $toast = [
            'type' => 'success',
            'message' => 'Courier parcel updated successfully.',
        ];

        Inertia::flash('toast', $toast);

        return to_route('admin.courier-parcels');
    }

    /**
     * Remove the specified courier parcel from storage.
     */
    public function destroy(CourierParcel $courierParcel): RedirectResponse
    {
        $courierParcel->delete();

        $toast = [
            'type' => 'success',
            'message' => 'Courier parcel deleted successfully.',
        ];

        Inertia::flash('toast', $toast);

        return to_route('admin.courier-parcels');
    }
}
