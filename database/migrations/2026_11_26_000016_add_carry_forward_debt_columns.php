<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('previous_debt_amount', 14, 2)->default(0)->after('total_amount');
            $table->decimal('previous_debt_paid', 14, 2)->default(0)->after('previous_debt_amount');
            $table->decimal('grand_total', 14, 2)->default(0)->after('previous_debt_paid');
            $table->decimal('cash_received', 14, 2)->default(0)->after('grand_total');
        });

        DB::table('transactions')->update([
            'grand_total' => DB::raw('total_amount'),
            'cash_received' => DB::raw('amount_paid'),
        ]);

        Schema::table('debt_payments', function (Blueprint $table) {
            $table->foreignId('settled_by_transaction_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table) {
            $table->dropForeign(['settled_by_transaction_id']);
            $table->dropColumn('settled_by_transaction_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['previous_debt_amount', 'previous_debt_paid', 'grand_total', 'cash_received']);
        });
    }
};
