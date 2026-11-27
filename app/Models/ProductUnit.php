<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'unit_name',
    'conversion_ratio',
    'selling_price',
    'is_default',
])]
class ProductUnit extends Model
{
    protected function casts(): array
    {
        return [
            'conversion_ratio' => 'decimal:3',
            'selling_price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitCost(): float
    {
        $baseCost = (float) ($this->product?->cost_price ?? 0);

        return round($baseCost * (float) $this->conversion_ratio, 2);
    }

    public function unitProfit(): float
    {
        return round((float) $this->selling_price - $this->unitCost(), 2);
    }

    public function profitMarginPercent(): float
    {
        $price = (float) $this->selling_price;
        if ($price <= 0) {
            return 0.0;
        }

        return round(($this->unitProfit() / $price) * 100, 1);
    }
}
