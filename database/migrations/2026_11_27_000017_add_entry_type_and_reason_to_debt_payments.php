<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_payments', function (Blueprint $table): void {
            $table->string('entry_type', 32)->default('repayment')->after('payment_method');
            $table->string('reason', 64)->nullable()->after('entry_type');
        });
    }

    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table): void {
            $table->dropColumn(['entry_type', 'reason']);
        });
    }
};
