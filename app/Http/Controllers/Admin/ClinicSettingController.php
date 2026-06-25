<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClinicSettingController extends Controller
{
    /**
     * Display the clinic settings page.
     */
    public function index(): Response
    {
        $settings = ClinicSetting::current();

        return Inertia::render('admin/ClinicSettings', [
            'settings' => [
                'clinic_name' => $settings->clinic_name,
                'doctor_name' => $settings->doctor_name,
                'doctor_qualifications' => $settings->doctor_qualifications,
                'doctor_title' => $settings->doctor_title,
                'doctor_registration_no' => $settings->doctor_registration_no,
                'clinic_registration_no' => $settings->clinic_registration_no,
                'address' => $settings->address,
                'phone' => $settings->phone,
                'email' => $settings->email,
                'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            ],
        ]);
    }

    /**
     * Update the clinic settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clinic_name' => ['nullable', 'string', 'max:255'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_qualifications' => ['nullable', 'string', 'max:255'],
            'doctor_title' => ['nullable', 'string', 'max:255'],
            'doctor_registration_no' => ['nullable', 'string', 'max:255'],
            'clinic_registration_no' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // 2MB max
        ]);

        $settings = ClinicSetting::current();
        if (!$settings->exists) {
            $settings = new ClinicSetting();
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $settings->logo_path = $request->file('logo')->store('clinic', 'public');
        }

        $settings->fill(Arr::except($validated, ['logo']));
        $settings->save();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Clinic settings updated successfully.',
        ]);
    }
}
