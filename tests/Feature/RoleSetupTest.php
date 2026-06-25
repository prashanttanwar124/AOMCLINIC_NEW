<?php

use App\Actions\Fortify\CreateNewUser;
use Spatie\Permission\Models\Role;

test('the application seeds the expected user roles', function () {
    $this->seed();

    expect(Role::query()->pluck('name')->all())
        ->toContain('admin', 'staff')
        ->not->toContain('patient');
});

test('newly registered users are created without a patient role', function () {
    $user = app(CreateNewUser::class)->create([
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($user->roles)->toHaveCount(0);
});
