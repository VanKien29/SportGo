<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Tên bậc phí.');
            $table->unsignedInteger('min_courts')->comment('Số sân con tối thiểu.');
            $table->unsignedInteger('max_courts')->nullable()->comment('Số sân con tối đa; null = không giới hạn.');
            $table->decimal('price_per_court_month', 12, 2)->default(0.00)->comment('Giá/sân/tháng.');
            $table->decimal('annual_discount_percent', 5, 2)->default(0.00)->comment('Phần trăm giảm khi đóng theo năm.');
            $table->boolean('is_active')->default(true)->comment('Bậc phí còn áp dụng.');
            $table->timestamp('effective_from')->nullable()->comment('Thời điểm bắt đầu hiệu lực.');
            $table->timestamps();
            $table->index('min_courts', 'platform_fee_tiers_min_courts_index');
            $table->index('max_courts', 'platform_fee_tiers_max_courts_index');
            $table->index('is_active', 'platform_fee_tiers_is_active_index');
            $table->index('effective_from', 'platform_fee_tiers_effective_from_index');
        });
    }
    public function down(): void { Schema::dropIfExists('platform_fee_tiers'); }
};
