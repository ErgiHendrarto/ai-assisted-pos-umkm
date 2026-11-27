<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_code', 'name', 'phone', 'address', 'notes', 'is_active'])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (blank($customer->customer_code)) {
                $customer->customer_code = static::generateNextCode();
            }
        });
    }

    public static function generateNextCode(): string
    {
        $codes = static::withTrashed()
            ->where('customer_code', 'like', 'CUS-%')
            ->pluck('customer_code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^CUS-(\d+)$/', (string) $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $next = $max + 1;
        do {
            $candidate = sprintf('CUS-%04d', $next);
            $next++;
        } while (static::withTrashed()->where('customer_code', $candidate)->exists());

        return $candidate;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function totalOutstandingDebt(): float
    {
        return (float) $this->transactions()
            ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');
    }
}
