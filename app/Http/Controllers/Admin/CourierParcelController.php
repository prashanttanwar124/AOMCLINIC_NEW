<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourierParcelRequest;
use App\Http\Requests\Admin\UpdateCourierParcelRequest;
use App\Models\CourierParcel;
use App\Models\MedicineStock;
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

        $query = CourierParcel::query()->with(['patient']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($qp) use ($search) {
                    $qp->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
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
            return [
                'id' => $parcel->id,
                'patient_id' => $parcel->patient_id,
                'patient_name' => $patient?->name ?? 'N/A',
                'patient_phone' => $patient?->phone ? "+{$patient->country_calling_code} {$patient->phone}" : null,
                'patient_email' => $patient?->email,
                'parcel_status' => $parcel->parcel_status,
                'parcel_date' => $parcel->parcel_date?->toDateString(),
                'amount' => $parcel->amount,
                'payment_status' => $parcel->payment_status,
                'medicines' => $parcel->medicines ?? [],
                'address' => $parcel->address,
                'notes' => $parcel->notes,
                'delivered_date' => $parcel->delivered_date?->toDateString(),
                'instructions_given' => (bool) $parcel->instructions_given,
                'instruction_note' => $parcel->instruction_note,
            ];
        });

        // Get medicines inventory for the dropdown list
        $medicinesInventory = MedicineStock::with(['medicine', 'size'])
            ->get()
            ->map(function (MedicineStock $stock): string {
                $name = $stock->medicine?->name ?? '';
                $size = $stock->size?->name ?? '';
                return trim(strtoupper($name).' | '.strtoupper($size));
            })
            ->unique()
            ->values()
            ->all();

        return Inertia::render('admin/CourierParcels', [
            'parcels' => $paginated,
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
