<?php

use App\Models\Patient;
use App\Models\User;

test('authenticated staff can link patient A as dependent under parent B', function () {
    $user = User::factory()->create();
    $parent = Patient::factory()->create(['name' => 'Parent Patient']);
    $child = Patient::factory()->create(['name' => 'Child Patient']);

    $this->actingAs($user)
        ->post(route('admin.patients.join', $parent), [
            'dependent_id' => $child->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($child->fresh()->parent_id)->toBe($parent->id);
    expect($child->fresh()->parent->id)->toBe($parent->id);
});

test('authenticated staff can unlink dependent patient from their parent', function () {
    $user = User::factory()->create();
    $parent = Patient::factory()->create(['name' => 'Parent Patient']);
    $child = Patient::factory()->create([
        'name' => 'Child Patient',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent_id)->toBe($parent->id);

    $this->actingAs($user)
        ->post(route('admin.patients.unlink', $child))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('validation prevents self-linking (joining patient to themselves)', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create(['name' => 'Self Patient']);

    $this->actingAs($user)
        ->post(route('admin.patients.join', $patient), [
            'dependent_id' => $patient->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dependent_id']);

    expect($patient->fresh()->parent_id)->toBeNull();
});

test('validation prevents circular dependency', function () {
    $user = User::factory()->create();

    // Create chain: A (grandparent) -> B (parent) -> C (child)
    $grandparent = Patient::factory()->create(['name' => 'Grandparent']);
    $parent = Patient::factory()->create(['name' => 'Parent', 'parent_id' => $grandparent->id]);
    $child = Patient::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

    // Try to make Grandparent A a dependent of Parent B. Since B's parent is A, B is already dependent of A.
    // If A is added as dependent under B, it would create a circular loop (A's parent is B, B's parent is A).
    $this->actingAs($user)
        ->post(route('admin.patients.join', $parent), [
            'dependent_id' => $grandparent->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dependent_id']);
});

test('validation prevents linking a patient who already has a parent account', function () {
    $user = User::factory()->create();
    $parent1 = Patient::factory()->create(['name' => 'Parent 1']);
    $parent2 = Patient::factory()->create(['name' => 'Parent 2']);
    $child = Patient::factory()->create([
        'name' => 'Child',
        'parent_id' => $parent1->id,
    ]);

    $this->actingAs($user)
        ->post(route('admin.patients.join', $parent2), [
            'dependent_id' => $child->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dependent_id']);

    expect($child->fresh()->parent_id)->toBe($parent1->id);
});

test('validation prevents linking a patient who has their own dependents', function () {
    $user = User::factory()->create();
    $parent = Patient::factory()->create(['name' => 'Target Parent']);
    $newDependent = Patient::factory()->create(['name' => 'New Dependent']);
    $childOfNewDependent = Patient::factory()->create([
        'name' => 'Child of New Dependent',
        'parent_id' => $newDependent->id,
    ]);

    // Try to make newDependent (who is already a parent of childOfNewDependent) a dependent of target parent
    $this->actingAs($user)
        ->post(route('admin.patients.join', $parent), [
            'dependent_id' => $newDependent->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dependent_id']);

    expect($newDependent->fresh()->parent_id)->toBeNull();
});

test('validation prevents adding dependents to a parent that is itself a dependent', function () {
    $user = User::factory()->create();
    $grandparent = Patient::factory()->create(['name' => 'Grandparent']);
    $parent = Patient::factory()->create(['name' => 'Parent', 'parent_id' => $grandparent->id]);
    $newDependent = Patient::factory()->create(['name' => 'New Dependent']);

    // Try to make newDependent a dependent under parent (who is already a dependent under grandparent)
    $this->actingAs($user)
        ->post(route('admin.patients.join', $parent), [
            'dependent_id' => $newDependent->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dependent_id']);

    expect($newDependent->fresh()->parent_id)->toBeNull();
});

