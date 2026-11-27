<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCustomerDebtSeeder extends Seeder
{
    /**
     * Non-destructively seed 5 demo customers with unpaid and partially paid debt transactions.
     */
    public function run(): void
    {
        $cashier = User::query()->where('role', User::ROLE_CASHIER)->first()
            ?? User::query()->first();
        $product = Product::query()->active()->first();

        if (! $cashier || ! $product) {
            return;
        }

        $demos = [
            [
                'trx_code' => 'TRX-DEMO-DEBT-001',
                'name' => 'Budi Santoso',
                'phone' => '0812-1111-0001',
                'address' => 'Jl. Merdeka No. 10 (Demo)',
                'total' => 250000,
                'paid' => 100000,
                'status' => Transaction::PAYMENT_STATUS_PARTIAL,
                'days_ago' => 2,
            ],
            [
                'trx_code' => 'TRX-DEMO-DEBT-002',
                'name' => 'Siti Rahma',
                'phone' => '0812-1111-0002',
                'address' => 'Jl. Sudirman No. 24 (Demo)',
                'total' => 180000,
                'paid' => 0,
                'status' => Transaction::PAYMENT_STATUS_UNPAID,
                'days_ago' => 3,
            ],
            [
                'trx_code' => 'TRX-DEMO-DEBT-003',
                'name' => 'Andi Wijaya',
                'phone' => '0812-1111-0003',
                'address' => 'Jl. Ahmad Yani No. 8 (Demo)',
                'total' => 320000,
                'paid' => 200000,
                'status' => Transaction::PAYMENT_STATUS_PARTIAL,
                'days_ago' => 1,
            ],
            [
                'trx_code' => 'TRX-DEMO-DEBT-004',
                'name' => 'Rina Marlina',
                'phone' => '0812-1111-0004',
                'address' => 'Jl. Diponegoro No. 15 (Demo)',
                'total' => 95000,
                'paid' => 25000,
                'status' => Transaction::PAYMENT_STATUS_PARTIAL,
                'days_ago' => 1,
            ],
            [
                'trx_code' => 'TRX-DEMO-DEBT-005',
                'name' => 'Dedi Saputra',
                'phone' => '0812-1111-0005',
                'address' => 'Jl. Gajah Mada No. 42 (Demo)',
                'total' => 400000,
                'paid' => 0,
                'status' => Transaction::PAYMENT_STATUS_UNPAID,
                'days_ago' => 0,
            ],
        ];

        foreach ($demos as $demo) {
            $customer = Customer::firstOrCreate(
                ['name' => $demo['name'], 'phone' => $demo['phone']],
                [
                    'address' => $demo['address'],
                    'notes' => 'Demo customer with sample debt',
                    'is_active' => true,
                ]
            );

            if (Transaction::query()->where('transaction_code', $demo['trx_code'])->exists()) {
                continue;
            }

            $timestamp = now()->subDays($demo['days_ago']);
            $outstanding = max(0, $demo['total'] - $demo['paid']);

            $trx = Transaction::create([
                'transaction_code' => $demo['trx_code'],
                'user_id' => $cashier->id,
                'customer_id' => $customer->id,
                'subtotal' => $demo['total'],
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $demo['total'],
                'amount_paid' => $demo['paid'],
                'change_amount' => 0,
                'outstanding_balance' => $outstanding,
                'payment_method' => Transaction::PAYMENT_CASH,
                'payment_status' => $demo['status'],
                'transaction_status' => Transaction::STATUS_COMPLETED,
                'transaction_date' => $timestamp,
                'notes' => '[DEMO] Sample customer debt record',
            ]);

            $trx->timestamps = false;
            $trx->created_at = $timestamp;
            $trx->updated_at = $timestamp;
            $trx->save();

            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->product_name . ' (Paket Demo)',
                'product_code_snapshot' => $product->product_code,
                'quantity' => 1,
                'unit' => $product->unit,
                'conversion_ratio' => 1,
                'base_quantity' => 1,
                'price' => $demo['total'],
                'cost_price_snapshot' => round($demo['total'] * 0.8, 2),
                'subtotal' => $demo['total'],
                'profit_snapshot' => round($demo['total'] * 0.2, 2),
            ]);
        }
    }
}
