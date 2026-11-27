<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'product_id',
    'product_unit_id',
    'product_name_snapshot',
    'product_code_snapshot',
    'quantity',
    'unit',
    'conversion_ratio',
    'base_quantity',
    'price',
    'cost_price_snapshot',
    'subtotal',
    'profit_snapshot',
])]
class TransactionDetail extends Model
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
            'quantity' => 'decimal:3',
            'conversion_ratio' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'price' => 'decimal:2',
            'cost_price_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'profit_snapshot' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
