<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id',
    'temperature',
    'weight',
    'blood_pressure',
    'pulse_rate',
    'spo2',
    'notes',
])]
class AppointmentVital extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentVitalFactory> */
    use HasFactory;

    /**
     * Get the appointment that owns the vitals.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
