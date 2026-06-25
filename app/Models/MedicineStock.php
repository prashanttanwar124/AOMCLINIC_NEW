<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'medicine_id',
    'category_id',
    'size_id',
    'quantity',
    'quantity_reduction',
])]
class MedicineStock extends Model
{
    use HasFactory;

    /**
     * Get the medicine associated with this stock.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the category associated with this stock.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the size associated with this stock.
     */
    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }
}
