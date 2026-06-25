<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineStock>
 */
class MedicineStockFactory extends Factory
{
    protected $model = MedicineStock::class;

    public function definition(): array
    {
        return [
            'medicine_id' => Medicine::factory(),
            'category_id' => Category::factory(),
            'size_id' => Size::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'quantity_reduction' => 1,
        ];
    }
}
