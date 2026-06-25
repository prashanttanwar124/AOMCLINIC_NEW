<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'patient_id',
    'parcel_status',
    'parcel_date',
    'amount',
    'payment_status',
    'medicines',
    'address',
    'notes',
    'delivered_date',
    'instructions_given',
    'instruction_note',
])]
class CourierParcel extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parcel_date' => 'date',
            'delivered_date' => 'date',
            'instructions_given' => 'boolean',
            'medicines' => 'array',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the patient associated with this parcel.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
