<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'staff_code',
    'name',
    'phone',
    'is_active',
    'notes',
])]
class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected static function booted(): void
    {
        static::creating(function (Staff $staff): void {
            if (empty($staff->staff_code)) {
                $staff->staff_code = self::generateNextCode();
            }
        });
    }

    public static function generateNextCode(): string
    {
        $maxNum = 0;
        $codes = self::query()->pluck('staff_code');
        foreach ($codes as $code) {
            if (preg_match('/STF-(\d+)/i', (string) $code, $m)) {
                $maxNum = max($maxNum, (int) $m[1]);
            }
        }

        $next = $maxNum + 1;
        $candidate = 'STF-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        while (self::query()->where('staff_code', $candidate)->exists()) {
            $next++;
            $candidate = 'STF-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'taken_by_staff_id');
    }
}
