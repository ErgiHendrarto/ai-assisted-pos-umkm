<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 40)->nullable()->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_unit_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_units')
                ->nullOnDelete();
            $table->string('unit_name', 50)->default('pcs')->after('product_unit_id');
            $table->decimal('conversion_ratio', 12, 3)->default(1)->after('unit_name');
        });

        // Allow same product in different selling units in one cart (e.g., 1 slop + 2 packs)
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropForeign(['product_id']);
            $table->dropUnique('cart_items_cart_product_unique');
            $table->unique(['cart_id', 'product_id', 'unit_name'], 'cart_items_cart_product_unit_unique');
            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('payment_status', 30)->default('paid')->after('payment_method')->index();
            $table->decimal('outstanding_balance', 14, 2)->default(0)->after('change_amount');
        });

        DB::table('transactions')
            ->where('transaction_status', 'completed')
            ->update([
                'payment_status' => 'paid',
                'outstanding_balance' => 0,
            ]);

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->foreignId('product_unit_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_units')
                ->nullOnDelete();
            $table->decimal('conversion_ratio', 12, 3)->default(1)->after('unit');
            $table->decimal('base_quantity', 12, 3)->default(0)->after('conversion_ratio');
            $table->decimal('cost_price_snapshot', 12, 2)->default(0)->after('price');
            $table->decimal('profit_snapshot', 14, 2)->default(0)->after('subtotal');
        });

        DB::table('transaction_details')->update([
            'base_quantity' => DB::raw('quantity'),
        ]);

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->timestamp('payment_date')->useCurrent()->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'payment_date'], 'debt_payments_customer_date_idx');
        });

        Schema::create('weekly_insights', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->index();
            $table->date('week_end');
            $table->string('source', 30)->default('rule_based');
            $table->text('summary_text');
            $table->json('observed_metrics')->nullable();
            $table->json('recommendations')->nullable();
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_insights');
        Schema::dropIfExists('debt_payments');
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'conversion_ratio', 'base_quantity', 'cost_price_snapshot', 'profit_snapshot']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'payment_status', 'outstanding_balance']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'unit_name', 'conversion_ratio']);
        });
        Schema::dropIfExists('customers');
    }
};
