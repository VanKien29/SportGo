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
        Schema::create('booking_configs', function (Blueprint $table) {
            $table->char('venue_cluster_id', 36)->primary();
            $table->integer('min_duration_minutes')->comment('Thời lượng đặt sân tối thiểu, chốt mặc định 30 phút.; VD: 60');
            $table->integer('max_duration_minutes')->nullable()->comment('Thời lượng đặt sân tối đa; null nghĩa là không giới hạn theo cấu hình.; VD: 60');
            $table->integer('slot_hold_minutes')->comment('Số phút giữ slot tạm khi user đang tạo booking/thanh toán, chốt 20 phút.; VD: 60');
            $table->integer('reminder_before_minutes')->comment('Số phút nhắc lịch trước giờ chơi, chốt 30 phút.; VD: 60');
            $table->boolean('allow_full_payment')->comment('Cụm sân có cho thanh toán hết trước hay không.; VD: true');
            $table->boolean('allow_deposit')->comment('Cụm sân có cho đặt cọc hay không.; VD: true');
            $table->boolean('allow_no_prepay')->comment('Cụm sân có cho đặt không thanh toán trước hay không.; VD: true');
            $table->boolean('auto_approve_full_payment')->comment('Nếu bật, booking thanh toán hết có thể tự xác nhận; nếu tắt vẫn chờ chủ sân duyệt.; VD: true');
            $table->decimal('deposit_percent', 5, 2)->nullable()->comment('Tỷ lệ đặt cọc tham khảo để tính required_payment_amount.; VD: 120000.00');
            $table->integer('cancel_before_hours')->comment('Số giờ tối thiểu trước giờ chơi để user được hủy theo chính sách.; VD: 60');
            $table->integer('refund_percent')->comment('Tỷ lệ hoàn tiền theo cấu hình cụm sân/chính sách.; VD: 120000.00');
            $table->timestamps();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_configs');
    }
};
