<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->all(),
                    'created_at' => $role->created_at?->toDateString(),
                ];
            });

        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Role {$role->name} created successfully.",
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Guard against renaming system roles
        if (in_array($role->name, ['admin', 'staff']) && $role->name !== $validated['name']) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'System roles cannot be renamed.',
            ]);
        }

        $role->update([
            'name' => $validated['name'],
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Role {$role->name} updated successfully.",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['admin', 'staff'])) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'System roles cannot be deleted.',
            ]);
        }

        $name = $role->name;
        $role->delete();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Role {$name} deleted successfully.",
        ]);
    }
}
