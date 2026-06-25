<?php

test('fresh sessions default to light appearance', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewHas('appearance', 'light');
});
