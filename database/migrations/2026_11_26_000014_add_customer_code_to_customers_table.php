<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 30)->nullable()->after('id');
        });

        // Backfill existing customers safely with sequential CUS-0001 codes
        $existingIds = DB::table('customers')->orderBy('id')->pluck('id');
        $seq = 1;
        foreach ($existingIds as $id) {
            DB::table('customers')
                ->where('id', $id)
                ->update(['customer_code' => sprintf('CUS-%04d', $seq++)]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 30)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['customer_code']);
            $table->dropColumn('customer_code');
        });
    }
};
