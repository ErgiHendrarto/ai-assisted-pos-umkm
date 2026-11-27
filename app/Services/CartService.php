<?php

namespace App\Services;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateActiveCart(User $user): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('cart_status', CartStatus::Active)
            ->latest('id')
            ->first();

        if (! $cart) {
            $cart = Cart::create([
                'user_id' => $user->id,
                'cart_status' => CartStatus::Active,
                'subtotal' => 0,
                'total' => 0,
            ]);
        }

        return $cart->load(['items.product.category', 'items.product.units', 'items.productUnit']);
    }

    public function addItem(User $user, Product $product, float $quantity = 1, ?ProductUnit $productUnit = null): Cart
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah produk harus lebih dari 0.',
            ]);
        }

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => "Produk {$product->product_name} sedang tidak aktif.",
            ]);
        }

        if ($productUnit && $productUnit->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'product_unit_id' => 'Satuan jual tidak sesuai dengan produk yang dipilih.',
            ]);
        }

        return DB::transaction(function () use ($user, $product, $quantity, $productUnit): Cart {
            $cart = $this->getOrCreateActiveCart($user);

            $unitName = $productUnit ? $productUnit->unit_name : $product->unit;
            $conversionRatio = $productUnit ? (float) $productUnit->conversion_ratio : 1.0;
            $officialPrice = $productUnit ? (float) $productUnit->selling_price : (float) $product->price;

            /** @var CartItem|null $existing */
            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('unit_name', $unitName)
                ->first();

            $newQty = round(($existing ? (float) $existing->quantity : 0.0) + $quantity, 3);
            $requiredBaseQty = round($newQty * $conversionRatio, 3);
            $availableStock = (float) $product->stock;

            if ($availableStock <= 0 || $requiredBaseQty > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$product->product_name} tidak mencukupi (dibutuhkan {$requiredBaseQty} {$product->unit}, tersedia {$availableStock} {$product->unit}).",
                ]);
            }

            $lineSubtotal = round(($existing && $existing->is_price_overridden ? (float) $existing->price_snapshot : $officialPrice) * $newQty, 2);

            if ($existing) {
                $existing->update([
                    'product_name_snapshot' => $product->product_name,
                    'product_code_snapshot' => $product->product_code,
                    'product_unit_id' => $productUnit?->id,
                    'conversion_ratio' => $conversionRatio,
                    'quantity' => $newQty,
                    'original_price' => $existing->original_price ?? $officialPrice,
                    'price_snapshot' => $existing->is_price_overridden ? (float) $existing->price_snapshot : $officialPrice,
                    'subtotal' => $lineSubtotal,
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->product_name,
                    'product_code_snapshot' => $product->product_code,
                    'product_unit_id' => $productUnit?->id,
                    'unit_name' => $unitName,
                    'conversion_ratio' => $conversionRatio,
                    'quantity' => $newQty,
                    'original_price' => $officialPrice,
                    'price_snapshot' => $officialPrice,
                    'is_price_overridden' => false,
                    'subtotal' => $lineSubtotal,
                ]);
            }

            return $this->recalculateCart($cart);
        });
    }

    public function updateItemQuantity(User $user, CartItem $item, float $quantity): Cart
    {
        $cart = $this->ensureOwnsActiveCart($user, $item->cart);

        if ($quantity <= 0) {
            $item->delete();

            return $this->recalculateCart($cart);
        }

        $product = $item->product;
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => 'Produk sudah tidak tersedia.',
            ]);
        }

        $unit = $item->productUnit;
        $conversionRatio = $unit ? (float) $unit->conversion_ratio : (float) ($item->conversion_ratio ?: 1);
        $officialPrice = $unit ? (float) $unit->selling_price : (float) $product->price;
        $effectivePrice = ($item->is_price_overridden || $cart->resumed_at !== null)
            ? (float) $item->price_snapshot
            : $officialPrice;

        $requiredBaseQty = round($quantity * $conversionRatio, 3);
        $availableStock = (float) $product->stock;

        if ($requiredBaseQty > $availableStock) {
            throw ValidationException::withMessages([
                'quantity' => "Stok {$product->product_name} tidak mencukupi (dibutuhkan {$requiredBaseQty} {$product->unit}, tersedia {$availableStock} {$product->unit}).",
            ]);
        }

        $item->update([
            'quantity' => round($quantity, 3),
            'conversion_ratio' => $conversionRatio,
            'original_price' => $item->original_price ?? $officialPrice,
            'price_snapshot' => $effectivePrice,
            'subtotal' => round($effectivePrice * $quantity, 2),
        ]);

        return $this->recalculateCart($cart);
    }

    public function updateItemPrice(User $user, CartItem $item, float $newPrice, ?string $reason = null): Cart
    {
        if ($newPrice < 0) {
            throw ValidationException::withMessages([
                'price' => 'Harga transaksi tidak boleh negatif.',
            ]);
        }

        $cart = $this->ensureOwnsActiveCart($user, $item->cart);
        $product = $item->product;
        $unit = $item->productUnit;
        $officialPrice = $unit
            ? (float) $unit->selling_price
            : ($product ? (float) $product->price : (float) $item->price_snapshot);

        $roundedPrice = round($newPrice, 2);

        $item->update([
            'original_price' => $item->original_price ?? $officialPrice,
            'price_snapshot' => $roundedPrice,
            'is_price_overridden' => true,
            'price_override_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : $item->price_override_reason,
            'subtotal' => round($roundedPrice * (float) $item->quantity, 2),
        ]);

        return $this->recalculateCart($cart);
    }

    /**
     * Place the user's current active cart on hold as a persistent pending transaction.
     *
     * @param array<string, mixed> $draft
     */
    public function holdActiveCart(User $user, array $draft = []): Cart
    {
        return DB::transaction(function () use ($user, $draft): Cart {
            /** @var Cart|null $cart */
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->where('cart_status', CartStatus::Active)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Keranjang belanja masih kosong. Tambahkan produk sebelum menahan transaksi.',
                ]);
            }

            $customerId = ! empty($draft['customer_id']) ? (int) $draft['customer_id'] : null;
            $customer = $customerId ? Customer::query()->find($customerId) : null;
            $previousDebt = $customer ? round($customer->totalOutstandingDebt(), 2) : 0.0;

            foreach ($cart->items()->with(['product', 'productUnit'])->get() as $item) {
                $officialPrice = $item->productUnit
                    ? (float) $item->productUnit->selling_price
                    : ($item->product ? (float) $item->product->price : (float) $item->price_snapshot);

                $item->update([
                    'product_name_snapshot' => $item->product_name_snapshot ?: $item->product?->product_name,
                    'product_code_snapshot' => $item->product_code_snapshot ?: $item->product?->product_code,
                    'original_price' => $item->original_price ?? $officialPrice,
                ]);
            }

            $holdRef = $cart->hold_reference ?: $this->generateHoldReference();
            $subtotal = (float) $cart->items()->sum('subtotal');
            $discount = max(0.0, min((float) ($draft['discount_amount'] ?? 0), $subtotal));

            $cart->update([
                'cart_status' => CartStatus::Pending,
                'hold_reference' => $holdRef,
                'customer_id' => $customer?->id,
                'customer_name_snapshot' => $customer?->name,
                'subtotal' => round($subtotal, 2),
                'discount_amount' => round($discount, 2),
                'total' => round($subtotal, 2),
                'payment_method' => $draft['payment_method'] ?? Transaction::PAYMENT_CASH,
                'amount_paid' => isset($draft['amount_paid']) && $draft['amount_paid'] !== '' && $draft['amount_paid'] !== null
                    ? round((float) $draft['amount_paid'], 2)
                    : null,
                'include_previous_debt' => (bool) ($draft['include_previous_debt'] ?? false),
                'previous_debt_snapshot' => $previousDebt,
                'notes' => $draft['notes'] ?? null,
                'held_at' => now(),
            ]);

            return $cart->fresh(['items.product', 'customer', 'user']);
        });
    }

    /**
     * Resume a pending cart into the user's active POS cart.
     *
     * @param array<string, mixed> $currentDraft
     * @return array{conflict: bool, cart?: Cart, warnings?: array<int, string>, active_items_count?: int, active_total?: float}
     */
    public function resumePendingCart(
        User $user,
        Cart $pendingCart,
        string $conflictAction = 'abort',
        array $currentDraft = []
    ): array {
        return DB::transaction(function () use ($user, $pendingCart, $conflictAction, $currentDraft): array {
            /** @var Cart $lockedPending */
            $lockedPending = Cart::query()
                ->whereKey($pendingCart->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPending->user_id !== $user->id && ! $user->isAdministrator()) {
                abort(403, 'Unauthorized pending transaction access.');
            }

            if ($lockedPending->cart_status !== CartStatus::Pending) {
                throw ValidationException::withMessages([
                    'pending' => 'Transaksi pending ini sudah dilanjutkan atau dibatalkan.',
                ]);
            }

            /** @var Cart|null $activeCart */
            $activeCart = Cart::query()
                ->where('user_id', $user->id)
                ->where('cart_status', CartStatus::Active)
                ->whereKeyNot($lockedPending->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($activeCart) {
                $activeItemsCount = $activeCart->items()->count();
                if ($activeItemsCount > 0) {
                    if ($conflictAction !== 'hold_current') {
                        return [
                            'conflict' => true,
                            'active_items_count' => $activeItemsCount,
                            'active_total' => (float) $activeCart->total,
                        ];
                    }

                    $this->holdActiveCart($user, $currentDraft);
                } else {
                    $activeCart->delete();
                }
            }

            $lockedPending->update([
                'cart_status' => CartStatus::Active,
                'user_id' => $user->id,
                'resumed_at' => now(),
            ]);

            $warnings = [];
            $items = $lockedPending->items()->with(['product', 'productUnit'])->get();

            foreach ($items as $item) {
                $name = $item->product_name_snapshot ?: ($item->product?->product_name ?? 'Produk');
                $product = $item->product;

                if (! $product || ! $product->is_active) {
                    $warnings[] = "Produk {$name} sudah tidak aktif atau tidak tersedia.";
                    continue;
                }

                $requiredBaseQty = $item->baseQuantity();
                $availableStock = (float) $product->stock;

                if ($requiredBaseQty > $availableStock) {
                    $warnings[] = "Stok {$name} tidak mencukupi (di keranjang: {$requiredBaseQty} {$product->unit}, tersedia: {$availableStock} {$product->unit}).";
                }
            }

            return [
                'conflict' => false,
                'cart' => $lockedPending->fresh(['items.product.category', 'items.product.units', 'items.productUnit', 'customer']),
                'warnings' => $warnings,
            ];
        });
    }

    public function cancelPendingCart(User $user, Cart $pendingCart): Cart
    {
        return DB::transaction(function () use ($user, $pendingCart): Cart {
            /** @var Cart $lockedPending */
            $lockedPending = Cart::query()
                ->whereKey($pendingCart->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPending->user_id !== $user->id && ! $user->isAdministrator()) {
                abort(403, 'Unauthorized pending transaction access.');
            }

            if ($lockedPending->cart_status !== CartStatus::Pending) {
                throw ValidationException::withMessages([
                    'pending' => 'Transaksi pending ini sudah tidak aktif.',
                ]);
            }

            $lockedPending->update([
                'cart_status' => CartStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $lockedPending;
        });
    }

    private function generateHoldReference(): string
    {
        $maxId = (int) Cart::query()->max('id') + 1;
        $candidate = 'HOLD-' . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);

        while (Cart::query()->where('hold_reference', $candidate)->exists()) {
            $maxId++;
            $candidate = 'HOLD-' . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $cart = $this->ensureOwnsActiveCart($user, $item->cart);
        $item->delete();

        return $this->recalculateCart($cart);
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->getOrCreateActiveCart($user);
        $cart->items()->delete();

        return $this->recalculateCart($cart);
    }

    public function recalculateCart(Cart $cart): Cart
    {
        $subtotal = (float) $cart->items()->sum('subtotal');

        $cart->update([
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal, 2),
        ]);

        return $cart->fresh(['items.product.category', 'items.product.units', 'items.productUnit']);
    }

    private function ensureOwnsActiveCart(User $user, Cart $cart): Cart
    {
        if ($cart->user_id !== $user->id || $cart->cart_status !== CartStatus::Active) {
            abort(403, 'Unauthorized cart access.');
        }

        return $cart;
    }
}
