<?php

namespace Database\Factories;

use App\Models\MedicalCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalCertificate>
 */
class MedicalCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => \App\Models\Patient::factory(),
            'certificate_type_id' => \App\Models\CertificateType::factory(),
            'certificate_number' => null, // will auto-generate
            'issue_date' => $this->faker->date(),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'diagnosis' => $this->faker->sentence(),
            'charge_amount' => $this->faker->randomFloat(2, 5, 50),
            'payment_status' => $this->faker->randomElement(['paid', 'unpaid']),
            'notes' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
