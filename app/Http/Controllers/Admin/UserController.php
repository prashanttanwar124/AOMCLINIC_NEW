<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $usersQuery = User::query()
            ->when(filled($search), function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });

        $users = $usersQuery->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // Transform collection to include roles
        $users->getCollection()->transform(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'created_at' => $user->created_at?->toDateString(),
            ];
        });

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default()],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles($validated['roles']);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "User {$user->name} created successfully.",
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::default()],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->input('password')),
            ]);
        }

        $user->syncRoles($validated['roles']);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "User {$user->name} updated successfully.",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'You cannot delete your own account.',
            ]);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "User {$name} deleted successfully.",
        ]);
    }
}
