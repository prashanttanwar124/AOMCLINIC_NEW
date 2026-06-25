<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'patient_id',
    'certificate_type_id',
    'certificate_number',
    'issue_date',
    'start_date',
    'end_date',
    'diagnosis',
    'charge_amount',
    'payment_status',
    'notes',
    'status',
])]
class MedicalCertificate extends Model
{
    /** @use HasFactory<\Database\Factories\MedicalCertificateFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'charge_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the patient associated with the certificate.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the type of the certificate.
     */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->certificate_number)) {
                $latest = static::query()->orderByDesc('id')->first();
                $nextNumber = $latest ? ((int) str_replace('MC-', '', $latest->certificate_number)) + 1 : 1;
                $model->certificate_number = 'MC-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
