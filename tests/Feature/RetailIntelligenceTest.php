<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\RetailIntelligenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_restock_recommendations_respect_sales_lead_time_safety_stock_and_units(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cat = Category::create(['name' => 'Cigarettes', 'is_active' => true]);

        $cigarette = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Gudang Garam 12',
            'cost_price' => 1800,
            'price' => 2200,
            'stock' => 20,
            'minimum_stock' => 16,
            'lead_time_days' => 2,
            'safety_stock' => 16,
            'unit' => 'stick',
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $cigarette->id,
            'unit_name' => 'pack',
            'conversion_ratio' => 12,
            'selling_price' => 25000,
        ]);

        // 140 sticks sold over 14 days => 10 sticks/day => 2 days coverage <= lead_time (2) => critical
        $trx = Transaction::factory()->create(['user_id' => $cashier->id, 'transaction_status' => Transaction::STATUS_COMPLETED]);
        TransactionDetail::create([
            'transaction_id' => $trx->id,
            'product_id' => $cigarette->id,
            'product_name_snapshot' => $cigarette->product_name,
            'quantity' => 140,
            'unit' => 'stick',
            'conversion_ratio' => 1,
            'base_quantity' => 140,
            'price' => 2200,
            'cost_price_snapshot' => 1800,
            'subtotal' => 308000,
            'profit_snapshot' => 56000,
        ]);

        $service = app(RetailIntelligenceService::class);
        $recs = $service->getRestockRecommendations();

        $this->assertNotEmpty($recs);
        $this->assertSame('critical', $recs[0]['priority']);
        $this->assertSame(10.0, $recs[0]['avg_daily_sales']);
        $this->assertStringContainsString('pack', $recs[0]['recommended_display']);
    }

    public function test_dead_stock_detection_excludes_newly_created_products(): void
    {
        $cat = Category::create(['name' => 'Snacks', 'is_active' => true]);

        $oldUnsold = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Old Snack',
            'cost_price' => 5000,
            'stock' => 25,
            'is_active' => true,
        ]);
        $oldUnsold->timestamps = false;
        $oldUnsold->created_at = now()->subDays(45);
        $oldUnsold->save();

        // Newly added product today should NOT be flagged as dead stock
        Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Brand New Snack',
            'cost_price' => 5000,
            'stock' => 25,
            'is_active' => true,
        ]);

        $service = app(RetailIntelligenceService::class);
        $items = $service->getSlowMovingAndDeadStock(30);

        $names = array_column($items, 'product_name');
        $this->assertContains('Old Snack', $names);
        $this->assertNotContains('Brand New Snack', $names);
    }

    public function test_profit_intelligence_uses_historical_cost_snapshots_and_excludes_cancelled(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cat = Category::create(['name' => 'Basic Goods', 'is_active' => true]);

        $rice = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Beras 5kg',
            'cost_price' => 60000,
            'price' => 75000,
            'stock' => 20,
        ]);

        $completed = Transaction::factory()->create(['user_id' => $cashier->id, 'transaction_status' => Transaction::STATUS_COMPLETED]);
        TransactionDetail::create([
            'transaction_id' => $completed->id,
            'product_id' => $rice->id,
            'product_name_snapshot' => $rice->product_name,
            'quantity' => 2,
            'unit' => 'pack',
            'conversion_ratio' => 1,
            'base_quantity' => 2,
            'price' => 75000,
            'cost_price_snapshot' => 60000,
            'subtotal' => 150000,
            'profit_snapshot' => 30000,
        ]);

        // Cancelled transaction must be excluded
        $cancelled = Transaction::factory()->create(['user_id' => $cashier->id, 'transaction_status' => Transaction::STATUS_CANCELLED]);
        TransactionDetail::create([
            'transaction_id' => $cancelled->id,
            'product_id' => $rice->id,
            'product_name_snapshot' => $rice->product_name,
            'quantity' => 5,
            'unit' => 'pack',
            'conversion_ratio' => 1,
            'base_quantity' => 5,
            'price' => 75000,
            'cost_price_snapshot' => 60000,
            'subtotal' => 375000,
            'profit_snapshot' => 75000,
        ]);

        // Later cost price increases in catalog, but historical profit remains 30.000
        $rice->update(['cost_price' => 72000]);

        $service = app(RetailIntelligenceService::class);
        $profit = $service->getProfitIntelligence(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());

        $this->assertSame(150000.0, $profit['top_profit'][0]['revenue']);
        $this->assertSame(30000.0, $profit['top_profit'][0]['profit']);
    }

    public function test_sales_patterns_and_out_of_stock_alternatives(): void
    {
        $cashier = User::factory()->cashier()->create();
        $cat = Category::create(['name' => 'Beverages', 'is_active' => true]);

        $outOfStock = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Aqua 600ml',
            'price' => 4000,
            'stock' => 0,
            'unit' => 'bottle',
        ]);

        $inStockAlt = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Le Minerale 600ml',
            'price' => 4000,
            'stock' => 30,
            'unit' => 'bottle',
        ]);

        $outAlt = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Ades 600ml',
            'price' => 3500,
            'stock' => 0,
            'unit' => 'bottle',
        ]);

        // POS alternatives endpoint returns only in-stock alternatives
        $this->actingAs($cashier)
            ->getJson(route('pos.products.alternatives', $outOfStock))
            ->assertOk()
            ->assertJsonFragment(['product_name' => 'Le Minerale 600ml'])
            ->assertJsonMissing(['product_name' => 'Ades 600ml']);

        // Sales patterns with 3 completed transactions
        for ($i = 0; $i < 3; $i++) {
            $t = Transaction::factory()->create([
                'user_id' => $cashier->id,
                'transaction_status' => Transaction::STATUS_COMPLETED,
                'total_amount' => 20000,
            ]);
            $t->timestamps = false;
            $t->created_at = Carbon::today()->setTime(18, $i * 10);
            $t->save();
        }

        $patterns = app(RetailIntelligenceService::class)->getSalesPatterns(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
        $this->assertTrue($patterns['has_sufficient_data']);
        $this->assertSame(3, $patterns['total_transactions']);
        $this->assertStringContainsString('16:00', $patterns['busiest_window'] === '16:00 - 19:00' ? '16:00' : $patterns['busiest_window']);
    }
}
