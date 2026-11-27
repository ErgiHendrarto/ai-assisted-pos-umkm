<?php

namespace Tests\Feature;

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
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_and_key_columns_exist(): void
    {
        $tables = [
            'users' => ['id', 'name', 'email', 'password', 'role', 'is_active', 'email_verified_at', 'remember_token'],
            'categories' => ['id', 'name', 'description', 'is_active', 'deleted_at'],
            'products' => ['id', 'category_id', 'product_code', 'product_name', 'description', 'price', 'stock', 'minimum_stock', 'unit', 'image_path', 'is_active', 'deleted_at'],
            'carts' => ['id', 'user_id', 'cart_status', 'subtotal', 'total'],
            'cart_items' => ['id', 'cart_id', 'product_id', 'quantity', 'price_snapshot', 'subtotal'],
            'order_inputs' => ['id', 'user_id', 'cart_id', 'input_type', 'original_text', 'file_path', 'processing_status', 'metadata'],
            'ai_processing_runs' => ['id', 'order_input_id', 'processing_type', 'service_name', 'model_name', 'prompt_version', 'recognized_text', 'structured_output', 'processing_status', 'error_message', 'processing_duration_ms'],
            'order_input_items' => ['id', 'ai_processing_run_id', 'requested_product_name', 'requested_quantity', 'requested_unit', 'matched_product_id', 'match_confidence', 'match_status', 'confirmed_product_id', 'confirmed_quantity', 'review_status'],
            'transactions' => ['id', 'transaction_code', 'user_id', 'cart_id', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'amount_paid', 'change_amount', 'payment_method', 'transaction_status', 'transaction_date', 'notes'],
            'transaction_details' => ['id', 'transaction_id', 'product_id', 'product_name_snapshot', 'product_code_snapshot', 'quantity', 'unit', 'price', 'subtotal'],
            'stock_movements' => ['id', 'product_id', 'user_id', 'transaction_id', 'movement_type', 'quantity', 'stock_before', 'stock_after', 'note'],
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Missing columns in table: {$table}");
        }
    }

    public function test_cart_items_enforce_unique_product_per_cart_and_preserve_price_snapshot(): void
    {
        $cashier = User::factory()->cashier()->create();
        $product = Product::factory()->create([
            'price' => 3500,
            'stock' => 20.500,
            'unit' => 'kg',
        ]);

        $cart = Cart::create([
            'user_id' => $cashier->id,
            'cart_status' => CartStatus::Active,
            'subtotal' => 7000,
            'total' => 7000,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2.000,
            'price_snapshot' => $product->price,
            'subtotal' => 7000,
        ]);

        // Product price changes later, but cart snapshot stays 3500.00
        $product->update(['price' => 4500]);
        $item->refresh();
        $this->assertSame('3500.00', $item->price_snapshot);

        // Duplicate product in same cart is rejected by unique constraint
        $this->expectException(QueryException::class);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_snapshot' => 4500,
            'subtotal' => 4500,
        ]);
    }

    public function test_ai_order_pipeline_and_eloquent_relationships(): void
    {
        $cashier = User::factory()->cashier()->create();
        $product = Product::factory()->create(['product_name' => 'Indomie Goreng', 'product_code' => 'PRD-001', 'price' => 3500]);

        $cart = Cart::factory()->create(['user_id' => $cashier->id]);

        $orderInput = OrderInput::create([
            'user_id' => $cashier->id,
            'cart_id' => $cart->id,
            'input_type' => OrderInputType::Voice,
            'original_text' => 'Indomie goreng 3',
            'file_path' => 'order_inputs/voice/test.webm',
            'processing_status' => OrderInputStatus::Completed,
            'metadata' => ['duration_ms' => 2100],
        ]);

        $run = AIProcessingRun::create([
            'order_input_id' => $orderInput->id,
            'processing_type' => AiProcessingType::LlmExtraction,
            'service_name' => 'gemini',
            'model_name' => 'gemini-flash',
            'prompt_version' => 'v1',
            'recognized_text' => 'Indomie goreng 3',
            'structured_output' => ['items' => [['name' => 'Indomie goreng', 'qty' => 3]]],
            'processing_status' => AiProcessingStatus::Completed,
            'processing_duration_ms' => 320,
        ]);

        $extractedItem = OrderInputItem::create([
            'ai_processing_run_id' => $run->id,
            'requested_product_name' => 'Indomie goreng',
            'requested_quantity' => 3,
            'requested_unit' => 'pcs',
            'matched_product_id' => $product->id,
            'match_confidence' => 0.9500,
            'match_status' => MatchStatus::Matched,
            'confirmed_product_id' => $product->id,
            'confirmed_quantity' => 3,
            'review_status' => ReviewStatus::Confirmed,
        ]);

        $this->assertTrue($cashier->orderInputs->contains($orderInput));
        $this->assertTrue($cart->orderInputs->contains($orderInput));
        $this->assertTrue($orderInput->aiProcessingRuns->contains($run));
        $this->assertTrue($run->items->contains($extractedItem));
        $this->assertSame($product->id, $extractedItem->matchedProduct->id);
        $this->assertSame($product->id, $extractedItem->confirmedProduct->id);
        $this->assertSame(2100, $orderInput->metadata['duration_ms']);
    }

    public function test_transaction_detail_snapshots_and_stock_movements_are_preserved(): void
    {
        $cashier = User::factory()->cashier()->create();
        $product = Product::factory()->create([
            'product_name' => 'Beras 5kg',
            'product_code' => 'PRD-006',
            'price' => 74000,
            'stock' => 10,
            'unit' => 'pack',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $cashier->id,
            'subtotal' => 148000,
            'total_amount' => 148000,
            'amount_paid' => 150000,
            'change_amount' => 2000,
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->product_name,
            'product_code_snapshot' => $product->product_code,
            'quantity' => 2,
            'unit' => $product->unit,
            'price' => $product->price,
            'subtotal' => 148000,
        ]);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $cashier->id,
            'transaction_id' => $transaction->id,
            'movement_type' => StockMovementType::Sale,
            'quantity' => 2,
            'stock_before' => 10,
            'stock_after' => 8,
            'note' => 'POS sale ' . $transaction->transaction_code,
        ]);

        // Soft deleting the product preserves historical transaction details and stock movements
        $product->delete();
        $detail->refresh();
        $movement->refresh();

        $this->assertSame('Beras 5kg', $detail->product_name_snapshot);
        $this->assertSame('PRD-006', $detail->product_code_snapshot);
        $this->assertSame('74000.00', $detail->price);
        $this->assertSame('8.000', $movement->stock_after);
        $this->assertTrue($transaction->stockMovements->contains($movement));
    }

    public function test_development_seeder_is_idempotent(): void
    {
        $this->seed(DevelopmentSeeder::class);
        $firstCounts = [
            'users' => User::count(),
            'categories' => Category::count(),
            'products' => Product::count(),
            'transactions' => Transaction::count(),
            'carts' => Cart::count(),
            'order_inputs' => OrderInput::count(),
            'ai_runs' => AIProcessingRun::count(),
            'stock_movements' => StockMovement::count(),
        ];

        $this->seed(DevelopmentSeeder::class);
        $secondCounts = [
            'users' => User::count(),
            'categories' => Category::count(),
            'products' => Product::count(),
            'transactions' => Transaction::count(),
            'carts' => Cart::count(),
            'order_inputs' => OrderInput::count(),
            'ai_runs' => AIProcessingRun::count(),
            'stock_movements' => StockMovement::count(),
        ];

        $this->assertSame($firstCounts, $secondCounts);
    }
}
