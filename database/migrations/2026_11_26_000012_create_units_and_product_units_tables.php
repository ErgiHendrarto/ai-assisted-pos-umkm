<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $defaultUnits = [
            'pcs', 'piece', 'stick', 'pack', 'box', 'carton',
            'can', 'bottle', 'bundle', 'slop', 'ball',
            'kg', 'gram', 'liter', 'sachet', 'pouch', 'galon', 'kotak', 'sak',
        ];

        $now = now();
        foreach ($defaultUnits as $unit) {
            DB::table('units')->insertOrIgnore([
                'name' => $unit,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->default(0)->after('description');
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('unit_name', 50);
            $table->decimal('conversion_ratio', 12, 3)->default(1);
            $table->decimal('selling_price', 12, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_name'], 'product_units_product_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
        Schema::dropIfExists('units');
    }
};
