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
        Schema::create('order_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();
            $table->string('input_type', 30)->index();
            $table->text('original_text')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('processing_status', 30)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'input_type', 'processing_status'], 'order_inputs_user_type_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_inputs');
    }
};
