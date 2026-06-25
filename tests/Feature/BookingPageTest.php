<?php

use App\Models\User;

test('guests are redirected to the login page for booking', function () {
    $response = $this->get(route('booking'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the booking page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('booking'));

    $response->assertOk();
});
