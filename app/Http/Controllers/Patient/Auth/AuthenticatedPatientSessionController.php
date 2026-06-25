<?php

namespace App\Http\Controllers\Patient\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Auth\StorePatientLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedPatientSessionController extends Controller
{
    /**
     * Show the patient login screen.
     */
    public function create(): Response
    {
        return Inertia::render('patient/auth/Login');
    }

    /**
     * Authenticate the patient.
     *
     * @throws ValidationException
     */
    public function store(StorePatientLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('patient')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('patient.dashboard', absolute: false));
    }

    /**
     * Destroy the patient session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('patient')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('patient.login');
    }
}
