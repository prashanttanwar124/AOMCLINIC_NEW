<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
])]
class Medicine extends Model
{
    use HasFactory;

    /**
     * Get the stock variations associated with this medicine.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(MedicineStock::class);
    }
}
