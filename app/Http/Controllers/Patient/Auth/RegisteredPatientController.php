<?php

namespace App\Http\Controllers\Patient\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Auth\StorePatientRegistrationRequest;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredPatientController extends Controller
{
    /**
     * Show the patient registration screen.
     */
    public function create(): Response
    {
        return Inertia::render('patient/auth/Register');
    }

    /**
     * Store a newly registered patient.
     */
    public function store(StorePatientRegistrationRequest $request): RedirectResponse
    {
        $patient = Patient::create($request->validated());

        auth('patient')->login($patient);

        $request->session()->regenerate();

        return redirect()->route('patient.dashboard');
    }
}
