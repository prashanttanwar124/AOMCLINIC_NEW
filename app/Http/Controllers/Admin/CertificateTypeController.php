<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateTypeRequest;
use App\Http\Requests\Admin\UpdateCertificateTypeRequest;
use App\Models\CertificateType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CertificateTypeController extends Controller
{
    /**
     * Store a newly created certificate type.
     */
    public function store(StoreCertificateTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $certificateType = CertificateType::query()->create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Certificate type '{$certificateType->name}' created successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified certificate type.
     */
    public function update(UpdateCertificateTypeRequest $request, CertificateType $certificateType): RedirectResponse
    {
        $validated = $request->validated();

        $certificateType->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Certificate type '{$certificateType->name}' updated successfully.",
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified certificate type.
     */
    public function destroy(CertificateType $certificateType): RedirectResponse
    {
        $name = $certificateType->name;
        $certificateType->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Certificate type '{$name}' deleted successfully.",
        ]);

        return redirect()->back();
    }
}
