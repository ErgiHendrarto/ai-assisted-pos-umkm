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
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();

            $table->string('product_name_snapshot', 150)->default('')->after('product_id');
            $table->string('product_code_snapshot', 50)->nullable()->after('product_name_snapshot');
            $table->decimal('quantity', 12, 3)->change();
            $table->string('unit', 30)->default('pcs')->after('quantity');
        });

        // Backfill snapshot columns from existing products for any pre-existing details.
        $details = DB::table('transaction_details')
            ->whereNotNull('product_id')
            ->where('product_name_snapshot', '')
            ->get(['id', 'product_id']);

        if ($details->isNotEmpty()) {
            $products = DB::table('products')
                ->whereIn('id', $details->pluck('product_id')->unique())
                ->get(['id', 'product_name', 'product_code', 'unit'])
                ->keyBy('id');

            foreach ($details as $detail) {
                $product = $products->get($detail->product_id);
                if ($product) {
                    DB::table('transaction_details')
                        ->where('id', $detail->id)
                        ->update([
                            'product_name_snapshot' => $product->product_name,
                            'product_code_snapshot' => $product->product_code,
                            'unit' => $product->unit,
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn([
                'product_name_snapshot',
                'product_code_snapshot',
                'unit',
            ]);
            $table->unsignedInteger('quantity')->change();
        });
    }
};
