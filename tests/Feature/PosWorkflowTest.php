<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_categories_and_products_with_stock_movements(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('categories.store'), [
            'name' => 'Minuman Ringan',
            'description' => 'Air & teh',
            'is_active' => '1',
        ])->assertRedirect(route('categories.index'));

        $category = Category::where('name', 'Minuman Ringan')->firstOrFail();

        $this->actingAs($admin)->post(route('products.store'), [
            'category_id' => $category->id,
            'product_code' => 'PRD-NEW-1',
            'product_name' => 'Teh Pucuk 350ml',
            'price' => 4000,
            'stock' => 20,
            'minimum_stock' => 5,
            'unit' => 'bottle',
            'is_active' => '1',
        ])->assertRedirect();

        $product = Product::where('product_code', 'PRD-NEW-1')->firstOrFail();
        $this->assertSame('20.000', $product->stock);

        // Cannot delete category while it has products
        $this->actingAs($admin)
            ->from(route('categories.index'))
            ->delete(route('categories.destroy', $category))
            ->assertSessionHasErrors('category');

        // Stock adjustment creates movement record and prevents negative stock
        $this->actingAs($admin)->post(route('products.adjust-stock', $product), [
            'movement_type' => StockMovementType::StockIn->value,
            'quantity' => 10,
            'note' => 'Restock pagi',
        ])->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('30.000', $product->stock);

        $this->actingAs($admin)
            ->from(route('products.show', $product))
            ->post(route('products.adjust-stock', $product), [
                'movement_type' => StockMovementType::StockOut->value,
                'quantity' => 50,
                'note' => 'Invalid deduction',
            ])->assertSessionHasErrors('quantity');
    }

    public function test_manual_pos_cart_checkout_receipt_and_cancellation_workflow(): void
    {
        $admin = User::factory()->administrator()->create();
        $cashier1 = User::factory()->cashier()->create();
        $cashier2 = User::factory()->cashier()->create();

        $product = Product::factory()->create([
            'product_name' => 'Indomie Goreng',
            'product_code' => 'PRD-001',
            'price' => 3500,
            'stock' => 10,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Cashier 1 adds 3 items to cart
        $this->actingAs($cashier1)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertRedirect();

        // Insufficient cash payment without a customer is rejected and stock is NOT deducted
        $this->actingAs($cashier1)
            ->from(route('pos.index'))
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_paid' => 5000,
            ])->assertSessionHasErrors('customer_id');

        $this->assertSame('10.000', $product->fresh()->stock);

        // Valid cash checkout succeeds, deducts stock, records snapshot and change
        $this->actingAs($cashier1)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 20000,
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('10500.00', $transaction->total_amount);
        $this->assertSame('9500.00', $transaction->change_amount);
        $this->assertSame('7.000', $product->fresh()->stock);
        $this->assertDatabaseHas('transaction_details', [
            'transaction_id' => $transaction->id,
            'product_name_snapshot' => 'Indomie Goreng',
            'product_code_snapshot' => 'PRD-001',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'movement_type' => StockMovementType::Sale->value,
        ]);

        // Receipt renders for owner cashier, forbidden for another cashier
        $this->actingAs($cashier1)->get(route('transactions.receipt', $transaction))->assertOk()->assertSee('Indomie Goreng');
        $this->actingAs($cashier2)->get(route('transactions.show', $transaction))->assertForbidden();

        // Administrator can cancel transaction and restore stock
        $this->actingAs($admin)->post(route('transactions.cancel', $transaction), [
            'reason' => 'Pelanggan batal beli',
        ])->assertRedirect(route('transactions.show', $transaction));

        $this->assertSame(Transaction::STATUS_CANCELLED, $transaction->fresh()->transaction_status);
        $this->assertSame('10.000', $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'movement_type' => StockMovementType::CancellationReversal->value,
        ]);
    }

    public function test_cashier_cannot_access_admin_reports_inventory_or_user_management(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('ai-history.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
    }

    public function test_all_admin_and_pos_pages_render_without_blade_errors(): void
    {
        $admin = User::factory()->administrator()->create();

        foreach ([
            'dashboard',
            'pos.index',
            'pos.ai.voice',
            'pos.ai.image',
            'products.index',
            'products.create',
            'categories.index',
            'inventory.index',
            'transactions.index',
            'reports.index',
            'reports.print',
            'ai-history.index',
            'users.index',
            'settings.index',
        ] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }

        // Verify categories and users Edit button renders cleanly without broken Blade @json text
        Category::create(['name' => 'Test Cat', 'is_active' => true]);
        $this->actingAs($admin)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertDontSee('$category->id');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('$user->id');

        // Verify CSV export download works
        $this->actingAs($admin)
            ->get(route('reports.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_product_sku_is_auto_generated_when_left_blank(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::create(['name' => 'Basic Goods', 'is_active' => true]);

        Product::factory()->create([
            'category_id' => $category->id,
            'product_code' => 'PRD-200',
        ]);

        $this->actingAs($admin)->post(route('products.store'), [
            'category_id' => $category->id,
            'product_code' => '',
            'product_name' => 'Gula Merah 500g',
            'price' => 14000,
            'stock' => 10,
            'minimum_stock' => 3,
            'unit' => 'pack',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'product_name' => 'Gula Merah 500g',
            'product_code' => 'PRD-201',
        ]);
    }

    public function test_complete_checkout_review_edit_order_confirm_and_receipt_flow(): void
    {
        $cashier = User::factory()->cashier()->create();
        $p1 = Product::factory()->create([
            'product_name' => 'Indomie Goreng',
            'product_code' => 'PRD-001',
            'price' => 3500,
            'stock' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
        $p2 = Product::factory()->create([
            'product_name' => 'Aqua 600ml',
            'product_code' => 'PRD-003',
            'price' => 4000,
            'stock' => 20,
            'unit' => 'bottle',
            'is_active' => true,
        ]);

        // 1. Add initial product
        $this->actingAs($cashier)->post(route('pos.cart.add'), [
            'product_id' => $p1->id,
            'quantity' => 2,
        ])->assertRedirect();

        // 2. Proceed to checkout review (does NOT finalize transaction or deduct stock yet)
        $this->actingAs($cashier)->post(route('pos.checkout.prepare'), [
            'payment_method' => 'cash',
            'amount_paid' => 20000,
            'discount_amount' => 0,
        ])->assertRedirect(route('pos.checkout.review'));

        $this->actingAs($cashier)
            ->get(route('pos.checkout.review'))
            ->assertOk()
            ->assertSee('Indomie Goreng')
            ->assertSee('Rp 7.000');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('20.000', $p1->fresh()->stock);

        // 3. Return to POS to add another product, then update quantity & remove item from checkout review
        $this->actingAs($cashier)->post(route('pos.cart.add'), [
            'product_id' => $p2->id,
            'quantity' => 1,
        ])->assertRedirect();

        $p1Item = \App\Models\CartItem::where('product_id', $p1->id)->firstOrFail();
        $p2Item = \App\Models\CartItem::where('product_id', $p2->id)->firstOrFail();

        // Update Indomie quantity from 2 to 4 right from checkout review
        $this->actingAs($cashier)->patch(route('pos.cart.update', $p1Item), [
            'quantity' => 4,
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('pos.checkout.review'));

        // Remove Aqua from checkout review
        $this->actingAs($cashier)->delete(route('pos.cart.remove', $p2Item), [
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('pos.checkout.review'));

        // Verify totals updated (4 * 3500 = Rp 14.000)
        $this->actingAs($cashier)
            ->get(route('pos.checkout.review'))
            ->assertOk()
            ->assertSee('Rp 14.000')
            ->assertDontSee('Aqua 600ml');

        // 4. Confirm & Finalize Transaction -> stock deducted & receipt printable
        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 20000,
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('14000.00', $transaction->total_amount);
        $this->assertSame('6000.00', $transaction->change_amount);
        $this->assertSame('16.000', $p1->fresh()->stock);
        $this->assertSame('20.000', $p2->fresh()->stock);

        $this->actingAs($cashier)
            ->get(route('transactions.receipt', $transaction))
            ->assertOk()
            ->assertSee('Indomie Goreng')
            ->assertSee('Rp 14.000');
    }

    public function test_pos_instant_search_endpoint_matches_partial_names_multi_words_sku_and_category(): void
    {
        $cashier = User::factory()->cashier()->create();
        $noodles = Category::create(['name' => 'Instant Noodles', 'is_active' => true]);
        $drinks = Category::create(['name' => 'Beverages', 'is_active' => true]);

        Product::factory()->create([
            'category_id' => $noodles->id,
            'product_name' => 'Indomie Goreng',
            'product_code' => 'PRD-001',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $noodles->id,
            'product_name' => 'Indomie Soto',
            'product_code' => 'PRD-002',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'category_id' => $drinks->id,
            'product_name' => 'Aqua 600ml',
            'product_code' => 'PRD-003',
            'is_active' => true,
        ]);

        // Partial prefix
        $this->actingAs($cashier)
            ->getJson(route('pos.products.search', ['search' => 'ind']))
            ->assertOk()
            ->assertJsonCount(2, 'products');

        // Multi-word partial match
        $this->actingAs($cashier)
            ->getJson(route('pos.products.search', ['search' => 'indomie gor']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.product_name', 'Indomie Goreng');

        // SKU search
        $this->actingAs($cashier)
            ->getJson(route('pos.products.search', ['search' => 'PRD-003']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.product_name', 'Aqua 600ml');

        // Category + empty search restores all in category
        $this->actingAs($cashier)
            ->getJson(route('pos.products.search', ['category_id' => $drinks->id]))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.product_name', 'Aqua 600ml');
    }
}
