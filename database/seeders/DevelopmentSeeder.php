<?php

namespace Database\Seeders;

use App\Enums\AiProcessingStatus;
use App\Enums\AiProcessingType;
use App\Enums\CartStatus;
use App\Enums\MatchStatus;
use App\Enums\OrderInputStatus;
use App\Enums\OrderInputType;
use App\Enums\ReviewStatus;
use App\Enums\StockMovementType;
use App\Models\AIProcessingRun;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderInput;
use App\Models\OrderInputItem;
use App\Models\Product;
use App\Models\Staff;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed development users, categories, products, carts, AI order samples, transactions, and stock movements.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@pos.test'],
            [
                'name' => 'Admin UMKM',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMINISTRATOR,
                'is_active' => true,
            ]
        );

        $cashierOne = User::updateOrCreate(
            ['email' => 'cashier1@pos.test'],
            [
                'name' => 'Rina',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CASHIER,
                'is_active' => true,
            ]
        );

        $cashierTwo = User::updateOrCreate(
            ['email' => 'cashier2@pos.test'],
            [
                'name' => 'Budi',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CASHIER,
                'is_active' => true,
            ]
        );

        foreach (['Andi', 'Joko', 'Dedi'] as $staffName) {
            Staff::firstOrCreate(
                ['name' => $staffName],
                [
                    'notes' => 'Demo staff member',
                    'is_active' => true,
                ]
            );
        }

        $categoriesData = [
            'Instant Noodles' => 'Berbagai varian mie instan dan makanan cepat saji kemasan.',
            'Beverages' => 'Air mineral, teh kemasan, kopi sachet, susu UHT, dan minuman ringan.',
            'Basic Goods' => 'Kebutuhan pokok harian seperti beras, gula, minyak goreng, telur, dan tepung.',
            'Snacks' => 'Makanan ringan, biskuit, roti, dan camilan kemasan.',
            'Household Products' => 'Produk kebersihan dan keperluan rumah tangga sehari-hari.',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $description) {
            $categories[$name] = Category::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }

        $productsData = [
            ['code' => 'PRD-001', 'name' => 'Indomie Goreng', 'category' => 'Instant Noodles', 'price' => 3500, 'stock' => 65, 'min' => 20, 'unit' => 'pcs'],
            ['code' => 'PRD-002', 'name' => 'Indomie Soto', 'category' => 'Instant Noodles', 'price' => 3500, 'stock' => 42, 'min' => 20, 'unit' => 'pcs'],
            ['code' => 'PRD-003', 'name' => 'Aqua 600ml', 'category' => 'Beverages', 'price' => 4000, 'stock' => 48, 'min' => 15, 'unit' => 'bottle'],
            ['code' => 'PRD-004', 'name' => 'Teh Botol Sosro', 'category' => 'Beverages', 'price' => 5000, 'stock' => 26, 'min' => 12, 'unit' => 'bottle'],
            ['code' => 'PRD-005', 'name' => 'Gula Pasir 1kg', 'category' => 'Basic Goods', 'price' => 17500, 'stock' => 0, 'min' => 10, 'unit' => 'kg'],
            ['code' => 'PRD-006', 'name' => 'Beras 5kg', 'category' => 'Basic Goods', 'price' => 74000, 'stock' => 5, 'min' => 5, 'unit' => 'pack'],
            ['code' => 'PRD-007', 'name' => 'Minyak Goreng 1L', 'category' => 'Basic Goods', 'price' => 18500, 'stock' => 3, 'min' => 10, 'unit' => 'liter'],
            ['code' => 'PRD-008', 'name' => 'Telur', 'category' => 'Basic Goods', 'price' => 29000, 'stock' => 4.5, 'min' => 8, 'unit' => 'kg'],
            ['code' => 'PRD-009', 'name' => 'Susu UHT', 'category' => 'Beverages', 'price' => 19500, 'stock' => 18, 'min' => 8, 'unit' => 'box'],
            ['code' => 'PRD-010', 'name' => 'Kopi Sachet', 'category' => 'Beverages', 'price' => 2000, 'stock' => 80, 'min' => 25, 'unit' => 'pcs'],
            ['code' => 'PRD-011', 'name' => 'Sabun Mandi', 'category' => 'Household Products', 'price' => 4500, 'stock' => 30, 'min' => 10, 'unit' => 'pcs'],
            ['code' => 'PRD-012', 'name' => 'Detergen', 'category' => 'Household Products', 'price' => 21500, 'stock' => 14, 'min' => 6, 'unit' => 'pack'],
            ['code' => 'PRD-013', 'name' => 'Biskuit', 'category' => 'Snacks', 'price' => 11000, 'stock' => 24, 'min' => 8, 'unit' => 'pack'],
            ['code' => 'PRD-014', 'name' => 'Roti', 'category' => 'Snacks', 'price' => 14500, 'stock' => 12, 'min' => 5, 'unit' => 'pack'],
            ['code' => 'PRD-015', 'name' => 'Kecap Manis 520ml', 'category' => 'Basic Goods', 'price' => 22000, 'stock' => 14, 'min' => 6, 'unit' => 'bottle'],
            ['code' => 'PRD-016', 'name' => 'Garam Dapur 500g', 'category' => 'Basic Goods', 'price' => 4500, 'stock' => 22, 'min' => 8, 'unit' => 'gram'],
            ['code' => 'PRD-017', 'name' => 'Tepung Terigu 1kg', 'category' => 'Basic Goods', 'price' => 13000, 'stock' => 16, 'min' => 6, 'unit' => 'kg'],
            ['code' => 'PRD-018', 'name' => 'Sabun Cuci Piring 750ml', 'category' => 'Household Products', 'price' => 16000, 'stock' => 11, 'min' => 5, 'unit' => 'pack'],
            ['code' => 'PRD-019', 'name' => 'Air Mineral Galon 19L', 'category' => 'Beverages', 'price' => 21000, 'stock' => 9, 'min' => 5, 'unit' => 'bottle'],
            ['code' => 'PRD-020', 'name' => 'Keripik Singkong', 'category' => 'Snacks', 'price' => 9500, 'stock' => 19, 'min' => 6, 'unit' => 'pack'],
        ];

        $products = [];
        foreach ($productsData as $item) {
            $products[$item['code']] = Product::updateOrCreate(
                ['product_code' => $item['code']],
                [
                    'category_id' => $categories[$item['category']]->id,
                    'product_name' => $item['name'],
                    'description' => 'Produk retail UMKM: ' . $item['name'],
                    'price' => $item['price'],
                    'stock' => $item['stock'],
                    'minimum_stock' => $item['min'],
                    'unit' => $item['unit'],
                    'is_active' => true,
                ]
            );
        }

        // Seed initial stock movements idempotently (one baseline stock_in per product if none exists)
        foreach ($products as $product) {
            StockMovement::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'movement_type' => StockMovementType::StockIn,
                    'note' => 'Initial development stock balance',
                ],
                [
                    'user_id' => $admin->id,
                    'transaction_id' => null,
                    'quantity' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                ]
            );
        }

        $sampleTransactions = [
            [
                'code' => 'TRX-DEV-001',
                'user' => $cashierOne,
                'days_ago' => 6,
                'hour' => 9,
                'minute' => 20,
                'payment_method' => Transaction::PAYMENT_CASH,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-006', 'qty' => 2],
                    ['code' => 'PRD-007', 'qty' => 3],
                    ['code' => 'PRD-001', 'qty' => 10],
                ],
            ],
            [
                'code' => 'TRX-DEV-002',
                'user' => $cashierTwo,
                'days_ago' => 5,
                'hour' => 11,
                'minute' => 45,
                'payment_method' => Transaction::PAYMENT_QRIS,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-008', 'qty' => 4],
                    ['code' => 'PRD-005', 'qty' => 5],
                    ['code' => 'PRD-003', 'qty' => 6],
                ],
            ],
            [
                'code' => 'TRX-DEV-003',
                'user' => $cashierOne,
                'days_ago' => 4,
                'hour' => 14,
                'minute' => 15,
                'payment_method' => Transaction::PAYMENT_CASH,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-009', 'qty' => 4],
                    ['code' => 'PRD-015', 'qty' => 3],
                    ['code' => 'PRD-018', 'qty' => 4],
                ],
            ],
            [
                'code' => 'TRX-DEV-004',
                'user' => $cashierTwo,
                'days_ago' => 3,
                'hour' => 16,
                'minute' => 30,
                'payment_method' => Transaction::PAYMENT_OTHER,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-006', 'qty' => 4],
                    ['code' => 'PRD-019', 'qty' => 3],
                    ['code' => 'PRD-017', 'qty' => 5],
                ],
            ],
            [
                'code' => 'TRX-DEV-005',
                'user' => $cashierOne,
                'days_ago' => 2,
                'hour' => 10,
                'minute' => 10,
                'payment_method' => Transaction::PAYMENT_QRIS,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-001', 'qty' => 15],
                    ['code' => 'PRD-002', 'qty' => 10],
                    ['code' => 'PRD-004', 'qty' => 8],
                    ['code' => 'PRD-008', 'qty' => 3],
                ],
            ],
            [
                'code' => 'TRX-DEV-006',
                'user' => $cashierTwo,
                'days_ago' => 1,
                'hour' => 15,
                'minute' => 50,
                'payment_method' => Transaction::PAYMENT_CASH,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-006', 'qty' => 3],
                    ['code' => 'PRD-007', 'qty' => 4],
                    ['code' => 'PRD-005', 'qty' => 4],
                    ['code' => 'PRD-010', 'qty' => 15],
                ],
            ],
            [
                'code' => 'TRX-DEV-007',
                'user' => $cashierOne,
                'days_ago' => 1,
                'hour' => 18,
                'minute' => 5,
                'payment_method' => Transaction::PAYMENT_OTHER,
                'status' => Transaction::STATUS_CANCELLED,
                'items' => [
                    ['code' => 'PRD-009', 'qty' => 2],
                ],
            ],
            [
                'code' => 'TRX-DEV-008',
                'user' => $cashierOne,
                'days_ago' => 0,
                'hour' => 8,
                'minute' => 40,
                'payment_method' => Transaction::PAYMENT_CASH,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-006', 'qty' => 2],
                    ['code' => 'PRD-007', 'qty' => 2],
                    ['code' => 'PRD-008', 'qty' => 2],
                    ['code' => 'PRD-001', 'qty' => 6],
                ],
            ],
            [
                'code' => 'TRX-DEV-009',
                'user' => $cashierTwo,
                'days_ago' => 0,
                'hour' => 11,
                'minute' => 25,
                'payment_method' => Transaction::PAYMENT_QRIS,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-009', 'qty' => 3],
                    ['code' => 'PRD-003', 'qty' => 5],
                    ['code' => 'PRD-004', 'qty' => 4],
                    ['code' => 'PRD-010', 'qty' => 10],
                ],
            ],
            [
                'code' => 'TRX-DEV-010',
                'user' => $admin,
                'days_ago' => 0,
                'hour' => 14,
                'minute' => 10,
                'payment_method' => Transaction::PAYMENT_OTHER,
                'status' => Transaction::STATUS_COMPLETED,
                'items' => [
                    ['code' => 'PRD-019', 'qty' => 4],
                    ['code' => 'PRD-015', 'qty' => 2],
                    ['code' => 'PRD-018', 'qty' => 3],
                    ['code' => 'PRD-017', 'qty' => 4],
                ],
            ],
            [
                'code' => 'TRX-DEV-011',
                'user' => $cashierOne,
                'days_ago' => 0,
                'hour' => 16,
                'minute' => 5,
                'payment_method' => Transaction::PAYMENT_CASH,
                'status' => Transaction::STATUS_PENDING,
                'items' => [
                    ['code' => 'PRD-002', 'qty' => 5],
                    ['code' => 'PRD-016', 'qty' => 3],
                ],
            ],
        ];

        foreach ($sampleTransactions as $trxData) {
            $timestamp = now()
                ->subDays($trxData['days_ago'])
                ->setTime($trxData['hour'], $trxData['minute'], 0);

            if ($trxData['days_ago'] === 0 && $timestamp->isFuture()) {
                $timestamp = now()->subMinutes(($trxData['hour'] + 1) * 5);
            }

            $subtotal = 0;
            $resolvedItems = [];
            foreach ($trxData['items'] as $item) {
                $product = $products[$item['code']];
                $lineSubtotal = (float) $product->price * $item['qty'];
                $subtotal += $lineSubtotal;
                $resolvedItems[] = [
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->product_name,
                    'product_code_snapshot' => $product->product_code,
                    'quantity' => $item['qty'],
                    'unit' => $product->unit,
                    'price' => $product->price,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $transaction = Transaction::updateOrCreate(
                ['transaction_code' => $trxData['code']],
                [
                    'user_id' => $trxData['user']->id,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $subtotal,
                    'amount_paid' => $trxData['status'] === Transaction::STATUS_COMPLETED ? $subtotal : 0,
                    'change_amount' => 0,
                    'payment_method' => $trxData['payment_method'],
                    'transaction_status' => $trxData['status'],
                    'transaction_date' => $timestamp,
                ]
            );

            $transaction->timestamps = false;
            $transaction->created_at = $timestamp;
            $transaction->updated_at = $timestamp;
            $transaction->save();

            TransactionDetail::where('transaction_id', $transaction->id)->delete();
            foreach ($resolvedItems as $line) {
                $detail = new TransactionDetail([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product_id'],
                    'product_name_snapshot' => $line['product_name_snapshot'],
                    'product_code_snapshot' => $line['product_code_snapshot'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'price' => $line['price'],
                    'subtotal' => $line['subtotal'],
                ]);
                $detail->timestamps = false;
                $detail->created_at = $timestamp;
                $detail->updated_at = $timestamp;
                $detail->save();
            }
        }

        // Seed one sample active cart and AI voice order pipeline for thesis testing
        $sampleCart = Cart::firstOrCreate(
            [
                'user_id' => $cashierOne->id,
                'cart_status' => CartStatus::Active,
            ],
            [
                'subtotal' => 18500,
                'total' => 18500,
            ]
        );

        $indomie = $products['PRD-001'];
        $aqua = $products['PRD-003'];

        CartItem::updateOrCreate(
            [
                'cart_id' => $sampleCart->id,
                'product_id' => $indomie->id,
            ],
            [
                'quantity' => 3,
                'price_snapshot' => $indomie->price,
                'subtotal' => (float) $indomie->price * 3,
            ]
        );

        CartItem::updateOrCreate(
            [
                'cart_id' => $sampleCart->id,
                'product_id' => $aqua->id,
            ],
            [
                'quantity' => 2,
                'price_snapshot' => $aqua->price,
                'subtotal' => (float) $aqua->price * 2,
            ]
        );

        $orderInput = OrderInput::firstOrCreate(
            [
                'user_id' => $cashierOne->id,
                'cart_id' => $sampleCart->id,
                'input_type' => OrderInputType::Voice,
            ],
            [
                'original_text' => 'Beli Indomie goreng 3 sama Aqua botol 2',
                'file_path' => 'order_inputs/voice/sample-order-001.webm',
                'processing_status' => OrderInputStatus::Reviewed,
                'metadata' => ['duration_seconds' => 4.2, 'locale' => 'id-ID'],
            ]
        );

        $aiRun = AIProcessingRun::firstOrCreate(
            [
                'order_input_id' => $orderInput->id,
                'processing_type' => AiProcessingType::LlmExtraction,
            ],
            [
                'service_name' => 'development-mock',
                'model_name' => 'gemini-flash',
                'prompt_version' => 'v1.0',
                'recognized_text' => 'Beli Indomie goreng 3 sama Aqua botol 2',
                'structured_output' => [
                    'items' => [
                        ['name' => 'Indomie goreng', 'quantity' => 3, 'unit' => 'pcs'],
                        ['name' => 'Aqua botol', 'quantity' => 2, 'unit' => 'bottle'],
                    ],
                ],
                'processing_status' => AiProcessingStatus::Completed,
                'processing_duration_ms' => 420,
            ]
        );

        OrderInputItem::updateOrCreate(
            [
                'ai_processing_run_id' => $aiRun->id,
                'requested_product_name' => 'Indomie goreng',
            ],
            [
                'requested_quantity' => 3,
                'requested_unit' => 'pcs',
                'matched_product_id' => $indomie->id,
                'match_confidence' => 0.9650,
                'match_status' => MatchStatus::Matched,
                'confirmed_product_id' => $indomie->id,
                'confirmed_quantity' => 3,
                'review_status' => ReviewStatus::Confirmed,
            ]
        );

        OrderInputItem::updateOrCreate(
            [
                'ai_processing_run_id' => $aiRun->id,
                'requested_product_name' => 'Aqua botol',
            ],
            [
                'requested_quantity' => 2,
                'requested_unit' => 'bottle',
                'matched_product_id' => $aqua->id,
                'match_confidence' => 0.9100,
                'match_status' => MatchStatus::Matched,
                'confirmed_product_id' => $aqua->id,
                'confirmed_quantity' => 2,
                'review_status' => ReviewStatus::Confirmed,
            ]
        );
    }
}
