<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Fillable([
    'morning_slot_capacity',
    'evening_slot_capacity',
    'booking_enabled',
    'booking_open_days',
    'morning_opening_time',
    'morning_closing_time',
    'evening_opening_time',
    'evening_closing_time',
    'clinic_closures',
    'closed_days',
    'notice_enabled',
    'notice_text',
])]
class BookingSetting extends Model
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'morning_slot_capacity' => 60,
        'evening_slot_capacity' => 20,
        'booking_enabled' => true,
        'booking_open_days' => 2,
        'notice_enabled' => false,
        'closed_days' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_enabled' => 'boolean',
            'clinic_closures' => 'array',
            'closed_days' => 'array',
            'notice_enabled' => 'boolean',
        ];
    }

    /**
     * Resolve current booking settings or defaults.
     */
    public static function current(): self
    {
        return static::query()->latest('id')->first() ?? new self;
    }

    /**
     * Get slot capacity for session.
     */
    public function capacityForSession(string $session): int
    {
        return match (Str::lower($session)) {
            'morning' => (int) $this->morning_slot_capacity,
            'evening' => (int) $this->evening_slot_capacity,
            default => 0,
        };
    }

    /**
     * Determine whether clinic is closed for date/session.
     */
    public function isClosedOn(CarbonInterface $date, string $session): bool
    {
        $normalizedSession = match (Str::lower(trim($session))) {
            'm', 'morning' => 'morning',
            'e', 'evening' => 'evening',
            default => Str::lower(trim($session)),
        };

        if (is_array($this->closed_days)) {
            $closedDays = array_map('intval', $this->closed_days);
            if (in_array((int) $date->dayOfWeek, $closedDays, true)) {
                return true;
            }
        }

        $dateStr = $date->toDateString();

        foreach ($this->clinic_closures ?? [] as $closure) {
            $closureDate = substr(trim((string) Arr::get($closure, 'date', '')), 0, 10);
            if ($closureDate !== $dateStr) {
                continue;
            }

            $slots = collect(Arr::get($closure, 'slot', []))
                ->map(fn (mixed $slot): string => match (Str::lower(trim((string) $slot))) {
                    'm', 'morning' => 'morning',
                    'e', 'evening' => 'evening',
                    default => Str::lower(trim((string) $slot)),
                });

            if ($slots->contains($normalizedSession)) {
                return true;
            }
        }

        return false;
    }
}
