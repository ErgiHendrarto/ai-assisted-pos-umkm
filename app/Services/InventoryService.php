<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Adjust product stock atomically with row-level locking and audit logging.
     */
    public function adjustStock(
        Product $product,
        float $quantity,
        StockMovementType $movementType,
        ?User $user = null,
        ?string $note = null,
        ?Transaction $transaction = null
    ): StockMovement {
        if ($quantity <= 0 && $movementType !== StockMovementType::Adjustment) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah perubahan stok harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $movementType, $user, $note, $transaction): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $stockBefore = (float) $locked->stock;
            $delta = match ($movementType) {
                StockMovementType::StockIn, StockMovementType::CancellationReversal => abs($quantity),
                StockMovementType::StockOut, StockMovementType::Sale => -abs($quantity),
                StockMovementType::Adjustment => $quantity,
            };

            $stockAfter = round($stockBefore + $delta, 3);

            if ($stockAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$locked->product_name} tidak mencukupi (tersedia: {$stockBefore} {$locked->unit}).",
                ]);
            }

            $locked->stock = $stockAfter;
            $locked->save();

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $user?->id,
                'transaction_id' => $transaction?->id,
                'movement_type' => $movementType,
                'quantity' => abs($delta),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $note,
            ]);
        });
    }

    /**
     * Set exact physical stock via adjustment movement.
     */
    public function setExactStock(Product $product, float $actualStock, User $user, ?string $note = null): ?StockMovement
    {
        if ($actualStock < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stok aktual tidak boleh bernilai negatif.',
            ]);
        }

        $diff = round($actualStock - (float) $product->stock, 3);
        if (abs($diff) < 0.0001) {
            return null;
        }

        return $this->adjustStock(
            $product,
            $diff,
            StockMovementType::Adjustment,
            $user,
            $note ?: 'Penyesuaian stok fisik (stock opname)'
        );
    }
}
