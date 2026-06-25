<?php

use App\Models\BookingSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('unauthenticated guests are redirected to login from booking settings', function () {
    $response = $this->get(route('admin.booking-settings'));
    $response->assertRedirect(route('login'));

    $response2 = $this->patch(route('admin.booking-settings.update'), []);
    $response2->assertRedirect(route('login'));
});

test('unauthorized users without manage staff permission cannot access booking settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.booking-settings'));
    $response->assertForbidden();

    $response2 = $this->actingAs($user)->patch(route('admin.booking-settings.update'), []);
    $response2->assertForbidden();
});

test('authorized users with manage staff permission can view booking settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    // Ensure at least one setting exists
    BookingSetting::query()->create([
        'morning_slot_capacity' => 45,
        'evening_slot_capacity' => 15,
        'booking_enabled' => true,
        'booking_open_days' => 5,
        'closed_days' => [0],
        'notice_text' => 'Weekly closed notice',
    ]);

    $response = $this->actingAs($user)->get(route('admin.booking-settings'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/BookingSettings')
            ->where('settings.morning_slot_capacity', 45)
            ->where('settings.evening_slot_capacity', 15)
            ->where('settings.booking_enabled', true)
            ->where('settings.booking_open_days', 5)
            ->where('settings.closed_days', [0])
            ->where('settings.notice_text', 'Weekly closed notice')
        );
});

test('authorized users can update booking settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    BookingSetting::query()->create([
        'morning_slot_capacity' => 10,
        'evening_slot_capacity' => 10,
        'booking_enabled' => false,
        'booking_open_days' => 1,
    ]);

    $payload = [
        'morning_slot_capacity' => 25,
        'evening_slot_capacity' => 35,
        'booking_enabled' => true,
        'booking_open_days' => 7,
        'morning_opening_time' => '09:00',
        'morning_closing_time' => '13:00',
        'evening_opening_time' => '17:00',
        'evening_closing_time' => '21:00',
        'clinic_closures' => [
            ['date' => '2026-06-15', 'slot' => ['Morning', 'Evening']],
        ],
        'closed_days' => [0, 6],
        'notice_enabled' => true,
        'notice_text' => 'Dynamic Notice Text',
    ];

    $response = $this->actingAs($user)->patch(route('admin.booking-settings.update'), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = BookingSetting::current();
    expect($settings->morning_slot_capacity)->toBe(25)
        ->and($settings->evening_slot_capacity)->toBe(35)
        ->and($settings->booking_enabled)->toBeTrue()
        ->and($settings->booking_open_days)->toBe(7)
        ->and(substr($settings->morning_opening_time, 0, 5))->toBe('09:00')
        ->and(substr($settings->morning_closing_time, 0, 5))->toBe('13:00')
        ->and($settings->clinic_closures)->toBe([
            ['date' => '2026-06-15', 'slot' => ['Morning', 'Evening']],
        ])
        ->and($settings->closed_days)->toBe([0, 6])
        ->and($settings->notice_enabled)->toBeTrue()
        ->and($settings->notice_text)->toBe('Dynamic Notice Text');
});

test('booking settings validation prevents invalid parameters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    $payload = [
        'morning_slot_capacity' => -5, // Invalid capacity
        'evening_slot_capacity' => 20,
        'booking_enabled' => 'not-a-boolean', // Invalid boolean
        'booking_open_days' => 0, // Invalid days
        'morning_opening_time' => '09:00',
        'morning_closing_time' => '13:00',
        'evening_opening_time' => '17:00',
        'evening_closing_time' => '21:00',
        'clinic_closures' => [
            ['date' => 'invalid-date', 'slot' => ['Afternoon']], // Invalid date format and slot type
        ],
        'closed_days' => ['invalid-day'], // Invalid closed days format
        'notice_enabled' => false,
    ];

    $response = $this->actingAs($user)->patch(route('admin.booking-settings.update'), $payload);
    $response->assertSessionHasErrors([
        'morning_slot_capacity',
        'booking_enabled',
        'booking_open_days',
        'clinic_closures.0.date',
        'clinic_closures.0.slot.0',
        'closed_days.0',
    ]);
});

test('booking settings normalizes empty opening/closing times to null', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage staff', 'web'));

    BookingSetting::query()->create([
        'morning_opening_time' => '09:00',
        'morning_closing_time' => '13:00',
        'evening_opening_time' => '17:00',
        'evening_closing_time' => '21:00',
    ]);

    $payload = [
        'morning_slot_capacity' => 25,
        'evening_slot_capacity' => 35,
        'booking_enabled' => true,
        'booking_open_days' => 7,
        'morning_opening_time' => '', // empty
        'morning_closing_time' => '   ', // empty string spaces
        'evening_opening_time' => null, // null
        'evening_closing_time' => '21:00', // filled
        'clinic_closures' => [],
        'notice_enabled' => true,
    ];

    $response = $this->actingAs($user)->patch(route('admin.booking-settings.update'), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = BookingSetting::current();
    expect($settings->morning_opening_time)->toBeNull()
        ->and($settings->morning_closing_time)->toBeNull()
        ->and($settings->evening_opening_time)->toBeNull()
        ->and(substr($settings->evening_closing_time, 0, 5))->toBe('21:00');
});
