<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'settled_by_transaction_id',
    'customer_id',
    'user_id',
    'amount',
    'payment_method',
    'entry_type',
    'reason',
    'payment_date',
    'note',
])]
class DebtPayment extends Model
{
    public const TYPE_REPAYMENT = 'repayment';
    public const TYPE_EXTERNAL_SETTLEMENT = 'external_settlement';
    public const TYPE_WRITE_OFF = 'write_off';

    public const REASON_LABELS = [
        'already_paid_outside' => 'Already paid outside the system',
        'incorrect_record' => 'Incorrect debt record',
        'write_off' => 'Debt write-off',
        'other' => 'Other',
    ];

    public function entryTypeLabel(): string
    {
        return match ($this->entry_type) {
            self::TYPE_EXTERNAL_SETTLEMENT => 'External Settlement',
            self::TYPE_WRITE_OFF => 'Write-Off / Adjustment',
            default => $this->settled_by_transaction_id ? 'POS Carry-Forward' : 'Cash Repayment',
        };
    }

    public function reasonLabel(): ?string
    {
        if (! $this->reason) {
            return null;
        }

        return self::REASON_LABELS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function settledByTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'settled_by_transaction_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
