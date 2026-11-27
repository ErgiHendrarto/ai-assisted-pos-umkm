<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedPosFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_unit_product_cost_profit_and_base_stock_conversion_deduction(): void
    {
        $admin = User::factory()->administrator()->create();
        $cashier = User::factory()->cashier()->create();
        $category = Category::create(['name' => 'Cigarettes', 'is_active' => true]);

        // Custom unit creation
        $this->actingAs($admin)->post(route('units.store'), ['name' => 'slop'])->assertRedirect();
        $this->assertDatabaseHas('units', ['name' => 'slop']);

        // Create cigarette sold per stick (base), pack (16 sticks), slop (160 sticks)
        $this->actingAs($admin)->post(route('products.store'), [
            'category_id' => $category->id,
            'product_code' => '',
            'product_name' => 'Sampoerna Mild 16',
            'cost_price' => 1800,
            'price' => 2500,
            'stock' => 320,
            'minimum_stock' => 32,
            'unit' => 'stick',
            'is_active' => '1',
            'product_units' => [
                ['unit_name' => 'pack', 'conversion_ratio' => 16, 'selling_price' => 36000],
                ['unit_name' => 'slop', 'conversion_ratio' => 160, 'selling_price' => 350000],
            ],
        ])->assertRedirect();

        $product = Product::where('product_name', 'Sampoerna Mild 16')->firstOrFail();
        $this->assertSame(700.0, $product->baseProfit());

        $packUnit = ProductUnit::where('product_id', $product->id)->where('unit_name', 'pack')->firstOrFail();
        $slopUnit = ProductUnit::where('product_id', $product->id)->where('unit_name', 'slop')->firstOrFail();
        $this->assertSame(28800.0, $packUnit->unitCost());
        $this->assertSame(7200.0, $packUnit->unitProfit());

        // Cashier sells 1 slop (160 sticks) + 2 packs (32 sticks) = 192 base sticks
        $this->actingAs($cashier)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'product_unit_id' => $slopUnit->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->actingAs($cashier)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'product_unit_id' => $packUnit->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'payment_mode' => 'full',
            'amount_paid' => 500000,
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('422000.00', $transaction->total_amount);
        // Remaining stock in base unit (stick): 320 - 192 = 128
        $this->assertSame('128.000', $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $transaction->id,
            'movement_type' => StockMovementType::Sale->value,
        ]);

        // Cancelling restores all 192 base sticks
        $this->actingAs($admin)->post(route('transactions.cancel', $transaction), [
            'reason' => 'Salah input unit',
        ])->assertRedirect();
        $this->assertSame('320.000', $product->fresh()->stock);
    }

    public function test_customer_partial_and_debt_checkout_repayments_and_receipt(): void
    {
        $admin = User::factory()->administrator()->create();
        $cashier = User::factory()->cashier()->create();
        $product = Product::factory()->create(['price' => 25000, 'cost_price' => 20000, 'stock' => 10, 'unit' => 'pcs']);

        // Cashier quick-adds customer during POS
        $resp = $this->actingAs($cashier)->postJson(route('pos.customers.quick'), [
            'name' => 'Bu Siti Warung',
            'phone' => '08123456789',
        ])->assertCreated();

        $customerId = $resp->json('customer.id');

        // Debt checkout without customer must fail (amount_paid = 0 < total)
        $this->actingAs($cashier)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($cashier)
            ->from(route('pos.checkout.review'))
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_paid' => 0,
            ])->assertSessionHasErrors('customer_id');

        // Automatic partial payment: Total 50.000, Amount Paid 20.000 -> Partially Paid, Debt 30.000
        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'customer_id' => $customerId,
            'payment_method' => 'cash',
            'amount_paid' => 20000,
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(Transaction::PAYMENT_STATUS_PARTIAL, $transaction->payment_status);
        $this->assertSame('20000.00', $transaction->amount_paid);
        $this->assertSame('30000.00', $transaction->outstanding_balance);

        // Receipt displays customer & outstanding debt
        $this->actingAs($cashier)
            ->get(route('transactions.receipt', $transaction))
            ->assertOk()
            ->assertSee('Bu Siti Warung')
            ->assertSee('Partially Paid')
            ->assertSee('Rp 30.000');

        // Cashier can also record partial repayment of 15.000 -> remaining 15.000 & print receipt
        $this->actingAs($cashier)->post(route('debts.pay', $transaction), [
            'amount' => 15000,
            'payment_method' => 'qris',
            'note' => 'Cicilan 1',
        ])->assertRedirect();

        $payment = \App\Models\DebtPayment::firstOrFail();
        $this->actingAs($cashier)->get(route('debts.receipt', $payment))->assertOk()->assertSee('Rp 15.000');

        $transaction->refresh();
        $this->assertSame('35000.00', $transaction->amount_paid);
        $this->assertSame('15000.00', $transaction->outstanding_balance);
        $this->assertSame(Transaction::PAYMENT_STATUS_PARTIAL, $transaction->payment_status);
        // Does NOT create duplicate sale transactions
        $this->assertDatabaseCount('transactions', 1);

        // Final repayment of 15.000 -> Paid
        $this->actingAs($admin)->post(route('debts.pay', $transaction), [
            'amount' => 15000,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $transaction->refresh();
        $this->assertSame('0.00', $transaction->outstanding_balance);
        $this->assertSame(Transaction::PAYMENT_STATUS_PAID, $transaction->payment_status);
    }

    public function test_admin_dashboard_analytics_date_filters_category_drilldown_and_weekly_insight(): void
    {
        $admin = User::factory()->administrator()->create();
        $cat = Category::create(['name' => 'Basic Goods', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'product_name' => 'Beras 5kg',
            'cost_price' => 65000,
            'price' => 74000,
            'stock' => 10,
            'unit' => 'pack',
        ]);

        $this->actingAs($admin)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'payment_mode' => 'full',
            'amount_paid' => 150000,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard', ['period' => 'week']));
        $response->assertOk()
            ->assertSee('Sales &amp; Profit Analytics', false)
            ->assertSee('Top 10 Best-Selling Products')
            ->assertSee('Weekly AI Business Summary')
            ->assertSee('Beras 5kg')
            ->assertSee('Rp 18.000'); // Gross profit = (74000 - 65000) * 2

        $this->actingAs($admin)
            ->post(route('dashboard.insight.regenerate'))
            ->assertRedirect();

        $this->actingAs($admin)->get(route('customers.index'))->assertOk();
        $this->actingAs($admin)->get(route('debts.index'))->assertOk();
    }

    public function test_customer_code_auto_generation_search_demo_seeder_and_dashboard_analytics_json(): void
    {
        $admin = User::factory()->administrator()->create();
        $cat = Category::create(['name' => 'Basic Goods', 'is_active' => true]);
        Product::factory()->create(['category_id' => $cat->id, 'product_name' => 'Indomie Goreng', 'price' => 3500, 'stock' => 50]);

        $c1 = Customer::create(['name' => 'Budi Santoso', 'phone' => '08111']);
        $c2 = Customer::create(['name' => 'Siti Rahma', 'phone' => '08222']);

        $this->assertSame('CUS-0001', $c1->customer_code);
        $this->assertSame('CUS-0002', $c2->customer_code);

        // Search by Customer ID
        $this->actingAs($admin)
            ->get(route('customers.index', ['search' => 'CUS-0002']))
            ->assertOk()
            ->assertSee('Siti Rahma')
            ->assertDontSee('Budi Santoso');

        // Run DemoCustomerDebtSeeder idempotently
        $this->seed(\Database\Seeders\DemoCustomerDebtSeeder::class);
        $this->seed(\Database\Seeders\DemoCustomerDebtSeeder::class);
        $this->assertDatabaseHas('transactions', ['transaction_code' => 'TRX-DEMO-DEBT-001', 'outstanding_balance' => 150000]);

        // Dashboard JSON auto-refresh endpoint returns debtTicker & topProducts
        $this->actingAs($admin)
            ->getJson(route('dashboard.analytics-data', ['period' => 'week']))
            ->assertOk()
            ->assertJsonStructure([
                'last_updated',
                'periodSalesFormatted',
                'periodTransactionsCount',
                'topProducts',
                'topCategories',
                'debtTicker',
            ]);
    }

    public function test_carry_forward_debt_checkout_and_fifo_settlement(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMINISTRATOR]);
        $category = \App\Models\Category::create(['name' => 'Test Carry Debt Category']);
        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'product_name' => 'Test Item 50k',
            'product_code' => 'TEST-CFD-' . uniqid(),
            'cost_price' => 30000,
            'price' => 50000,
            'stock' => 100,
            'is_active' => true,
        ]);

        // 1. Customer with no previous debt -> receipt omits previous debt section
        $cleanCustomer = \App\Models\Customer::create([
            'name' => 'Clean Customer',
            'phone' => '08110000001',
        ]);

        $this->actingAs($admin)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2, // 100,000
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $cleanCustomer->id,
            'payment_method' => 'cash',
            'amount_paid' => 100000,
        ])->assertRedirect();

        $cleanTrx = \App\Models\Transaction::where('customer_id', $cleanCustomer->id)->latest('id')->firstOrFail();
        $this->assertEquals(100000.0, (float) $cleanTrx->total_amount);
        $this->assertEquals(0.0, (float) $cleanTrx->previous_debt_amount);
        $this->assertEquals(100000.0, (float) $cleanTrx->grand_total);
        $this->assertEquals(0.0, (float) $cleanTrx->outstanding_balance);

        $this->actingAs($admin)
            ->get(route('transactions.receipt', $cleanTrx))
            ->assertOk()
            ->assertDontSee('Hutang Sebelumnya');

        // 2. Scenario A: Customer has Rp 50.000 debt, buys Rp 100.000 WITHOUT Pay Now -> debt stays 50.000
        $customerNoPayNow = \App\Models\Customer::create([
            'name' => 'No Pay Now Customer',
            'phone' => '08110000099',
        ]);
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $customerNoPayNow->id,
            'payment_method' => 'cash',
            'amount_paid' => 0,
        ])->assertRedirect();
        $this->assertEquals(50000.0, $customerNoPayNow->totalOutstandingDebt());

        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $customerNoPayNow->id,
            'payment_method' => 'cash',
            'amount_paid' => 100000,
            'include_previous_debt' => false,
        ])->assertRedirect();
        $trxNoPayNow = \App\Models\Transaction::where('customer_id', $customerNoPayNow->id)->latest('id')->firstOrFail();
        $this->assertEquals(0.0, (float) $trxNoPayNow->previous_debt_amount);
        $this->assertEquals(100000.0, (float) $trxNoPayNow->grand_total);
        $this->assertEquals(50000.0, $customerNoPayNow->totalOutstandingDebt());

        // 3. Scenario B: Customer with Rp 50.000 previous debt + Rp 100.000 purchase, clicks Pay Now, pays Rp 200.000
        $customerA = \App\Models\Customer::create([
            'name' => 'Budi Carry Overpay',
            'phone' => '08110000002',
        ]);
        $this->actingAs($admin)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1, // 50,000
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $customerA->id,
            'payment_method' => 'cash',
            'amount_paid' => 0, // full debt 50,000
        ])->assertRedirect();

        $oldDebtA = \App\Models\Transaction::where('customer_id', $customerA->id)->latest('id')->firstOrFail();
        $this->assertEquals(50000.0, (float) $oldDebtA->outstanding_balance);

        $this->actingAs($admin)->post(route('pos.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2, // 100,000
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $customerA->id,
            'payment_method' => 'cash',
            'amount_paid' => 200000,
            'include_previous_debt' => true,
        ])->assertRedirect();

        $newTrxA = \App\Models\Transaction::where('customer_id', $customerA->id)->latest('id')->firstOrFail();
        $oldDebtA->refresh();
        $this->assertEquals(100000.0, (float) $newTrxA->total_amount);
        $this->assertEquals(50000.0, (float) $newTrxA->previous_debt_amount);
        $this->assertEquals(50000.0, (float) $newTrxA->previous_debt_paid);
        $this->assertEquals(150000.0, (float) $newTrxA->grand_total);
        $this->assertEquals(200000.0, (float) $newTrxA->cash_received);
        $this->assertEquals(100000.0, (float) $newTrxA->amount_paid);
        $this->assertEquals(0.0, (float) $newTrxA->outstanding_balance);
        $this->assertEquals(50000.0, (float) $newTrxA->change_amount);
        $this->assertEquals(0.0, (float) $oldDebtA->outstanding_balance);
        $this->assertEquals(0.0, $customerA->totalOutstandingDebt());

        // Receipt & detail pages show previous debt breakdown
        $this->actingAs($admin)
            ->get(route('transactions.receipt', $newTrxA))
            ->assertOk()
            ->assertSee('Hutang Sebelumnya')
            ->assertSee('GRAND TOTAL');
        $this->actingAs($admin)
            ->get(route('transactions.show', $newTrxA))
            ->assertOk()
            ->assertSee('Previous Outstanding Debt');
        $this->actingAs($admin)
            ->get(route('customers.show', $customerA))
            ->assertOk()
            ->assertSee($newTrxA->transaction_code);

        // 3. Exact payment (50k debt + 100k purchase, pays 150k)
        $customerB = \App\Models\Customer::create(['name' => 'Exact Payer', 'phone' => '08110000003']);
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerB->id, 'payment_method' => 'cash', 'amount_paid' => 0])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerB->id, 'payment_method' => 'cash', 'amount_paid' => 150000, 'include_previous_debt' => true])->assertRedirect();
        $trxB = \App\Models\Transaction::where('customer_id', $customerB->id)->latest('id')->firstOrFail();
        $this->assertEquals(0.0, (float) $trxB->change_amount);
        $this->assertEquals(0.0, $customerB->totalOutstandingDebt());

        // 4. Partial payment covering old debt + part of current (50k debt + 100k purchase, pays 120k -> 30k debt)
        $customerC = \App\Models\Customer::create(['name' => 'Partial Payer', 'phone' => '08110000004']);
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerC->id, 'payment_method' => 'cash', 'amount_paid' => 0])->assertRedirect();
        $oldDebtC = \App\Models\Transaction::where('customer_id', $customerC->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerC->id, 'payment_method' => 'cash', 'amount_paid' => 120000, 'include_previous_debt' => true])->assertRedirect();
        $trxC = \App\Models\Transaction::where('customer_id', $customerC->id)->latest('id')->firstOrFail();
        $oldDebtC->refresh();
        $this->assertEquals(50000.0, (float) $trxC->previous_debt_paid);
        $this->assertEquals(70000.0, (float) $trxC->amount_paid);
        $this->assertEquals(30000.0, (float) $trxC->outstanding_balance);
        $this->assertEquals(0.0, (float) $oldDebtC->outstanding_balance);
        $this->assertEquals(30000.0, $customerC->totalOutstandingDebt());

        // 5. Payment less than previous debt (50k debt + 100k purchase, pays 40k -> old 10k, new 100k, total 110k)
        $customerD = \App\Models\Customer::create(['name' => 'Under Payer', 'phone' => '08110000005']);
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerD->id, 'payment_method' => 'cash', 'amount_paid' => 0])->assertRedirect();
        $oldDebtD = \App\Models\Transaction::where('customer_id', $customerD->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerD->id, 'payment_method' => 'cash', 'amount_paid' => 40000, 'include_previous_debt' => true])->assertRedirect();
        $trxD = \App\Models\Transaction::where('customer_id', $customerD->id)->latest('id')->firstOrFail();
        $oldDebtD->refresh();
        $this->assertEquals(40000.0, (float) $trxD->previous_debt_paid);
        $this->assertEquals(0.0, (float) $trxD->amount_paid);
        $this->assertEquals(100000.0, (float) $trxD->outstanding_balance);
        $this->assertEquals(10000.0, (float) $oldDebtD->outstanding_balance);
        $this->assertEquals(110000.0, $customerD->totalOutstandingDebt());

        // 6. Multiple outstanding debts FIFO allocation
        $customerE = \App\Models\Customer::create(['name' => 'FIFO Customer', 'phone' => '08110000006']);
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerE->id, 'payment_method' => 'cash', 'amount_paid' => 0])->assertRedirect();
        $fifoOld1 = \App\Models\Transaction::where('customer_id', $customerE->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerE->id, 'payment_method' => 'cash', 'amount_paid' => 0])->assertRedirect();
        $fifoOld2 = \App\Models\Transaction::where('customer_id', $customerE->id)->latest('id')->firstOrFail();

        // Now has 50k + 50k = 100k debt. Buys 50k (grand total 150k), pays 70k -> old1 fully paid (50k), old2 partially paid (20k, 30k left), newTrx 50k left.
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), ['customer_id' => $customerE->id, 'payment_method' => 'cash', 'amount_paid' => 70000, 'include_previous_debt' => true])->assertRedirect();
        $fifoNew = \App\Models\Transaction::where('customer_id', $customerE->id)->latest('id')->firstOrFail();
        $fifoOld1->refresh();
        $fifoOld2->refresh();

        $this->assertEquals(0.0, (float) $fifoOld1->outstanding_balance);
        $this->assertEquals(30000.0, (float) $fifoOld2->outstanding_balance);
        $this->assertEquals(50000.0, (float) $fifoNew->outstanding_balance);
        $this->assertEquals(80000.0, $customerE->totalOutstandingDebt());
        $this->assertDatabaseHas('debt_payments', [
            'transaction_id' => $fifoOld1->id,
            'settled_by_transaction_id' => $fifoNew->id,
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('debt_payments', [
            'transaction_id' => $fifoOld2->id,
            'settled_by_transaction_id' => $fifoNew->id,
            'amount' => 20000,
        ]);

        // 7. Dashboard revenue only counts current purchase total_amount, not carried-forward debt
        $expectedTodaySales = (float) \App\Models\Transaction::whereDate('created_at', today())
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        $dashboardRes = $this->actingAs($admin)
            ->getJson(route('dashboard.analytics-data', ['period' => 'today']))
            ->assertOk();
        $this->assertSame('Rp ' . number_format($expectedTodaySales, 0, ',', '.'), $dashboardRes->json('periodSalesFormatted'));
    }

    public function test_optional_pay_now_lookup_and_admin_remove_debt_from_pos(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMINISTRATOR]);
        $cashier = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_CASHIER]);
        $category = \App\Models\Category::create(['name' => 'WriteOff Category']);
        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'product_name' => 'WriteOff Item 50k',
            'product_code' => 'TEST-WO-' . uniqid(),
            'cost_price' => 30000,
            'price' => 50000,
            'stock' => 50,
            'is_active' => true,
        ]);

        $customer = \App\Models\Customer::create([
            'name' => 'WriteOff Customer',
            'phone' => '08110000088',
        ]);

        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'amount_paid' => 0,
        ])->assertRedirect();

        $debtTrx = \App\Models\Transaction::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(50000.0, $customer->totalOutstandingDebt());

        // Cashier can fetch customer debt for Pay Now
        $this->actingAs($cashier)
            ->getJson(route('pos.customers.debt', $customer))
            ->assertOk()
            ->assertJson([
                'customer_id' => $customer->id,
                'outstanding_debt' => 50000.0,
            ]);

        // Cashier is NOT authorized to remove/write off debt
        $this->actingAs($cashier)
            ->postJson(route('pos.customers.remove-debt', $customer), [
                'reason' => 'write_off',
                'notes' => 'Cashier attempt',
            ])
            ->assertForbidden();
        $this->assertEquals(50000.0, $customer->totalOutstandingDebt());

        // Admin removes debt with reason & notes
        $this->actingAs($admin)
            ->postJson(route('pos.customers.remove-debt', $customer), [
                'reason' => 'already_paid_outside',
                'notes' => 'Paid via bank transfer',
            ])
            ->assertOk()
            ->assertJson([
                'customer_id' => $customer->id,
                'outstanding_debt' => 0.0,
                'removed_amount' => 50000.0,
                'entry_type' => \App\Models\DebtPayment::TYPE_EXTERNAL_SETTLEMENT,
            ]);

        $debtTrx->refresh();
        $this->assertEquals(0.0, $customer->totalOutstandingDebt());
        $this->assertEquals(0.0, (float) $debtTrx->outstanding_balance);
        $this->assertEquals(50000.0, (float) $debtTrx->total_amount);
        $this->assertEquals(0.0, (float) $debtTrx->amount_paid);

        $this->assertDatabaseHas('debt_payments', [
            'transaction_id' => $debtTrx->id,
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'amount' => 50000,
            'entry_type' => \App\Models\DebtPayment::TYPE_EXTERNAL_SETTLEMENT,
            'reason' => 'already_paid_outside',
        ]);

        // Duplicate removal is prevented
        $this->actingAs($admin)
            ->postJson(route('pos.customers.remove-debt', $customer), [
                'reason' => 'write_off',
            ])
            ->assertUnprocessable();

        // Customer profile shows audit trail
        $this->actingAs($admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('External Settlement')
            ->assertSee('Paid via bank transfer');
    }

    public function test_hold_pending_and_resume_pos_transactions_all_scenarios(): void
    {
        $cashier1 = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_CASHIER]);
        $cashier2 = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_CASHIER]);
        $category = \App\Models\Category::create(['name' => 'Hold Test Category']);

        // Create 31 products for large cart & second customer scenarios
        $products = [];
        for ($i = 1; $i <= 31; $i++) {
            $products[$i] = \App\Models\Product::create([
                'category_id' => $category->id,
                'product_name' => "Hold Product {$i}",
                'product_code' => 'HOLD-P-' . $i . '-' . uniqid(),
                'cost_price' => 5000,
                'price' => 10000,
                'stock' => 20,
                'is_active' => true,
            ]);
        }

        $customerA = \App\Models\Customer::create(['name' => 'Customer A Hold', 'phone' => '08120000001']);
        $customerB = \App\Models\Customer::create(['name' => 'Customer B Quick', 'phone' => '08120000002']);

        // Scenario A: Add 30 different products to cart & hold
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($cashier1)->post(route('pos.cart.add'), [
                'product_id' => $products[$i]->id,
                'quantity' => ($i % 3) + 1,
            ])->assertRedirect();
        }

        $this->actingAs($cashier1)->postJson(route('pos.hold'), [
            'customer_id' => $customerA->id,
            'discount_amount' => 5000,
            'notes' => 'Customer A large order',
        ])->assertOk()->assertJsonPath('hold_reference', fn ($ref) => str_starts_with($ref, 'HOLD-'));

        $heldA = \App\Models\Cart::where('customer_id', $customerA->id)
            ->where('cart_status', \App\Enums\CartStatus::Pending)
            ->firstOrFail();
        $this->assertCount(30, $heldA->items);
        // Stock is NOT deducted while held
        $this->assertEquals(20.0, (float) $products[1]->fresh()->stock);

        // Scenario B: Serve Customer B while Customer A is held
        $this->actingAs($cashier1)->post(route('pos.cart.add'), [
            'product_id' => $products[31]->id,
            'quantity' => 2,
        ])->assertRedirect();
        $this->actingAs($cashier1)->post(route('pos.checkout'), [
            'customer_id' => $customerB->id,
            'payment_method' => 'cash',
            'amount_paid' => 20000,
        ])->assertRedirect();
        $this->assertEquals(18.0, (float) $products[31]->fresh()->stock);
        $this->assertEquals(\App\Enums\CartStatus::Pending, $heldA->fresh()->cart_status);

        // Scenario F: Pending list persists across requests
        $this->actingAs($cashier1)
            ->getJson(route('pos.pending.index'))
            ->assertOk()
            ->assertJsonCount(1, 'pending_transactions');

        // Scenario J (Isolation): Another cashier cannot resume or cancel cashier1's pending cart
        $this->actingAs($cashier2)
            ->postJson(route('pos.pending.resume', $heldA))
            ->assertForbidden();
        $this->actingAs($cashier2)
            ->deleteJson(route('pos.pending.cancel', $heldA))
            ->assertForbidden();

        // Scenario C: Resume Customer A's pending transaction
        $this->actingAs($cashier1)
            ->postJson(route('pos.pending.resume', $heldA))
            ->assertOk()
            ->assertJson(['conflict' => false]);

        $resumedA = $heldA->fresh(['items']);
        $this->assertEquals(\App\Enums\CartStatus::Active, $resumedA->cart_status);
        $this->assertCount(30, $resumedA->items);
        $this->assertEquals($customerA->id, $resumedA->customer_id);

        // Scenario D: Modify after resuming (add item 31, update qty, remove item 30, edit price of item 1) -> re-hold -> resume
        $this->actingAs($cashier1)->post(route('pos.cart.add'), [
            'product_id' => $products[31]->id,
            'quantity' => 1,
        ])->assertRedirect();
        $item1 = $resumedA->items->firstWhere('product_id', $products[1]->id);
        $item30 = $resumedA->items->firstWhere('product_id', $products[30]->id);
        $this->actingAs($cashier1)->patch(route('pos.cart.update', $item1), ['quantity' => 5])->assertRedirect();
        $this->actingAs($cashier1)->delete(route('pos.cart.remove', $item30))->assertRedirect();
        $this->actingAs($cashier1)->patch(route('pos.cart.update-price', $item1), [
            'price' => 8500,
            'reason' => 'Promo regular customer',
        ])->assertRedirect();

        // Change master product price while pending -> overridden/held price must stay 8500!
        $products[1]->update(['price' => 12000]);

        $this->actingAs($cashier1)->postJson(route('pos.hold'), [
            'customer_id' => $customerA->id,
            'notes' => 'Re-held after edits',
        ])->assertOk();

        $this->actingAs($cashier1)->postJson(route('pos.pending.resume', $heldA))->assertOk();
        $item1After = $heldA->fresh(['items'])->items->firstWhere('product_id', $products[1]->id);
        $this->assertEquals(5.0, (float) $item1After->quantity);
        $this->assertEquals(8500.0, (float) $item1After->price_snapshot);
        $this->assertTrue((bool) $item1After->is_price_overridden);

        // Checkout resumed cart and verify overridden price 8500 was used
        $this->actingAs($cashier1)->post(route('pos.checkout'), [
            'customer_id' => $customerA->id,
            'payment_method' => 'cash',
            'amount_paid' => 1000000,
        ])->assertRedirect();
        $trxA = \App\Models\Transaction::where('customer_id', $customerA->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('transaction_details', [
            'transaction_id' => $trxA->id,
            'product_id' => $products[1]->id,
            'quantity' => 5,
            'price' => 8500,
        ]);

        // Scenario E & J: Multiple pending carts + Active cart conflict protection
        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $products[2]->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($cashier1)->postJson(route('pos.hold'), ['notes' => 'Hold 1'])->assertOk();
        $hold1 = \App\Models\Cart::where('notes', 'Hold 1')->firstOrFail();

        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $products[3]->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($cashier1)->postJson(route('pos.hold'), ['notes' => 'Hold 2'])->assertOk();
        $hold2 = \App\Models\Cart::where('notes', 'Hold 2')->firstOrFail();

        // Active cart has item 4
        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $products[4]->id, 'quantity' => 1])->assertRedirect();

        // Attempting resume without conflict_action returns 409 and does NOT discard active cart
        $this->actingAs($cashier1)
            ->postJson(route('pos.pending.resume', $hold1))
            ->assertStatus(409)
            ->assertJson(['conflict' => true]);

        // Resuming with conflict_action = hold_current holds active cart & resumes hold1
        $this->actingAs($cashier1)
            ->postJson(route('pos.pending.resume', $hold1), [
                'conflict_action' => 'hold_current',
                'current_draft' => ['notes' => 'Auto-held from conflict'],
            ])
            ->assertOk()
            ->assertJson(['conflict' => false]);

        $this->assertDatabaseHas('carts', [
            'notes' => 'Auto-held from conflict',
            'cart_status' => \App\Enums\CartStatus::Pending->value,
        ]);
        $this->assertEquals(\App\Enums\CartStatus::Active, $hold1->fresh()->cart_status);

        // Clear active cart for next scenario
        $this->actingAs($cashier1)->delete(route('pos.cart.clear'))->assertRedirect();

        // Scenario G: Stock changes while pending -> warning on resume
        $stockProduct = \App\Models\Product::create([
            'category_id' => $category->id,
            'product_name' => 'Limited Stock Item',
            'product_code' => 'HOLD-LIM-' . uniqid(),
            'cost_price' => 5000,
            'price' => 10000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $stockProduct->id, 'quantity' => 4])->assertRedirect();
        $this->actingAs($cashier1)->postJson(route('pos.hold'), ['notes' => 'Stock check hold'])->assertOk();
        $holdStock = \App\Models\Cart::where('notes', 'Stock check hold')->firstOrFail();

        // Cashier 2 sells 3 units -> only 2 left!
        $this->actingAs($cashier2)->post(route('pos.cart.add'), ['product_id' => $stockProduct->id, 'quantity' => 3])->assertRedirect();
        $this->actingAs($cashier2)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 30000,
        ])->assertRedirect();
        $this->assertEquals(2.0, (float) $stockProduct->fresh()->stock);

        $resumeStockRes = $this->actingAs($cashier1)
            ->postJson(route('pos.pending.resume', $holdStock))
            ->assertOk();
        $this->assertNotEmpty($resumeStockRes->json('warnings'));
        // Item is NOT silently removed
        $this->assertCount(1, $holdStock->fresh(['items'])->items);
        $this->actingAs($cashier1)->delete(route('pos.cart.clear'))->assertRedirect();

        // Scenario H: Customer debt integration (Pay Now + Hold + Resume + Checkout settles once)
        $debtCustomer = \App\Models\Customer::create(['name' => 'Hold Debt Customer', 'phone' => '08120000099']);
        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $products[5]->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($cashier1)->post(route('pos.checkout'), [
            'customer_id' => $debtCustomer->id,
            'payment_method' => 'cash',
            'amount_paid' => 0, // 10,000 debt
        ])->assertRedirect();
        $this->assertEquals(10000.0, $debtCustomer->totalOutstandingDebt());

        $this->actingAs($cashier1)->post(route('pos.cart.add'), ['product_id' => $products[6]->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($cashier1)->postJson(route('pos.hold'), [
            'customer_id' => $debtCustomer->id,
            'include_previous_debt' => true,
        ])->assertOk();
        $holdDebt = \App\Models\Cart::where('customer_id', $debtCustomer->id)
            ->where('cart_status', \App\Enums\CartStatus::Pending)
            ->firstOrFail();
        // Debt is NOT settled while held
        $this->assertEquals(10000.0, $debtCustomer->totalOutstandingDebt());

        $this->actingAs($cashier1)->postJson(route('pos.pending.resume', $holdDebt))->assertOk();
        // Still NOT settled on resume
        $this->assertEquals(10000.0, $debtCustomer->totalOutstandingDebt());
        $this->assertTrue((bool) session('pos_checkout_draft.include_previous_debt'));

        $this->actingAs($cashier1)->post(route('pos.checkout'), [
            'customer_id' => $debtCustomer->id,
            'payment_method' => 'cash',
            'amount_paid' => 20000,
            'include_previous_debt' => true,
        ])->assertRedirect();
        $this->assertEquals(0.0, $debtCustomer->totalOutstandingDebt());

        // Scenario I: Cancel pending transaction -> no sale, no stock deduction, no debt settlement
        $stockBeforeCancel = (float) $products[7]->fresh()->stock;
        $trxCountBefore = \App\Models\Transaction::count();
        $this->actingAs($cashier1)->deleteJson(route('pos.pending.cancel', $hold2))->assertOk();
        $this->assertEquals(\App\Enums\CartStatus::Cancelled, $hold2->fresh()->cart_status);
        $this->assertEquals($stockBeforeCancel, (float) $products[7]->fresh()->stock);
        $this->assertEquals($trxCountBefore, \App\Models\Transaction::count());
    }

    public function test_optional_staff_selection_during_checkout_and_history(): void
    {
        $admin = \App\Models\User::factory()->create(['name' => 'Admin User', 'role' => \App\Models\User::ROLE_ADMINISTRATOR]);
        $cashier = \App\Models\User::factory()->create(['name' => 'Cashier User', 'role' => \App\Models\User::ROLE_CASHIER]);
        $category = \App\Models\Category::create(['name' => 'Staff Test Category']);
        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'product_name' => 'Staff Test Item',
            'product_code' => 'STF-ITEM-' . uniqid(),
            'cost_price' => 10000,
            'price' => 25000,
            'stock' => 50,
            'is_active' => true,
        ]);

        // Admin creates staff via Staff Management; cashier is forbidden
        $this->actingAs($cashier)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('staff.store'), ['name' => 'Andi', 'phone' => '0811111', 'is_active' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('staff.store'), ['name' => 'Joko', 'phone' => '0822222', 'is_active' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('staff.store'), ['name' => 'Inactive Staff', 'is_active' => 0])->assertRedirect();

        $andi = \App\Models\Staff::where('name', 'Andi')->firstOrFail();
        $joko = \App\Models\Staff::where('name', 'Joko')->firstOrFail();
        $inactive = \App\Models\Staff::where('name', 'Inactive Staff')->firstOrFail();

        $this->assertStringStartsWith('STF-', $andi->staff_code);

        // Main POS page does NOT show Taken By field
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertDontSee('Taken By (Optional)');

        // Checkout page DOES show Taken By (Optional) with active staff only
        $this->actingAs($admin)
            ->get(route('pos.checkout.review'))
            ->assertOk()
            ->assertSee('Taken By (Optional)')
            ->assertSee('Andi')
            ->assertSee('Joko')
            ->assertDontSee('Inactive Staff');

        // Inactive staff cannot be selected on checkout
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 25000,
            'taken_by_staff_id' => $inactive->id,
        ])->assertSessionHasErrors('taken_by_staff_id');

        // Scenario A & E: Select Andi during checkout; Cashier remains Admin User
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 25000,
            'taken_by_staff_id' => $andi->id,
        ])->assertRedirect();

        $trxAndi = \App\Models\Transaction::latest('id')->firstOrFail();
        $this->assertEquals($admin->id, $trxAndi->user_id);
        $this->assertEquals($andi->id, $trxAndi->taken_by_staff_id);
        $this->assertEquals('Andi', $trxAndi->taken_by_staff_name_snapshot);

        $this->actingAs($admin)
            ->get(route('transactions.show', $trxAndi))
            ->assertOk()
            ->assertSee('Admin User')
            ->assertSee("Andi — {$andi->staff_code}");

        $this->actingAs($admin)
            ->get(route('transactions.receipt', $trxAndi))
            ->assertOk()
            ->assertSee('Taken By')
            ->assertSee('Andi');

        // Scenario B: Leave Taken By empty -> Not Assigned on detail, omitted on receipt
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 25000,
            'taken_by_staff_id' => '',
        ])->assertRedirect();

        $trxEmpty = \App\Models\Transaction::latest('id')->firstOrFail();
        $this->assertNull($trxEmpty->taken_by_staff_id);
        $this->actingAs($admin)
            ->get(route('transactions.show', $trxEmpty))
            ->assertOk()
            ->assertSee('Not Assigned');
        $this->actingAs($admin)
            ->get(route('transactions.receipt', $trxEmpty))
            ->assertOk()
            ->assertDontSee('Taken By');

        // Scenario F: Pending transaction -> hold -> resume -> select Joko at checkout
        $this->actingAs($admin)->post(route('pos.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($admin)->postJson(route('pos.hold'), ['notes' => 'Pending for Joko'])->assertOk();
        $held = \App\Models\Cart::where('notes', 'Pending for Joko')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pos.pending.resume', $held))->assertOk();

        $this->actingAs($admin)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_paid' => 50000,
            'taken_by_staff_id' => $joko->id,
        ])->assertRedirect();
        $trxJoko = \App\Models\Transaction::latest('id')->firstOrFail();
        $this->assertEquals($joko->id, $trxJoko->taken_by_staff_id);
        $this->assertEquals('Joko', $trxJoko->taken_by_staff_name_snapshot);

        // Scenario G: Deactivate Andi -> historical transaction & receipt still show Andi
        $this->actingAs($admin)->patch(route('staff.toggle-active', $andi))->assertRedirect();
        $this->assertFalse($andi->fresh()->is_active);
        $this->actingAs($admin)
            ->get(route('transactions.show', $trxAndi))
            ->assertOk()
            ->assertSee("Andi — {$andi->staff_code}");
        $this->actingAs($admin)
            ->get(route('transactions.receipt', $trxAndi))
            ->assertOk()
            ->assertSee('Andi');

        // Scenario H: Transaction History filter by Taken By
        $this->actingAs($admin)
            ->get(route('transactions.index', ['taken_by' => $joko->id]))
            ->assertOk()
            ->assertSee($trxJoko->transaction_code)
            ->assertDontSee($trxAndi->transaction_code);

        $this->actingAs($admin)
            ->get(route('transactions.index', ['taken_by' => 'unassigned']))
            ->assertOk()
            ->assertSee($trxEmpty->transaction_code)
            ->assertDontSee($trxJoko->transaction_code);
    }
}
