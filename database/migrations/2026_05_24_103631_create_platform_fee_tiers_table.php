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
        Schema::create('platform_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Tên bậc phí như 1-3, 4-7, 8-11, >11.; VD: Sân Cầu Lông A1');
            $table->integer('min_courts')->comment('Số sân con tối thiểu để áp dụng bậc phí.; VD: 1');
            $table->integer('max_courts')->nullable()->comment('Số sân con tối đa; null nghĩa là lớn hơn không giới hạn.; VD: 1');
            $table->decimal('price_per_court_month', 12, 2)->comment('Giá tính trên mỗi sân con mỗi tháng.; VD: 120000.00');
            $table->decimal('annual_discount_percent', 5, 2)->comment('Phần trăm giảm khi đóng theo năm.; VD: 60');
            $table->boolean('is_active')->comment('Bậc phí còn áp dụng hay không.; VD: true');
            $table->timestamp('effective_from')->nullable()->comment('Thời điểm bậc phí bắt đầu có hiệu lực.; VD: 18:00:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fee_tiers');
    }
};
