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
        Schema::create('ai_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_input_id')
                ->constrained('order_inputs')
                ->cascadeOnDelete();
            $table->string('processing_type', 40)->index();
            $table->string('service_name', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('prompt_version', 50)->nullable();
            $table->text('recognized_text')->nullable();
            $table->json('structured_output')->nullable();
            $table->string('processing_status', 30)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('processing_duration_ms')->nullable();
            $table->timestamps();

            $table->index(['order_input_id', 'processing_type'], 'ai_runs_input_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_processing_runs');
    }
};
