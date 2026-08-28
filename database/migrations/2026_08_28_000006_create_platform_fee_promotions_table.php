<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('status', 30)->default('draft');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 14, 2);
            $table->decimal('max_discount_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('duration_cycles')->default(1);
            $table->boolean('applies_to_all_clusters')->default(false);
            $table->boolean('stackable_with_prepay')->default(false);
            $table->decimal('budget_amount', 16, 2)->nullable();
            $table->decimal('spent_amount', 16, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at'], 'pf_promotions_status_dates_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_promotions');
    }
};
