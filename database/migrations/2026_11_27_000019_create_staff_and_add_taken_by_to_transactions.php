<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->string('staff_code', 32)->unique();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('taken_by_staff_id')->nullable()->after('customer_id')->constrained('staff')->nullOnDelete();
            $table->string('taken_by_staff_name_snapshot', 150)->nullable()->after('taken_by_staff_id');
            $table->string('taken_by_staff_code_snapshot', 32)->nullable()->after('taken_by_staff_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('taken_by_staff_id');
            $table->dropColumn([
                'taken_by_staff_name_snapshot',
                'taken_by_staff_code_snapshot',
            ]);
        });

        Schema::dropIfExists('staff');
    }
};
