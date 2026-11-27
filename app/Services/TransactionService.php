<?php

namespace App\Services;

use App\Enums\CartStatus;
use App\Enums\StockMovementType;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\DebtPayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Staff;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Complete a POS checkout atomically from the cashier's active cart.
     */
    public function checkout(
        User $cashier,
        Cart $cart,
        string $paymentMethod,
        ?float $amountPaid = null,
        float $discountAmount = 0,
        ?string $notes = null,
        ?int $customerId = null,
        string $paymentMode = 'full',
        bool $includePreviousDebt = false,
        ?int $takenByStaffId = null
    ): Transaction {
        return DB::transaction(function () use ($cashier, $cart, $paymentMethod, $amountPaid, $discountAmount, $notes, $customerId, $paymentMode, $includePreviousDebt, $takenByStaffId): Transaction {
            /** @var Cart $lockedCart */
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->where('user_id', $cashier->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCart->cart_status !== CartStatus::Active) {
                throw ValidationException::withMessages([
                    'cart' => 'Keranjang ini sudah diproses atau tidak aktif.',
                ]);
            }

            $items = $lockedCart->items()->with(['product', 'productUnit'])->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Keranjang belanja masih kosong.',
                ]);
            }

            if ($customerId !== null) {
                Customer::query()->whereKey($customerId)->firstOrFail();
            }

            $takenByStaff = null;
            if ($takenByStaffId !== null) {
                $takenByStaff = Staff::query()->whereKey($takenByStaffId)->where('is_active', true)->first();
                if (! $takenByStaff) {
                    throw ValidationException::withMessages([
                        'taken_by_staff_id' => 'Staf yang dipilih tidak ditemukan atau sudah tidak aktif.',
                    ]);
                }
            }

            $subtotal = 0.0;
            $preparedLines = [];

            foreach ($items as $cartItem) {
                /** @var Product|null $product */
                $product = Product::query()->whereKey($cartItem->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => 'Salah satu produk di keranjang sudah tidak aktif.',
                    ]);
                }

                $qty = (float) $cartItem->quantity;
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'cart' => "Jumlah untuk {$product->product_name} tidak valid.",
                    ]);
                }

                /** @var ProductUnit|null $productUnit */
                $productUnit = $cartItem->product_unit_id
                    ? ProductUnit::query()->whereKey($cartItem->product_unit_id)->where('product_id', $product->id)->first()
                    : null;

                $unitName = $productUnit ? $productUnit->unit_name : ($cartItem->unit_name ?: $product->unit);
                $conversionRatio = $productUnit ? (float) $productUnit->conversion_ratio : 1.0;
                $baseQuantity = round($qty * $conversionRatio, 3);

                if ((float) $product->stock < $baseQuantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Stok {$product->product_name} tidak mencukupi (dibutuhkan: {$baseQuantity} {$product->unit}, tersedia: {$product->stock} {$product->unit}).",
                    ]);
                }

                // Always use official price & cost from the database, unless price was overridden or preserved in a held/resumed cart
                $dbPrice = $productUnit ? (float) $productUnit->selling_price : (float) $product->price;
                $officialPrice = ($cartItem->is_price_overridden || $lockedCart->resumed_at !== null)
                    ? (float) $cartItem->price_snapshot
                    : $dbPrice;
                $unitCost = round((float) $product->cost_price * $conversionRatio, 2);
                $lineSubtotal = round($officialPrice * $qty, 2);
                $lineProfit = round(($officialPrice - $unitCost) * $qty, 2);
                $subtotal += $lineSubtotal;

                $preparedLines[] = [
                    'product' => $product,
                    'product_unit_id' => $productUnit?->id,
                    'unit_name' => $unitName,
                    'conversion_ratio' => $conversionRatio,
                    'base_quantity' => $baseQuantity,
                    'quantity' => $qty,
                    'price' => $officialPrice,
                    'cost_price' => $unitCost,
                    'subtotal' => $lineSubtotal,
                    'profit' => $lineProfit,
                ];
            }

            $discount = max(0.0, min($discountAmount, $subtotal));
            $totalAmount = round($subtotal - $discount, 2);

            $unpaidDebts = collect();
            $previousDebt = 0.0;
            if ($customerId !== null && $includePreviousDebt) {
                $unpaidDebts = Transaction::query()
                    ->where('customer_id', $customerId)
                    ->where('transaction_status', '!=', Transaction::STATUS_CANCELLED)
                    ->where('outstanding_balance', '>', 0)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $previousDebt = round((float) $unpaidDebts->sum('outstanding_balance'), 2);
            }

            $grandTotal = round($totalAmount + $previousDebt, 2);

            if ($amountPaid === null && $paymentMethod !== Transaction::PAYMENT_CASH) {
                $cashReceived = $grandTotal;
            } else {
                $cashReceived = max(0.0, round((float) ($amountPaid ?? 0), 2));
            }

            if ($cashReceived < $grandTotal && ! $customerId) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pembayaran kurang dari total belanja. Pilih atau daftarkan pelanggan untuk mencatat hutang.',
                ]);
            }

            // FIFO allocation: settle previous outstanding debts first, then current purchase
            $remainingCash = $cashReceived;
            $previousDebtPaid = 0.0;
            $allocations = [];

            foreach ($unpaidDebts as $oldDebt) {
                if ($remainingCash <= 0) {
                    break;
                }
                $oldBalance = round((float) $oldDebt->outstanding_balance, 2);
                $alloc = round(min($remainingCash, $oldBalance), 2);
                if ($alloc > 0) {
                    $allocations[] = ['trx' => $oldDebt, 'amount' => $alloc];
                    $previousDebtPaid = round($previousDebtPaid + $alloc, 2);
                    $remainingCash = round($remainingCash - $alloc, 2);
                }
            }

            $currentPaid = round(min($remainingCash, $totalAmount), 2);
            $currentOutstanding = round(max(0.0, $totalAmount - $currentPaid), 2);
            $change = round(max(0.0, $cashReceived - $grandTotal), 2);

            $paymentStatus = $currentOutstanding <= 0
                ? Transaction::PAYMENT_STATUS_PAID
                : ($currentPaid > 0 ? Transaction::PAYMENT_STATUS_PARTIAL : Transaction::PAYMENT_STATUS_UNPAID);

            $transaction = Transaction::create([
                'transaction_code' => $this->generateUniqueCode(),
                'user_id' => $cashier->id,
                'customer_id' => $customerId,
                'taken_by_staff_id' => $takenByStaff?->id,
                'taken_by_staff_name_snapshot' => $takenByStaff?->name,
                'taken_by_staff_code_snapshot' => $takenByStaff?->staff_code,
                'cart_id' => $lockedCart->id,
                'subtotal' => round($subtotal, 2),
                'discount_amount' => round($discount, 2),
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'previous_debt_amount' => $previousDebt,
                'previous_debt_paid' => $previousDebtPaid,
                'grand_total' => $grandTotal,
                'cash_received' => $cashReceived,
                'amount_paid' => $currentPaid,
                'change_amount' => $change,
                'outstanding_balance' => $currentOutstanding,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'transaction_status' => Transaction::STATUS_COMPLETED,
                'transaction_date' => now(),
                'notes' => $notes,
            ]);

            foreach ($allocations as $allocRow) {
                /** @var Transaction $oldTrx */
                $oldTrx = $allocRow['trx'];
                $allocAmt = $allocRow['amount'];
                $newOldBal = round(max(0.0, (float) $oldTrx->outstanding_balance - $allocAmt), 2);
                $newOldPaid = round((float) $oldTrx->amount_paid + $allocAmt, 2);

                $oldTrx->update([
                    'amount_paid' => $newOldPaid,
                    'outstanding_balance' => $newOldBal,
                    'payment_status' => $newOldBal <= 0
                        ? Transaction::PAYMENT_STATUS_PAID
                        : Transaction::PAYMENT_STATUS_PARTIAL,
                ]);

                DebtPayment::create([
                    'transaction_id' => $oldTrx->id,
                    'settled_by_transaction_id' => $transaction->id,
                    'customer_id' => $customerId,
                    'user_id' => $cashier->id,
                    'amount' => $allocAmt,
                    'payment_method' => $paymentMethod,
                    'payment_date' => now(),
                    'note' => "Dialokasikan dari transaksi {$transaction->transaction_code}",
                ]);
            }

            foreach ($preparedLines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'product_unit_id' => $line['product_unit_id'],
                    'product_name_snapshot' => $product->product_name,
                    'product_code_snapshot' => $product->product_code,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit_name'],
                    'conversion_ratio' => $line['conversion_ratio'],
                    'base_quantity' => $line['base_quantity'],
                    'price' => $line['price'],
                    'cost_price_snapshot' => $line['cost_price'],
                    'subtotal' => $line['subtotal'],
                    'profit_snapshot' => $line['profit'],
                ]);

                $this->inventoryService->adjustStock(
                    $product,
                    $line['base_quantity'],
                    StockMovementType::Sale,
                    $cashier,
                    "Penjualan {$transaction->transaction_code} ({$line['quantity']} {$line['unit_name']})",
                    $transaction
                );
            }

            $lockedCart->update([
                'cart_status' => CartStatus::Completed,
                'subtotal' => round($subtotal, 2),
                'total' => $totalAmount,
            ]);

            return $transaction->load(['user', 'customer', 'details']);
        });
    }

    /**
     * Cancel a completed transaction safely and restore stock once.
     */
    public function cancel(Transaction $transaction, User $admin, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $admin, $reason): Transaction {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->with('details.product')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->transaction_status === Transaction::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi ini sudah dibatalkan sebelumnya.',
                ]);
            }

            if ($locked->transaction_status === Transaction::STATUS_COMPLETED) {
                foreach ($locked->details as $detail) {
                    if ($detail->product) {
                        $restoreQty = (float) ($detail->base_quantity > 0 ? $detail->base_quantity : $detail->quantity);
                        $this->inventoryService->adjustStock(
                            $detail->product,
                            $restoreQty,
                            StockMovementType::CancellationReversal,
                            $admin,
                            "Pembatalan {$locked->transaction_code}: {$reason}",
                            $locked
                        );
                    }
                }
            }

            $existingNotes = $locked->notes ? ($locked->notes . "\n") : '';
            $locked->update([
                'transaction_status' => Transaction::STATUS_CANCELLED,
                'outstanding_balance' => 0,
                'notes' => $existingNotes . "[Cancelled by {$admin->name}]: {$reason}",
            ]);

            return $locked->fresh(['user', 'customer', 'details']);
        });
    }

    private function generateUniqueCode(): string
    {
        $prefix = 'TRX-' . now()->format('Ymd') . '-';

        do {
            $code = $prefix . strtoupper(Str::random(4));
        } while (Transaction::query()->where('transaction_code', $code)->exists());

        return $code;
    }
}
