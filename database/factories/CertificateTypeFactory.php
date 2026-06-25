<?php

namespace Database\Factories;

use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateType>
 */
class CertificateTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . ' Certificate',
            'description' => $this->faker->sentence(),
            'default_charge' => $this->faker->randomFloat(2, 5, 50),
        ];
    }
}
