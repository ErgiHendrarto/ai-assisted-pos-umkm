<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'week_start',
    'week_end',
    'source',
    'summary_text',
    'observed_metrics',
    'recommendations',
    'generated_by',
])]
class WeeklyInsight extends Model
{
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'observed_metrics' => 'array',
            'recommendations' => 'array',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
