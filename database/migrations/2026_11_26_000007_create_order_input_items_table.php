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
        Schema::create('order_input_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_processing_run_id')
                ->constrained('ai_processing_runs')
                ->cascadeOnDelete();
            $table->string('requested_product_name', 200);
            $table->decimal('requested_quantity', 12, 3);
            $table->string('requested_unit', 30)->nullable();
            $table->foreignId('matched_product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->decimal('match_confidence', 5, 4)->nullable();
            $table->string('match_status', 30)->default('unmatched')->index();
            $table->foreignId('confirmed_product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->decimal('confirmed_quantity', 12, 3)->nullable();
            $table->string('review_status', 30)->default('pending')->index();
            $table->timestamps();

            $table->index(['ai_processing_run_id', 'review_status'], 'order_input_items_run_review_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_input_items');
    }
};
