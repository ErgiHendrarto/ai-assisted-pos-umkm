<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_processing_run_id',
    'requested_product_name',
    'requested_quantity',
    'requested_unit',
    'matched_product_id',
    'match_confidence',
    'match_status',
    'confirmed_product_id',
    'confirmed_quantity',
    'review_status',
])]
class OrderInputItem extends Model
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
            'requested_quantity' => 'decimal:3',
            'match_confidence' => 'decimal:4',
            'match_status' => MatchStatus::class,
            'confirmed_quantity' => 'decimal:3',
            'review_status' => ReviewStatus::class,
        ];
    }

    public function aiProcessingRun(): BelongsTo
    {
        return $this->belongsTo(AIProcessingRun::class, 'ai_processing_run_id');
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function confirmedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'confirmed_product_id');
    }
}
