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
        Schema::create('venue_platform_fee_ledgers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân bị tính phí nền tảng.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('tier_id')->nullable()->comment('Bậc phí áp dụng tại thời điểm tạo ledger.; VD: 10000000-0000-0000-0000-000000000001');
            $table->integer('court_count')->comment('Snapshot số sân con tại kỳ tính phí.; VD: 60');
            $table->string('billing_cycle')->comment('Chu kỳ tính phí: monthly hoặc yearly. Giá trị enum: monthly=giá trị nghiệp vụ; yearly=giá trị nghiệp vụ.; VD: giá trị mẫu');
            $table->date('period_start')->comment('Ngày bắt đầu kỳ phí.; VD: 2026-06-15');
            $table->date('period_end')->comment('Ngày kết thúc kỳ phí.; VD: 2026-06-15');
            $table->decimal('price_per_court_month', 12, 2)->comment('Snapshot giá/sân/tháng tại kỳ phí.; VD: 120000.00');
            $table->decimal('discount_percent', 5, 2)->comment('Snapshot phần trăm giảm giá theo chu kỳ.; VD: 60');
            $table->decimal('amount_due', 12, 2)->comment('Số tiền phải thu của kỳ phí.; VD: 120000.00');
            $table->decimal('amount_paid', 12, 2)->comment('Số tiền đã thanh toán cho kỳ phí.; VD: 120000.00');
            $table->string('status')->comment('Trạng thái phí: pending, paid, overdue, cancelled. Giá trị enum: pending=chờ xử lý; paid=đã thanh toán; overdue=giá trị nghiệp vụ; cancelled=đã hủy.; VD: confirmed');
            $table->timestamp('paid_at')->nullable()->comment('Thời điểm chủ sân thanh toán phí.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['venue_cluster_id', 'status'], 'venue_platform_fee_ledgers_venue_cluster_id_status_index');
            $table->foreign('tier_id')->references('id')->on('platform_fee_tiers')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_platform_fee_ledgers');
    }
};
