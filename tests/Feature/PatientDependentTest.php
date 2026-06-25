<?php

use App\Models\Patient;
use Inertia\Testing\AssertableInertia as Assert;

test('guest patients are redirected to login from dependents routes', function () {
    $this->get(route('patient.dependents'))->assertRedirect(route('patient.login'));
    $this->post(route('patient.dependents.store'), [])->assertRedirect(route('patient.login'));

    $dependent = Patient::factory()->create();
    $this->patch(route('patient.dependents.update', $dependent), [])->assertRedirect(route('patient.login'));
});

test('authenticated patients can view their dependents', function () {
    $parent = Patient::factory()->create();
    $dependent = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Child Dependent',
        'gender' => 'male',
    ]);

    $response = $this->actingAs($parent, 'patient')->get(route('patient.dependents'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Dependents')
            ->has('dependents', 1)
            ->where('dependents.0.name', 'Child Dependent')
            ->where('dependents.0.gender', 'male')
            ->where('canAddDependents', true)
        );
});

test('authenticated patients can create a new dependent without email or password', function () {
    $parent = Patient::factory()->create();

    $payload = [
        'name' => 'New Child',
        'date_of_birth' => '2018-05-10',
        'gender' => 'female',
        'address' => '123 Family Lane',
        'city' => 'Metro City',
    ];

    $response = $this->actingAs($parent, 'patient')->post(route('patient.dependents.store'), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $dependent = Patient::where('parent_id', $parent->id)->first();
    expect($dependent)->not->toBeNull()
        ->and($dependent->name)->toBe('New Child')
        ->and($dependent->date_of_birth?->format('Y-m-d'))->toBe('2018-05-10')
        ->and($dependent->gender)->toBe('female')
        ->and($dependent->address)->toBe('123 Family Lane')
        ->and($dependent->city)->toBe('Metro City')
        ->and($dependent->email)->toBeNull()
        ->and($dependent->password)->toBeNull();
});

test('authenticated patients can update their dependent details', function () {
    $parent = Patient::factory()->create();
    $dependent = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Old Name',
    ]);

    $payload = [
        'name' => 'Updated Child Name',
        'date_of_birth' => '2015-08-20',
        'gender' => 'male',
        'address' => '456 Updated St',
        'city' => 'Updated City',
    ];

    $response = $this->actingAs($parent, 'patient')->patch(route('patient.dependents.update', $dependent), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $dependent->refresh();
    expect($dependent->name)->toBe('Updated Child Name')
        ->and($dependent->date_of_birth?->format('Y-m-d'))->toBe('2015-08-20')
        ->and($dependent->city)->toBe('Updated City');
});

test('authenticated patients cannot update someone else dependent details', function () {
    $parent1 = Patient::factory()->create();
    $parent2 = Patient::factory()->create();
    $dependentOfParent2 = Patient::factory()->create([
        'parent_id' => $parent2->id,
        'name' => 'Parent 2 Child',
    ]);

    $payload = [
        'name' => 'Hacked Name',
        'date_of_birth' => '2010-01-01',
        'gender' => 'other',
    ];

    $response = $this->actingAs($parent1, 'patient')->patch(route('patient.dependents.update', $dependentOfParent2), $payload);
    $response->assertForbidden();
});

test('dependent patients can view other dependents and the primary account holder', function () {
    $parent = Patient::factory()->create([
        'name' => 'Parent Account Holder',
    ]);
    $dependent1 = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'First Dependent',
    ]);
    $dependent2 = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Second Dependent',
    ]);

    // Authenticate as $dependent1 (they should see $dependent2 and the parent, but not themselves)
    $response = $this->actingAs($dependent1, 'patient')->get(route('patient.dependents'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient/Dependents')
            ->has('dependents', 2)
            ->where('dependents.0.name', 'Parent Account Holder')
            ->where('dependents.0.is_account_holder', true)
            ->where('dependents.1.name', 'Second Dependent')
            ->where('dependents.1.is_account_holder', false)
            ->where('canAddDependents', false)
        );
});

test('dependent patients cannot add a dependent and get forbidden error', function () {
    $parent = Patient::factory()->create();
    $dependent = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'First Dependent',
    ]);

    $payload = [
        'name' => 'New Sibling',
        'date_of_birth' => '2020-01-01',
        'gender' => 'other',
        'address' => 'Same House',
        'city' => 'Metro City',
    ];

    // Authenticate as $dependent, try to add a new dependent - should fail (403)
    $response = $this->actingAs($dependent, 'patient')->post(route('patient.dependents.store'), $payload);
    $response->assertForbidden();
});

test('dependent patients can update sibling details but not themselves', function () {
    $parent = Patient::factory()->create();
    $dependent1 = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'First Dependent',
    ]);
    $dependent2 = Patient::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Second Dependent',
    ]);

    $payload = [
        'name' => 'Updated Sibling Name',
        'date_of_birth' => '2019-12-31',
        'gender' => 'female',
    ];

    // Authenticate as $dependent1, update $dependent2 (sibling)
    $response = $this->actingAs($dependent1, 'patient')->patch(route('patient.dependents.update', $dependent2), $payload);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $dependent2->refresh();
    expect($dependent2->name)->toBe('Updated Sibling Name');

    // Trying to update themselves through the dependents controller should fail (403)
    $responseSelf = $this->actingAs($dependent1, 'patient')->patch(route('patient.dependents.update', $dependent1), $payload);
    $responseSelf->assertForbidden();
});
