<?php

namespace App\Models;

use App\Enums\AiProcessingStatus;
use App\Enums\AiProcessingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('ai_processing_runs')]
#[Fillable([
    'order_input_id',
    'processing_type',
    'service_name',
    'model_name',
    'prompt_version',
    'recognized_text',
    'structured_output',
    'processing_status',
    'error_message',
    'processing_duration_ms',
])]
class AIProcessingRun extends Model
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
            'processing_type' => AiProcessingType::class,
            'processing_status' => AiProcessingStatus::class,
            'structured_output' => 'array',
            'processing_duration_ms' => 'integer',
        ];
    }

    public function orderInput(): BelongsTo
    {
        return $this->belongsTo(OrderInput::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderInputItem::class, 'ai_processing_run_id');
    }
}
