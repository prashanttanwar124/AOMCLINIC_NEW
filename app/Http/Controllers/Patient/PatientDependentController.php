<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientDependentController extends Controller
{
    /**
     * Display the patient's dependents list.
     */
    public function index(Request $request): Response
    {
        $patient = $request->user('patient');
        $ownerId = $patient->parent_id ?? $patient->id;

        if ($patient->parent_id !== null) {
            $dependentsQuery = Patient::where(function ($query) use ($ownerId, $patient) {
                $query->where('parent_id', $ownerId)
                      ->where('id', '!=', $patient->id);
            })->orWhere('id', $ownerId);
        } else {
            $dependentsQuery = Patient::where('parent_id', $ownerId);
        }

        $dependents = $dependentsQuery->orderBy('name')
            ->get()
            ->map(fn (Patient $child): array => [
                'id' => $child->id,
                'name' => $child->name,
                'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
                'gender' => $child->gender,
                'address' => $child->address,
                'city' => $child->city,
                'is_account_holder' => $child->id === $ownerId,
            ]);

        return Inertia::render('patient/Dependents', [
            'dependents' => $dependents,
            'canAddDependents' => $patient->parent_id === null,
        ]);
    }

    /**
     * Add a new dependent.
     */
    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user('patient');

        // A dependent patient is not allowed to add dependents
        if ($patient->parent_id !== null) {
            abort(403, 'A dependent patient account cannot add dependents.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'string', 'in:male,female,other,prefer_not_to_say'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $dependent = new Patient($validated);
        $dependent->parent_id = $patient->id; // Linked directly to parent (since patient has parent_id = null here)
        $dependent->save();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Dependent added successfully.',
        ]);
    }

    /**
     * Update an existing dependent's details.
     */
    public function update(Request $request, Patient $dependent): RedirectResponse
    {
        $patient = $request->user('patient');
        $ownerId = $patient->parent_id ?? $patient->id;

        // Ensure the dependent belongs to this account and is not the logged-in user themselves or the parent account holder
        if ($dependent->parent_id !== $ownerId || $dependent->id === $patient->id || $dependent->id === $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'string', 'in:male,female,other,prefer_not_to_say'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $dependent->update($validated);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Dependent details updated successfully.',
        ]);
    }
}
