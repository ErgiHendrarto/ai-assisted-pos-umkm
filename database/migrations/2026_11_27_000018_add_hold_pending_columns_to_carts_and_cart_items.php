<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->string('hold_reference', 32)->nullable()->unique()->after('cart_status');
            $table->foreignId('customer_id')->nullable()->after('hold_reference')->constrained('customers')->nullOnDelete();
            $table->string('customer_name_snapshot', 255)->nullable()->after('customer_id');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('subtotal');
            $table->string('payment_method', 30)->nullable()->default('cash')->after('total');
            $table->decimal('amount_paid', 15, 2)->nullable()->after('payment_method');
            $table->boolean('include_previous_debt')->default(false)->after('amount_paid');
            $table->decimal('previous_debt_snapshot', 15, 2)->default(0)->after('include_previous_debt');
            $table->text('notes')->nullable()->after('previous_debt_snapshot');
            $table->timestamp('held_at')->nullable()->after('notes');
            $table->timestamp('resumed_at')->nullable()->after('held_at');
            $table->timestamp('cancelled_at')->nullable()->after('resumed_at');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('product_name_snapshot', 255)->nullable()->after('product_id');
            $table->string('product_code_snapshot', 100)->nullable()->after('product_name_snapshot');
            $table->decimal('original_price', 15, 2)->nullable()->after('quantity');
            $table->boolean('is_price_overridden')->default(false)->after('price_snapshot');
            $table->string('price_override_reason', 255)->nullable()->after('is_price_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn([
                'product_name_snapshot',
                'product_code_snapshot',
                'original_price',
                'is_price_overridden',
                'price_override_reason',
            ]);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn([
                'hold_reference',
                'customer_name_snapshot',
                'discount_amount',
                'payment_method',
                'amount_paid',
                'include_previous_debt',
                'previous_debt_snapshot',
                'notes',
                'held_at',
                'resumed_at',
                'cancelled_at',
            ]);
        });
    }
};
