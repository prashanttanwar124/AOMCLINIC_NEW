<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Arnica Montana', 'Nux Vomica', 'Rhus Tox', 'Belladonna', 'Sulphur',
                'Aconite', 'Bryonia', 'Gelsemium', 'Pulsatilla', 'Silicea', 'Lycopodium',
                'Natrum Mur', 'Calcarea Carb', 'Phosphorus', 'Hepar Sulph',
            ]).' '.fake()->uuid(),
        ];
    }
}
