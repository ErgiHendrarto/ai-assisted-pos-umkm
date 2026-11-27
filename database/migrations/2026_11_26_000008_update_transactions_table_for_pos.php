<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cart_id')
                ->nullable()
                ->after('user_id')
                ->constrained('carts')
                ->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0)->after('cart_id');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('discount_amount');
            $table->decimal('amount_paid', 14, 2)->default(0)->after('total_amount');
            $table->decimal('change_amount', 14, 2)->default(0)->after('amount_paid');
            $table->timestamp('transaction_date')->nullable()->after('transaction_status')->index();
            $table->text('notes')->nullable()->after('transaction_date');
        });

        // Backfill existing transaction rows safely without losing historical timestamps.
        DB::table('transactions')->whereNull('transaction_date')->update([
            'subtotal' => DB::raw('total_amount'),
            'amount_paid' => DB::raw('total_amount'),
            'transaction_date' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropColumn([
                'cart_id',
                'subtotal',
                'discount_amount',
                'tax_amount',
                'amount_paid',
                'change_amount',
                'transaction_date',
                'notes',
            ]);
        });
    }
};
