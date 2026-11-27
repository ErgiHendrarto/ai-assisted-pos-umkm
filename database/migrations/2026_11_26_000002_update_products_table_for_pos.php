<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('product_name');
            $table->decimal('stock', 12, 3)->default(0)->change();
            $table->decimal('minimum_stock', 12, 3)->default(5)->change();
            $table->string('image_path', 500)->nullable()->after('unit');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['description', 'image_path']);
            $table->unsignedInteger('stock')->default(0)->change();
            $table->unsignedInteger('minimum_stock')->default(5)->change();
        });
    }
};
