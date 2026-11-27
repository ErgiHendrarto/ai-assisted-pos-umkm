<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_clean_empty_states_when_database_is_empty(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Rp 0')
            ->assertSee('No Sales Records Yet')
            ->assertSee('No Transactions Found')
            ->assertSee('Stock Levels Are Sufficient')
            ->assertViewHas('todaySales', 0.0)
            ->assertViewHas('todayTransactionsCount', 0)
            ->assertViewHas('totalActiveProducts', 0)
            ->assertViewHas('lowStockCount', 0);
    }

    public function test_dashboard_displays_accurate_database_statistics_chart_recent_transactions_and_low_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $cashier = User::factory()->cashier()->create(['name' => 'Cashier One']);

        $category = Category::create([
            'name' => 'Sembako',
            'description' => 'Bahan pokok',
        ]);

        $lowProduct = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Minyak Goreng 1L',
            'product_code' => 'PRD-TEST-1',
            'price' => 18500,
            'stock' => 3,
            'minimum_stock' => 10,
            'unit' => 'pouch',
            'is_active' => true,
        ]);

        $normalProduct = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Indomie Goreng',
            'product_code' => 'PRD-TEST-2',
            'price' => 3500,
            'stock' => 50,
            'minimum_stock' => 15,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Inactive low-stock product should NOT count toward active products or low stock alert
        Product::create([
            'category_id' => $category->id,
            'product_name' => 'Produk Nonaktif',
            'product_code' => 'PRD-TEST-3',
            'price' => 5000,
            'stock' => 0,
            'minimum_stock' => 5,
            'unit' => 'pcs',
            'is_active' => false,
        ]);

        // Completed transaction today
        $todayCompleted = Transaction::create([
            'transaction_code' => 'TRX-TODAY-1',
            'user_id' => $cashier->id,
            'total_amount' => 1250000,
            'payment_method' => 'qris',
            'transaction_status' => Transaction::STATUS_COMPLETED,
        ]);

        TransactionDetail::create([
            'transaction_id' => $todayCompleted->id,
            'product_id' => $lowProduct->id,
            'quantity' => 2,
            'price' => 18500,
            'subtotal' => 37000,
        ]);

        // Pending transaction today should NOT be counted in Today's Sales or Today's Transactions
        Transaction::create([
            'transaction_code' => 'TRX-TODAY-2',
            'user_id' => $cashier->id,
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'transaction_status' => Transaction::STATUS_PENDING,
        ]);

        // Completed transaction 2 days ago (included in 7-day chart, excluded from today's KPIs)
        $twoDaysAgo = Transaction::create([
            'transaction_code' => 'TRX-OLD-1',
            'user_id' => $cashier->id,
            'total_amount' => 300000,
            'payment_method' => 'cash',
            'transaction_status' => Transaction::STATUS_COMPLETED,
        ]);
        $twoDaysAgo->timestamps = false;
        $twoDaysAgo->created_at = now()->subDays(2);
        $twoDaysAgo->updated_at = now()->subDays(2);
        $twoDaysAgo->save();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('todaySales', 1250000.0)
            ->assertViewHas('todaySalesFormatted', 'Rp 1.250.000')
            ->assertViewHas('todayTransactionsCount', 1)
            ->assertViewHas('totalActiveProducts', 2)
            ->assertViewHas('lowStockCount', 1)
            ->assertSee('Rp 1.250.000')
            ->assertSee('TRX-TODAY-1')
            ->assertSee('Minyak Goreng 1L')
            ->assertDontSee('Produk Nonaktif');

        $salesChart = $response->viewData('salesChart');
        $this->assertCount(7, $salesChart['labels']);
        $this->assertCount(7, $salesChart['values']);
        $this->assertTrue($salesChart['has_data']);
        $this->assertSame(1550000.0, array_sum($salesChart['values']));

        // Transaction detail placeholder link works
        $this->actingAs($admin)
            ->get(route('transactions.show', $todayCompleted))
            ->assertOk()
            ->assertSee('TRX-TODAY-1');
    }

    public function test_development_seeder_populates_expected_records_and_hashed_users(): void
    {
        $this->seed(DevelopmentSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertGreaterThanOrEqual(5, Category::count());
        $this->assertGreaterThanOrEqual(20, Product::count());
        $this->assertGreaterThanOrEqual(10, Transaction::count());
        $this->assertGreaterThanOrEqual(20, TransactionDetail::count());

        $admin = User::where('email', 'admin@pos.test')->firstOrFail();
        $this->assertSame(User::ROLE_ADMINISTRATOR, $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));

        $cashier = User::where('email', 'cashier1@pos.test')->firstOrFail();
        $this->assertSame(User::ROLE_CASHIER, $cashier->role);
        $this->assertTrue(Hash::check('password', $cashier->password));

        // Verify seeded dashboard renders without errors, limits recent transactions to 5, and excludes healthy-stock items from low-stock alert
        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('TRX-DEV-011')
            ->assertSee('Gula Pasir 1kg')
            ->assertSee('Minyak Goreng 1L');

        $this->assertFalse(
            $response->viewData('lowStockProducts')->contains('product_name', 'Indomie Goreng')
        );

        $this->assertCount(5, $response->viewData('recentTransactions'));
    }
}
