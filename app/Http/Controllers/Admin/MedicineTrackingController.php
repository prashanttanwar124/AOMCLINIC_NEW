<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicineTrackingController extends Controller
{
    /**
     * Show today's appointments queue with medicine tracking and billing panel.
     */
    public function index(Request $request): Response
    {
        $today = today()->toDateString();

        $appointments = Appointment::query()
            ->with([
                'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
            ])
            ->whereDate('appointment_date', $today)
            ->where('status', 'complete')
            ->where('medicine_status', false)
            ->orderByRaw(
                "CASE slot
                    WHEN '".Appointment::SLOT_MORNING."' THEN 0
                    WHEN '".Appointment::SLOT_EVENING."' THEN 1
                    ELSE 2
                END",
            )
            ->orderBy('appointment_order')
            ->get();

        $selectedId = $request->query('selected');
        if (! $selectedId && $appointments->isNotEmpty()) {
            $selectedId = (string) $appointments->first()->id;
        }

        $selectedAppointment = null;
        $previousMedicines = [];

        if ($selectedId) {
            $selectedAppointment = $appointments->firstWhere('id', $selectedId);
            if (! $selectedAppointment) {
                $selectedAppointment = Appointment::with([
                    'patient:id,name,email,phone,country_calling_code,date_of_birth,gender,city,address',
                ])->find($selectedId);
            }

            if ($selectedAppointment && $selectedAppointment->patient) {
                $previousMedicines = Appointment::query()
                    ->where('patient_id', $selectedAppointment->patient_id)
                    ->where('status', 'complete')
                    ->where('id', '!=', $selectedAppointment->id)
                    ->orderBy('appointment_date')
                    ->orderBy('id')
                    ->get(['id', 'appointment_date', 'medicines', 'treatment', 'diagnosis'])
                    ->map(fn (Appointment $apt): array => [
                        'id' => $apt->id,
                        'date' => $apt->appointment_date?->toDateString(),
                        'dateLabel' => $apt->appointment_date?->format('d M Y'),
                        'medicines' => $apt->parsedMedicines(),
                        'treatment' => $apt->treatment,
                        'diagnosis' => $apt->diagnosis,
                    ])
                    ->all();
            }
        }

        $transformedAppointments = $appointments->map(fn (Appointment $apt): array => [
            'id' => $apt->id,
            'patientId' => $apt->patient?->id,
            'patientName' => $apt->patient?->name ?? 'Walk-in patient',
            'gender' => $apt->patient?->gender ? str($apt->patient->gender)->headline()->toString() : null,
            'age' => $apt->patient?->date_of_birth?->age,
            'phone' => $apt->patient?->phone ? "+{$apt->patient->country_calling_code} {$apt->patient->phone}" : null,
            'session' => Appointment::sessionLabelForSlot($apt->slot),
            'appointmentNumber' => $apt->appointment_number,
            'appointmentSequence' => (int) $apt->appointment_order,
            'appointmentType' => $apt->appointment_type,
            'status' => $apt->status,
            'medicineStatus' => (bool) $apt->medicine_status,
            'amount' => $apt->amount,
            'paymentType' => $apt->payment_type,
            'diagnosis' => $apt->diagnosis,
            'treatment' => $apt->treatment,
            'medicationInstructions' => $apt->medication_instructions,
        ])->all();

        $medicinesInventory = MedicineStock::with(['medicine', 'category', 'size'])
            ->get()
            ->map(fn (MedicineStock $stock): array => [
                'id' => $stock->id,
                'medicine_id' => $stock->medicine_id,
                'category_id' => $stock->category_id,
                'size_id' => $stock->size_id,
                'name' => $stock->medicine?->name ?? '',
                'category' => $stock->category?->name,
                'size' => $stock->size?->name,
                'quantity' => $stock->quantity,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $categories = Category::orderBy('name')->pluck('name')->all();
        $sizes = Size::orderBy('name')->pluck('name')->all();

        // Get matching transformed active appointment details
        $transformedSelected = null;
        if ($selectedAppointment) {
            $transformedSelected = [
                'id' => $selectedAppointment->id,
                'patientId' => $selectedAppointment->patient?->id,
                'patientName' => $selectedAppointment->patient?->name ?? 'Walk-in patient',
                'gender' => $selectedAppointment->patient?->gender ? str($selectedAppointment->patient->gender)->headline()->toString() : null,
                'age' => $selectedAppointment->patient?->date_of_birth?->age,
                'phone' => $selectedAppointment->patient?->phone ? "+{$selectedAppointment->patient->country_calling_code} {$selectedAppointment->patient->phone}" : null,
                'session' => Appointment::sessionLabelForSlot($selectedAppointment->slot),
                'appointmentNumber' => $selectedAppointment->appointment_number,
                'appointmentSequence' => (int) $selectedAppointment->appointment_order,
                'appointmentType' => $selectedAppointment->appointment_type,
                'status' => $selectedAppointment->status,
                'medicineStatus' => (bool) $selectedAppointment->medicine_status,
                'amount' => $selectedAppointment->amount,
                'paymentType' => $selectedAppointment->payment_type,
                'diagnosis' => $selectedAppointment->diagnosis,
                'treatment' => $selectedAppointment->treatment,
                'medicationInstructions' => $selectedAppointment->medication_instructions,
                'medicines' => $selectedAppointment->parsedMedicines(),
            ];
        }

        return Inertia::render('admin/MedicineTracking', [
            'appointments' => $transformedAppointments,
            'selectedId' => $selectedId ? (int) $selectedId : null,
            'selectedAppointment' => $transformedSelected,
            'previousMedicines' => $previousMedicines,
            'medicinesInventory' => $medicinesInventory,
            'categories' => $categories,
            'sizes' => $sizes,
        ]);
    }

    /**
     * Update current prescription medicines and log payment details.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'medicines' => ['nullable', 'array'],
            'medicines.*.name' => ['required', 'string', 'max:255'],
            'medicines.*.category' => ['required', 'string', 'max:50'],
            'medicines.*.size' => ['required', 'string', 'max:50'],
            'medicines.*.quantity' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', 'string', 'max:50'],
        ]);

        $existingMedicines = $appointment->medicines ?? [];
        $existingQuantities = [];
        foreach ($existingMedicines as $med) {
            if (is_array($med) && isset($med['id'])) {
                $existingQuantities[$med['id']] = (int) ($med['quantity'] ?? 0);
            }
        }

        $storedMedicines = [];
        $newIds = [];
        foreach ($validated['medicines'] ?? [] as $item) {
            $stock = MedicineStock::whereHas('medicine', function ($q) use ($item) {
                $q->where('name', $item['name']);
            })
                ->whereHas('category', function ($q) use ($item) {
                    $q->where('name', $item['category']);
                })
                ->whereHas('size', function ($q) use ($item) {
                    $q->where('name', $item['size']);
                })
                ->first();

            if ($stock) {
                $idKey = "{$stock->medicine_id} | {$stock->category_id} | {$stock->size_id}";
                $newIds[] = $idKey;

                $quantityReduction = (int) $item['quantity'];
                $existingReduction = $existingQuantities[$idKey] ?? 0;
                $diff = $quantityReduction - $existingReduction;

                if ($diff !== 0) {
                    $stock->update([
                        'quantity' => max(0, $stock->quantity - $diff),
                    ]);
                }

                $storedMedicines[] = [
                    'id' => $idKey,
                    'label' => trim(strtoupper($stock->medicine?->name ?? '').' | '.($stock->category?->name ?? '').' | '.($stock->size?->name ?? '')),
                    'quantity' => $quantityReduction,
                ];
            } else {
                $idKey = trim(strtoupper($item['name']).' | '.strtoupper($item['category']).' | '.strtoupper($item['size']));
                $storedMedicines[] = [
                    'id' => $idKey,
                    'label' => trim(strtoupper($item['name']).' | '.strtoupper($item['category']).' | '.strtoupper($item['size'])),
                    'quantity' => (int) $item['quantity'],
                ];
            }
        }

        // Restore stock for any removed medicines
        foreach ($existingMedicines as $med) {
            if (is_array($med) && isset($med['id']) && ! in_array($med['id'], $newIds)) {
                $idParts = explode('|', $med['id']);
                if (count($idParts) === 3) {
                    $medId = (int) trim($idParts[0]);
                    $catId = (int) trim($idParts[1]);
                    $sizeId = (int) trim($idParts[2]);

                    $stock = MedicineStock::where('medicine_id', $medId)
                        ->where('category_id', $catId)
                        ->where('size_id', $sizeId)
                        ->first();

                    if ($stock) {
                        $stock->update([
                            'quantity' => $stock->quantity + (int) ($med['quantity'] ?? 0),
                        ]);
                    }
                }
            }
        }

        $appointment->update([
            'medicines' => $storedMedicines,
            'amount' => (string) $validated['amount'],
            'payment_type' => $validated['payment_type'],
            'medicine_status' => true,
        ]);

        $patientName = $appointment->patient?->name ?? 'Walk-in patient';

        return redirect()->route('admin.medicine-tracking')->with('toast', [
            'type' => 'success',
            'message' => "Medicine tracking and payment updated for {$patientName}.",
        ]);
    }
}
