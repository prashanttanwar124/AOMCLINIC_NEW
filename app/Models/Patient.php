<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'phone',
    'country_code',
    'country_calling_code',
    'date_of_birth',
    'gender',
    'address',
    'city',
    'password',
    'parent_id',
])]
#[Hidden(['password', 'remember_token'])]
class Patient extends Authenticatable
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Format a text value so the first letter of each word is capitalized.
     */
    public static function formatTitleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/[ \t]+/', ' ', trim($value)) ?? trim($value);

        if ($cleaned === '') {
            return '';
        }

        return mb_convert_case($cleaned, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normalize patient name in Title Case when saving to the database.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::formatTitleCase($value),
        );
    }

    /**
     * Normalize patient city in Title Case when saving to the database.
     */
    protected function city(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::formatTitleCase($value),
        );
    }

    /**
     * Normalize patient address in Title Case when saving to the database.
     */
    protected function address(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::formatTitleCase($value),
        );
    }

    /**
     * Get appointments booked by patient.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get parent patient.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'parent_id');
    }

    /**
     * Get child patients.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Patient::class, 'parent_id');
    }

    /**
     * Get courier parcels for the patient.
     */
    public function courierParcels(): HasMany
    {
        return $this->hasMany(CourierParcel::class);
    }

    /**
     * Scope a query to search patients by any combination of first name,
     * last name, partial phone number, email, city, address, or ID.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $trimmed = trim((string) $search);

        if ($trimmed === '') {
            return $query;
        }

        $tokens = preg_split('/[\s,]+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $query->where(function (Builder $outer) use ($tokens): void {
            foreach ($tokens as $token) {
                $outer->where(function (Builder $q) use ($token): void {
                    if (preg_match('/^#(\d+)$/', $token, $matches)) {
                        $q->where('id', (int) $matches[1]);

                        return;
                    }

                    $q->where('name', 'like', "%{$token}%")
                        ->orWhere('phone', 'like', "%{$token}%")
                        ->orWhere('email', 'like', "%{$token}%")
                        ->orWhere('city', 'like', "%{$token}%")
                        ->orWhere('address', 'like', "%{$token}%")
                        ->orWhereHas('parent', function (Builder $pq) use ($token): void {
                            $pq->where('name', 'like', "%{$token}%")
                                ->orWhere('phone', 'like', "%{$token}%")
                                ->orWhere('email', 'like', "%{$token}%")
                                ->orWhere('city', 'like', "%{$token}%")
                                ->orWhere('address', 'like', "%{$token}%");
                        });

                    $digits = preg_replace('/\D+/', '', $token) ?? '';

                    if ($digits !== '') {
                        if ($digits !== $token) {
                            $q->orWhere('phone', 'like', "%{$digits}%")
                                ->orWhereHas('parent', fn (Builder $pq) => $pq->where('phone', 'like', "%{$digits}%"));
                        }

                        $q->orWhere('country_calling_code', $digits);

                        if (ctype_digit($token)) {
                            $q->orWhere('id', (int) $token)
                                ->orWhere('parent_id', (int) $token);
                        }

                        if (str_starts_with($token, '+') && strlen($digits) > 2) {
                            $q->orWhere('phone', 'like', '%'.substr($digits, 2).'%')
                                ->orWhere('phone', 'like', '%'.substr($digits, 1).'%')
                                ->orWhereHas('parent', fn (Builder $pq) => $pq
                                    ->where('phone', 'like', '%'.substr($digits, 2).'%')
                                    ->orWhere('phone', 'like', '%'.substr($digits, 1).'%'));
                        }
                    }
                });
            }
        });
    }
}
