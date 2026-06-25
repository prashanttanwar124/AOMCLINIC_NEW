<?php

namespace Database\Seeders;

use App\Models\BookingSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            MedicineSeeder::class,
            AppointmentSeeder::class,
        ]);

        BookingSetting::query()->firstOrCreate([], [
            'morning_slot_capacity' => 60,
            'evening_slot_capacity' => 20,
            'booking_enabled' => true,
            'booking_open_days' => 2,
            'morning_opening_time' => '09:00',
            'morning_closing_time' => '13:00',
            'evening_opening_time' => '17:00',
            'evening_closing_time' => '21:00',
            'clinic_closures' => [],
            'closed_days' => [],
            'notice_enabled' => false,
            'notice_text' => null,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole('admin');
    }
}
