<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id',
    'product_id',
    'product_name_snapshot',
    'product_code_snapshot',
    'product_unit_id',
    'unit_name',
    'conversion_ratio',
    'quantity',
    'original_price',
    'price_snapshot',
    'is_price_overridden',
    'price_override_reason',
    'subtotal',
])]
class CartItem extends Model
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
            'conversion_ratio' => 'decimal:3',
            'quantity' => 'decimal:3',
            'original_price' => 'decimal:2',
            'price_snapshot' => 'decimal:2',
            'is_price_overridden' => 'boolean',
            'subtotal' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function baseQuantity(): float
    {
        return round((float) $this->quantity * max(0.001, (float) ($this->conversion_ratio ?: 1)), 3);
    }
}
