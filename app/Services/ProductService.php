<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function create(array $data, ?UploadedFile $image = null, ?User $user = null): Product
    {
        return DB::transaction(function () use ($data, $image, $user): Product {
            $imagePath = $image ? $image->store('products', 'public') : null;
            $initialStock = (float) ($data['stock'] ?? 0);
            $productCode = filled($data['product_code'] ?? null)
                ? strtoupper(trim((string) $data['product_code']))
                : $this->generateNextSku();

            $baseUnit = strtolower(trim($data['unit']));
            Unit::firstOrCreate(['name' => $baseUnit], ['is_active' => true]);

            $product = Product::create([
                'category_id' => $data['category_id'],
                'product_code' => $productCode,
                'product_name' => trim($data['product_name']),
                'description' => $data['description'] ?? null,
                'cost_price' => (float) ($data['cost_price'] ?? 0),
                'price' => $data['price'],
                'stock' => $initialStock,
                'minimum_stock' => $data['minimum_stock'] ?? 5,
                'lead_time_days' => (int) ($data['lead_time_days'] ?? 2),
                'safety_stock' => (float) ($data['safety_stock'] ?? 5),
                'unit' => $baseUnit,
                'image_path' => $imagePath,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if (! empty($data['product_units']) && is_array($data['product_units'])) {
                $this->syncUnits($product, $data['product_units']);
            }

            if (! empty($data['alternative_product_ids']) && is_array($data['alternative_product_ids'])) {
                $this->syncAlternatives($product, $data['alternative_product_ids']);
            }

            if ($initialStock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user?->id,
                    'movement_type' => StockMovementType::StockIn,
                    'quantity' => $initialStock,
                    'stock_before' => 0,
                    'stock_after' => $initialStock,
                    'note' => 'Stok awal produk baru',
                ]);
            }

            return $product;
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        $imagePath = $product->image_path;

        if ($removeImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if ($image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $image->store('products', 'public');
        }

        $baseUnit = strtolower(trim($data['unit']));
        Unit::firstOrCreate(['name' => $baseUnit], ['is_active' => true]);

        $product->update([
            'category_id' => $data['category_id'],
            'product_code' => strtoupper(trim($data['product_code'])),
            'product_name' => trim($data['product_name']),
            'description' => $data['description'] ?? null,
            'cost_price' => (float) ($data['cost_price'] ?? $product->cost_price ?? 0),
            'price' => $data['price'],
            'minimum_stock' => $data['minimum_stock'] ?? $product->minimum_stock,
            'lead_time_days' => (int) ($data['lead_time_days'] ?? $product->lead_time_days ?? 2),
            'safety_stock' => (float) ($data['safety_stock'] ?? $product->safety_stock ?? 5),
            'unit' => $baseUnit,
            'image_path' => $imagePath,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (array_key_exists('product_units', $data)) {
            $this->syncUnits($product, is_array($data['product_units']) ? $data['product_units'] : []);
        }

        if (array_key_exists('alternative_product_ids', $data)) {
            $this->syncAlternatives($product, is_array($data['alternative_product_ids']) ? $data['alternative_product_ids'] : []);
        }

        return $product->refresh();
    }

    /**
     * @param  array<int, int|string>  $alternativeIds
     */
    public function syncAlternatives(Product $product, array $alternativeIds): void
    {
        $syncPayload = [];
        $priority = 1;
        foreach ($alternativeIds as $altId) {
            $id = (int) $altId;
            if ($id > 0 && $id !== $product->id && ! isset($syncPayload[$id])) {
                $syncPayload[$id] = ['priority' => $priority++];
            }
        }
        $product->alternatives()->sync($syncPayload);
    }

    /**
     * Sync additional selling units and conversion ratios for a product.
     *
     * @param  array<int, array<string, mixed>>  $unitsData
     */
    public function syncUnits(Product $product, array $unitsData): void
    {
        $seenNames = [];
        $keptIds = [];

        foreach ($unitsData as $row) {
            $unitName = strtolower(trim((string) ($row['unit_name'] ?? '')));
            $ratio = (float) ($row['conversion_ratio'] ?? 0);
            $price = (float) ($row['selling_price'] ?? 0);

            if ($unitName === '' || $ratio <= 0 || $unitName === strtolower((string) $product->unit)) {
                continue;
            }

            if (in_array($unitName, $seenNames, true)) {
                continue;
            }
            $seenNames[] = $unitName;

            Unit::firstOrCreate(['name' => $unitName], ['is_active' => true]);

            $unitRecord = ProductUnit::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'unit_name' => $unitName,
                ],
                [
                    'conversion_ratio' => round($ratio, 3),
                    'selling_price' => round(max(0, $price), 2),
                    'is_default' => false,
                ]
            );

            $keptIds[] = $unitRecord->id;
        }

        $product->units()->whereNotIn('id', $keptIds)->delete();
    }

    public function delete(Product $product): void
    {
        if ($product->cartItems()->exists()) {
            throw ValidationException::withMessages([
                'product' => 'Produk sedang berada di keranjang kasir aktif. Nonaktifkan produk terlebih dahulu.',
            ]);
        }

        $product->update(['is_active' => false]);
        $product->delete();
    }

    public function generateNextSku(): string
    {
        $codes = Product::withTrashed()
            ->where('product_code', 'like', 'PRD-%')
            ->pluck('product_code');

        $maxNumber = 0;
        foreach ($codes as $code) {
            if (preg_match('/^PRD-(\d+)$/', (string) $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $next = $maxNumber + 1;
        do {
            $candidate = sprintf('PRD-%03d', $next);
            $next++;
        } while (Product::withTrashed()->where('product_code', $candidate)->exists());

        return $candidate;
    }
}
