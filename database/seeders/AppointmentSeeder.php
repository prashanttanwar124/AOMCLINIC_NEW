<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Seed 10 dummy appointments for today across the morning and evening
     * sessions so the admin booking desk has data to work with.
     */
    public function run(): void
    {
        $today = today()->toDateString();

        $cases = [
            ['Morning', 'New', 'Recurring migraine with aura', 'Throbbing left-sided headache for 3 weeks', 'Nausea and photophobia'],
            ['Morning', 'Follow Up', 'Acidity and bloating after meals', 'Burning sensation in upper abdomen', 'Belching, irregular appetite'],
            ['Morning', 'New', 'Chronic lower back pain', 'Stiffness worse in the morning', 'Radiating pain to right leg'],
            ['Morning', 'New', 'Seasonal allergic rhinitis', 'Sneezing and watery eyes', 'Worse on dust exposure'],
            ['Morning', 'Follow Up', 'Hair fall and dandruff', 'Diffuse hair thinning for 6 months', 'Itchy scalp'],
            ['Morning', 'New', 'Anxiety and disturbed sleep', 'Restlessness and overthinking', 'Difficulty falling asleep'],
            ['Evening', 'New', 'Knee joint pain', 'Pain on climbing stairs', 'Mild swelling in left knee'],
            ['Evening', 'Follow Up', 'Skin rash on forearms', 'Itchy red patches', 'Aggravated at night'],
            ['Evening', 'New', 'Frequent cold and cough', 'Recurrent throat infections', 'Low immunity, fatigue'],
            ['Evening', 'New', 'Irregular digestion', 'Alternating constipation and loose motion', 'Gas and discomfort'],
        ];

        // Continue numbering after any appointments that already exist for
        // today so the seeder can be run safely without colliding with the
        // unique (date, slot, order) constraint.
        $orders = [
            'Morning' => (int) Appointment::whereDate('appointment_date', $today)
                ->where('slot', Appointment::SLOT_MORNING)
                ->max('appointment_order'),
            'Evening' => (int) Appointment::whereDate('appointment_date', $today)
                ->where('slot', Appointment::SLOT_EVENING)
                ->max('appointment_order'),
        ];

        $i = 0;
        foreach ($cases as [$session, $type, $purpose, $chiefComplaint, $associated]) {
            $orders[$session]++;
            $order = $orders[$session];

            $patient = Patient::factory()->create();

            $factory = Appointment::factory();
            if ($i % 2 === 0) {
                $factory = $factory->withVitals();
            }
            $i++;

            $factory->create([
                'patient_id' => $patient->id,
                'appointment_date' => $today,
                'appointment_number' => (string) $order,
                'appointment_order' => $order,
                'slot' => Appointment::slotForSession($session),
                'appointment_type' => $type,
                'amount' => $type === 'New' ? '500' : '300',
                'purpose_of_appointment' => $purpose,
                'chief_complaint' => $chiefComplaint,
                'associated_complaint' => $associated,
                'appetite' => 'Moderate',
                'thirst' => 'Normal',
                'sleep' => 'Disturbed',
                'status' => 'pending',
                'on_hold' => false,
            ]);
        }
    }
}
