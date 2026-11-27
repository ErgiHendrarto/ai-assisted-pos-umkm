<?php

namespace App\Models;

use App\Support\Format;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'transaction_code',
    'user_id',
    'customer_id',
    'taken_by_staff_id',
    'taken_by_staff_name_snapshot',
    'taken_by_staff_code_snapshot',
    'cart_id',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'previous_debt_amount',
    'previous_debt_paid',
    'grand_total',
    'cash_received',
    'amount_paid',
    'change_amount',
    'outstanding_balance',
    'payment_method',
    'payment_status',
    'transaction_status',
    'transaction_date',
    'notes',
])]
class Transaction extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_QRIS = 'qris';

    public const PAYMENT_OTHER = 'other';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partially_paid';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'previous_debt_amount' => 'decimal:2',
            'previous_debt_paid' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function takenByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'taken_by_staff_id');
    }

    public function takenByLabel(): string
    {
        if ($this->taken_by_staff_name_snapshot) {
            return $this->taken_by_staff_code_snapshot
                ? "{$this->taken_by_staff_name_snapshot} — {$this->taken_by_staff_code_snapshot}"
                : $this->taken_by_staff_name_snapshot;
        }

        if ($this->takenByStaff) {
            return "{$this->takenByStaff->name} — {$this->takenByStaff->staff_code}";
        }

        return 'Not Assigned';
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class)->latest('payment_date')->latest('id');
    }

    public function paymentStatusLabel(): string
    {
        return match (strtolower((string) $this->payment_status)) {
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_PARTIAL => 'Partially Paid',
            self::PAYMENT_STATUS_UNPAID => 'Unpaid / Debt',
            default => ucfirst((string) $this->payment_status),
        };
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('transaction_status', self::STATUS_COMPLETED);
    }

    public function formattedTotal(): string
    {
        return Format::rupiah($this->total_amount);
    }

    public function paymentMethodLabel(): string
    {
        return match (strtolower((string) $this->payment_method)) {
            'cash' => 'Cash',
            'qris' => 'QRIS',
            'other' => 'Other',
            'transfer' => 'Bank Transfer',
            'debit' => 'Debit Card',
            default => ucfirst((string) $this->payment_method),
        };
    }

    public function statusLabel(): string
    {
        return match (strtolower((string) $this->transaction_status)) {
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->transaction_status),
        };
    }
}
