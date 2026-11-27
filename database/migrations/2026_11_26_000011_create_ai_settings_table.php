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
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('gemini');
            $table->text('api_key')->nullable();
            $table->string('model_name', 100)->default('gemini-2.5-flash');
            $table->string('base_url', 500)->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->boolean('voice_enabled')->default(true);
            $table->boolean('ocr_enabled')->default(true);
            $table->boolean('matching_enabled')->default(true);
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
