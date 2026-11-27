<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'product_name',
    'description',
    'cost_price',
    'product_code',
    'price',
    'stock',
    'minimum_stock',
    'lead_time_days',
    'safety_stock',
    'unit',
    'image_path',
    'is_active',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'price' => 'decimal:2',
            'stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'lead_time_days' => 'integer',
            'safety_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function alternatives(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_alternatives',
            'product_id',
            'alternative_product_id'
        )->withPivot('priority')->orderBy('product_alternatives.priority');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('conversion_ratio');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function matchedOrderInputItems(): HasMany
    {
        return $this->hasMany(OrderInputItem::class, 'matched_product_id');
    }

    public function confirmedOrderInputItems(): HasMany
    {
        return $this->hasMany(OrderInputItem::class, 'confirmed_product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereColumn('stock', '<=', 'minimum_stock');
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->minimum_stock;
    }

    public function stockStatus(): string
    {
        $stock = (float) $this->stock;
        $minStock = (float) $this->minimum_stock;

        if ($stock <= 0) {
            return 'out_of_stock';
        }

        if ($stock <= ($minStock / 2)) {
            return 'critical';
        }

        if ($stock <= $minStock) {
            return 'low';
        }

        return 'sufficient';
    }

    public function baseProfit(): float
    {
        return round((float) $this->price - (float) $this->cost_price, 2);
    }

    public function profitMarginPercent(): float
    {
        $price = (float) $this->price;
        if ($price <= 0) {
            return 0.0;
        }

        return round(($this->baseProfit() / $price) * 100, 1);
    }
}
