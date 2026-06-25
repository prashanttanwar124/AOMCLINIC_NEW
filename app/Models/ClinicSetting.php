<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'clinic_name',
    'doctor_name',
    'doctor_qualifications',
    'doctor_title',
    'doctor_registration_no',
    'clinic_registration_no',
    'address',
    'phone',
    'email',
    'logo_path',
])]
class ClinicSetting extends Model
{
    /**
     * Get the current settings record.
     */
    public static function current(): self
    {
        return static::query()->latest('id')->first() ?? new self;
    }
}
