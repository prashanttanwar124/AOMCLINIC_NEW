<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'patient_id',
    'appointment_date',
    'appointment_number',
    'appointment_order',
    'slot',
    'appointment_type',
    'payment_type',
    'amount',
    'purpose_of_appointment',
    'chief_complaint',
    'present_complaint',
    'associated_complaint',
    'diagnosis',
    'treatment',
    'medication_instructions',
    'medicines',
    'medicine_status',
    'past_history',
    'family_history_father_side',
    'family_history_mother_side',
    'history_of_vaccination',
    'addiction',
    'diet',
    'occupation',
    'number_of_children',
    'medicine_taking',
    'appetite',
    'thirst',
    'sleep',
    'urine',
    'stool',
    'pysical_examination',
    'as_a_person',
    'nature_of_person',
    'anxiety',
    'fear',
    'nature',
    'dreams',
    'desire',
    'craving',
    'menses_regular',
    'menses_duration',
    'menses_flow_complaints',
    'menses_medicine_taking_menses',
    'follow_up_day',
    'follow_up',
    'follow_up_message_sent',
    'days_prescription',
    'status',
    'on_hold',
    'hold_order',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * Slot code stored for a Morning session.
     */
    public const SLOT_MORNING = 'M';

    /**
     * Slot code stored for an Evening session.
     */
    public const SLOT_EVENING = 'E';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_type' => 'Cash',
        'medicine_status' => false,
        'follow_up' => 0,
        'follow_up_message_sent' => false,
        'days_prescription' => 0,
        'status' => 'pending',
        'on_hold' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'follow_up_day' => 'date',
            'follow_up_message_sent' => 'boolean',
            'medicine_status' => 'boolean',
            'medicines' => 'array',
            'on_hold' => 'boolean',
        ];
    }

    /**
     * Map a session name (morning/evening) to its stored slot code.
     */
    public static function slotForSession(string $session): string
    {
        return Str::lower($session) === 'evening'
            ? self::SLOT_EVENING
            : self::SLOT_MORNING;
    }

    /**
     * Map a stored slot code back to a human session label.
     */
    public static function sessionLabelForSlot(?string $slot): string
    {
        return $slot === self::SLOT_EVENING ? 'Evening' : 'Morning';
    }

    /**
     * Get patient who owns appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the vitals record associated with the appointment.
     */
    public function vital(): HasOne
    {
        return $this->hasOne(AppointmentVital::class);
    }

    /**
     * Parse stored medicines to load medicine_id, category_id, size_id keys.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parsedMedicines(): array
    {
        return collect($this->medicines ?? [])->map(function ($med): array {
            if (is_array($med) && isset($med['id'])) {
                $id = explode('|', (string) $med['id']);
                $id = array_map('trim', $id);
                if (count($id) === 3) {
                    $med['medicine_id'] = $id[0];
                    $med['category_id'] = $id[1];
                    $med['size_id'] = $id[2];
                }
            }

            return $med;
        })->all();
    }
}
