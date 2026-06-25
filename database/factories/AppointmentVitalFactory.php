<?php

namespace Database\Factories;

use App\Models\AppointmentVital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentVital>
 */
class AppointmentVitalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => \App\Models\Appointment::factory(),
            'temperature' => (string) fake()->randomFloat(1, 97, 102),
            'weight' => (string) fake()->numberBetween(50, 100),
            'blood_pressure' => fake()->numberBetween(110, 135) . '/' . fake()->numberBetween(70, 85),
            'pulse_rate' => (string) fake()->numberBetween(60, 95),
            'spo2' => (string) fake()->numberBetween(95, 100),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
