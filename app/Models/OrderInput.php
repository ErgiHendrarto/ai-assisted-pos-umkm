<?php

namespace App\Models;

use App\Enums\OrderInputStatus;
use App\Enums\OrderInputType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'cart_id',
    'input_type',
    'original_text',
    'file_path',
    'processing_status',
    'metadata',
])]
class OrderInput extends Model
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
            'input_type' => OrderInputType::class,
            'processing_status' => OrderInputStatus::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function aiProcessingRuns(): HasMany
    {
        return $this->hasMany(AIProcessingRun::class);
    }
}
