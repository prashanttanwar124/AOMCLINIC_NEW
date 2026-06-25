<?php

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Size;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest users are redirected to login', function () {
    $this->get(route('admin.medicines'))->assertRedirect(route('login'));
    $this->post(route('admin.medicines.store'))->assertRedirect(route('login'));
});

test('authenticated staff can view medicines list, categories, and sizes', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => '200C']);
    $size = Size::factory()->create(['name' => '30ml']);
    $medicine = Medicine::factory()->create([
        'name' => 'Arnica',
    ]);
    MedicineStock::factory()->create([
        'medicine_id' => $medicine->id,
        'category_id' => $category->id,
        'size_id' => $size->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($user)->get(route('admin.medicines'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Medicines')
            ->has('medicines.data', 1)
            ->where('medicines.data.0.name', 'Arnica')
            ->where('medicines.data.0.total_quantity', 10)
            ->has('categories', 1)
            ->where('categories.0.name', '200C')
            ->has('sizes', 1)
            ->where('sizes.0.name', '30ml')
        );
});

test('authenticated staff can filter medicines by name', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $size = Size::factory()->create();

    $m1 = Medicine::factory()->create([
        'name' => 'Arnica Montana',
    ]);
    MedicineStock::factory()->create([
        'medicine_id' => $m1->id,
        'category_id' => $category->id,
        'size_id' => $size->id,
    ]);
    $m2 = Medicine::factory()->create([
        'name' => 'Nux Vomica',
    ]);
    MedicineStock::factory()->create([
        'medicine_id' => $m2->id,
        'category_id' => $category->id,
        'size_id' => $size->id,
    ]);

    $response = $this->actingAs($user)->get(route('admin.medicines', ['search' => 'Arnica']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Medicines')
            ->has('medicines.data', 1)
            ->where('medicines.data.0.name', 'Arnica Montana')
        );
});

test('authenticated staff can create new category option', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.categories.store'), [
        'name' => '1M',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', ['name' => '1M']);
});

test('authenticated staff cannot create duplicate category options', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => '200C']);

    $response = $this->actingAs($user)->post(route('admin.categories.store'), [
        'name' => '200C',
    ]);

    $response->assertSessionHasErrors('name');
});

test('authenticated staff can delete category option', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => '30C']);

    $response = $this->actingAs($user)->delete(route('admin.categories.destroy', $category));

    $response->assertRedirect();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('authenticated staff can create new size option', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.sizes.store'), [
        'name' => '100ml',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sizes', ['name' => '100ml']);
});

test('authenticated staff cannot create duplicate size options', function () {
    $user = User::factory()->create();
    Size::factory()->create(['name' => '15ml']);

    $response = $this->actingAs($user)->post(route('admin.sizes.store'), [
        'name' => '15ml',
    ]);

    $response->assertSessionHasErrors('name');
});

test('authenticated staff can delete size option', function () {
    $user = User::factory()->create();
    $size = Size::factory()->create(['name' => '50ml']);

    $response = $this->actingAs($user)->delete(route('admin.sizes.destroy', $size));

    $response->assertRedirect();
    $this->assertDatabaseMissing('sizes', ['id' => $size->id]);
});

test('authenticated staff can create medicine variations', function () {
    $user = User::factory()->create();
    $category1 = Category::factory()->create(['name' => '30C']);
    $category2 = Category::factory()->create(['name' => '200C']);
    $size1 = Size::factory()->create(['name' => '30ml']);
    $size2 = Size::factory()->create(['name' => '100ml']);

    $response = $this->actingAs($user)->post(route('admin.medicines.store'), [
        'name' => 'Belladonna',
        'variations' => [
            ['category_id' => $category1->id, 'size_id' => $size1->id, 'quantity' => 5],
            ['category_id' => $category1->id, 'size_id' => $size2->id, 'quantity' => 10],
            ['category_id' => $category2->id, 'size_id' => $size1->id, 'quantity' => 15],
            ['category_id' => $category2->id, 'size_id' => $size2->id, 'quantity' => 20],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('medicines', [
        'name' => 'Belladonna',
    ]);

    $med = Medicine::where('name', 'Belladonna')->first();

    $this->assertDatabaseHas('medicine_stocks', [
        'medicine_id' => $med->id,
        'category_id' => $category1->id,
        'size_id' => $size1->id,
        'quantity' => 5,
    ]);

    $this->assertDatabaseHas('medicine_stocks', [
        'medicine_id' => $med->id,
        'category_id' => $category2->id,
        'size_id' => $size2->id,
        'quantity' => 20,
    ]);
});

test('authenticated staff can update medicine variation quantity', function () {
    $user = User::factory()->create();
    $medicine = Medicine::factory()->create();
    $stock = MedicineStock::factory()->create([
        'medicine_id' => $medicine->id,
        'quantity' => 8,
    ]);

    $response = $this->actingAs($user)->patch(route('admin.medicines.quantity.update', $stock), [
        'quantity' => 25,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('medicine_stocks', [
        'id' => $stock->id,
        'quantity' => 25,
    ]);
});

test('authenticated staff can delete medicine variation', function () {
    $user = User::factory()->create();
    $medicine = Medicine::factory()->create();
    $stock = MedicineStock::factory()->create([
        'medicine_id' => $medicine->id,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.medicines.destroy', $stock));

    $response->assertRedirect();
    $this->assertDatabaseMissing('medicine_stocks', ['id' => $stock->id]);
});

test('authenticated staff can update category option', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => '30C']);

    $response = $this->actingAs($user)->patch(route('admin.categories.update', $category), [
        'name' => '200C',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => '200C',
    ]);
});

test('authenticated staff can update size option', function () {
    $user = User::factory()->create();
    $size = Size::factory()->create(['name' => '30ml']);

    $response = $this->actingAs($user)->patch(route('admin.sizes.update', $size), [
        'name' => '50ml',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sizes', [
        'id' => $size->id,
        'name' => '50ml',
    ]);
});
