<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = fake()->numberBetween(1, 20);

        return [
            'patient_id' => Patient::factory(),
            'appointment_date' => fake()->dateTimeBetween('now', '+5 days')->format('Y-m-d'),
            'appointment_number' => (string) $token,
            'appointment_order' => $token,
            'slot' => fake()->randomElement([Appointment::SLOT_MORNING, Appointment::SLOT_EVENING]),
            'appointment_type' => fake()->randomElement(['New', 'Follow Up']),
            'payment_type' => 'Cash',
            'purpose_of_appointment' => fake()->sentence(),
            'status' => 'pending',
            'on_hold' => false,
        ];
    }

    /**
     * Indicate that the appointment should have vitals.
     */
    public function withVitals(array $attributes = []): static
    {
        return $this->has(
            \App\Models\AppointmentVital::factory()->state($attributes),
            'vital'
        );
    }
}
