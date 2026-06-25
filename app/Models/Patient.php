<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
}

