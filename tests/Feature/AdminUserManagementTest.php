<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Standard setup
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('unauthenticated guests are redirected to login', function () {
    $response = $this->get(route('admin.users'));
    $response->assertRedirect(route('login'));

    $response2 = $this->get(route('admin.roles'));
    $response2->assertRedirect(route('login'));
});

test('unauthorized users without manage staff permission are forbidden', function () {
    $user = User::factory()->create();
    // No roles/permissions assigned

    $response = $this->actingAs($user)->get(route('admin.users'));
    $response->assertForbidden();

    $response2 = $this->actingAs($user)->get(route('admin.roles'));
    $response2->assertForbidden();
});

test('authorized users with manage staff permission can view users and roles list', function () {
    $user = User::factory()->create();

    // Create custom role and permission
    $permission = Permission::findOrCreate('manage staff', 'web');
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)->get(route('admin.users'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Users')
            ->has('users')
            ->has('roles')
        );

    $response2 = $this->actingAs($user)->get(route('admin.roles'));
    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Roles')
            ->has('roles')
            ->has('permissions')
        );
});

test('authorized users can create and edit users and assign roles', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $staffRole = Role::findOrCreate('staff', 'web');

    // Create user
    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'John Staff',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'roles' => ['staff'],
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'john@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->name)->toBe('John Staff');
    expect($newUser->hasRole('staff'))->toBeTrue();

    // Edit user
    $response2 = $this->actingAs($admin)->patch(route('admin.users.update', $newUser->id), [
        'name' => 'John Staff Updated',
        'email' => 'john.updated@example.com',
        'roles' => ['staff'],
    ]);

    $response2->assertRedirect();
    $newUser->refresh();
    expect($newUser->name)->toBe('John Staff Updated');
    expect($newUser->email)->toBe('john.updated@example.com');
});

test('authorized users can delete other users but not themselves', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $otherUser = User::factory()->create();

    // Prevent deleting self
    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin->id));
    $response->assertRedirect();
    expect(User::find($admin->id))->not->toBeNull();

    // Delete other user
    $response2 = $this->actingAs($admin)->delete(route('admin.users.destroy', $otherUser->id));
    $response2->assertRedirect();
    expect(User::find($otherUser->id))->toBeNull();
});

test('authorized users can create, edit and delete custom roles', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $perm1 = Permission::findOrCreate('manage patients', 'web');

    // Create custom role
    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Custom Clerk',
        'permissions' => ['manage patients'],
    ]);

    $response->assertRedirect();
    $newRole = Role::findByName('Custom Clerk');
    expect($newRole)->not->toBeNull();
    expect($newRole->hasPermissionTo('manage patients'))->toBeTrue();

    // Edit custom role
    $response2 = $this->actingAs($admin)->patch(route('admin.roles.update', $newRole->id), [
        'name' => 'Clerk Supervisor',
        'permissions' => ['manage patients'],
    ]);

    $response2->assertRedirect();
    $newRole->refresh();
    expect($newRole->name)->toBe('Clerk Supervisor');

    // Delete custom role
    $response3 = $this->actingAs($admin)->delete(route('admin.roles.destroy', $newRole->id));
    $response3->assertRedirect();
    expect(Role::where('name', 'Clerk Supervisor')->exists())->toBeFalse();
});
