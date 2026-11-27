<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_days')->default(2)->after('minimum_stock');
            $table->decimal('safety_stock', 12, 3)->default(5)->after('lead_time_days');
        });

        Schema::create('product_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('alternative_product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'alternative_product_id'], 'product_alternatives_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_alternatives');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['lead_time_days', 'safety_stock']);
        });
    }
};
