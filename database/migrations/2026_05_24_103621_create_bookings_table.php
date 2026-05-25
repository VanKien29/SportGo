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
        Schema::create('bookings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('booking_code', 30)->unique()->comment('Mã booking dễ đọc để user/chủ sân tra cứu.; VD: BK-20260615-0001');
            $table->char('customer_id', 36)->nullable()->comment('User đặt online; nullable vì booking tại quầy không bắt buộc tài khoản.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_court_id', 36)->comment('Sân con thực tế được gán cho buổi chơi; chủ sân có thể đổi sân ở bước xác nhận nếu hợp lệ.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('requested_venue_court_id', 36)->nullable()->comment('Sân con khách yêu cầu ban đầu; dùng để giữ lịch sử khi chủ sân đổi sang sân khác.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân denormalized từ venue_courts để lọc booking/dashboard nhanh, không FK vật lý.; VD: 10000000-0000-0000-0000-000000000001');
            $table->date('booking_date')->comment('Ngày chơi.; VD: 2026-06-15');
            $table->time('start_time')->comment('Giờ bắt đầu booking.; VD: 18:00:00');
            $table->time('end_time')->comment('Giờ kết thúc booking; dùng rule chống overlap.; VD: 20:00:00');
            $table->integer('duration_minutes')->comment('Tổng thời lượng đặt sân tính bằng phút.; VD: 60');
            $table->decimal('total_price', 12, 2)->comment('Tổng tiền booking trước thanh toán/hoàn tiền.; VD: 60');
            $table->string('payment_option')->comment('Kiểu thanh toán user chọn: full_payment, deposit hoặc no_prepay. Giá trị enum: full_payment=thanh toán hết; deposit=đặt cọc; no_prepay=không thanh toán trước.; VD: deposit');
            $table->decimal('required_payment_amount', 12, 2)->comment('Số tiền tối thiểu cần thanh toán để giữ/xác nhận theo option.; VD: 120000.00');
            $table->string('source')->comment('Nguồn booking: online từ app hoặc counter tại quầy. Giá trị enum: online=đặt online; counter=đặt tại quầy.; VD: online');
            $table->string('booking_type')->comment('Kiểu booking: single=đặt lẻ; recurring=đặt cố định/lặp lại. Không dùng field này để phân biệt online hay tại quầy.; VD: recurring');
            $table->string('recurring_group_code', 30)->nullable()->comment('Mã nhóm đơn đặt cố định; các booking con cùng đơn cố định dùng chung mã này.; VD: RC-202606-0001');
            $table->date('recurring_start_date')->nullable()->comment('Ngày bắt đầu của rule đặt cố định; lặp lại ở từng booking con để không cần bảng cha.; VD: 2026-06-15');
            $table->date('recurring_end_date')->nullable()->comment('Ngày kết thúc của rule đặt cố định.; VD: 2026-06-15');
            $table->string('recurrence_type')->nullable()->comment('Kiểu lặp của đơn cố định: daily=theo ngày; weekly=theo tuần; monthly=theo tháng.; VD: weekly');
            $table->integer('recurrence_interval')->nullable()->comment('Khoảng lặp theo recurrence_type; ví dụ weekly + 2 là 2 tuần/lần.; VD: 1');
            $table->json('recurrence_days_of_week')->nullable();
            $table->json('recurrence_days_of_month')->nullable();
            $table->string('status')->comment('Trạng thái booking: pending_approval, pending_payment, confirmed, checked_in, completed, cancelled, expired, rejected. Giá trị enum: pending_approval=chờ duyệt; pending_payment=chờ thanh toán; confirmed=đã xác nhận; checked_in=đã check-in; completed=hoàn thành; cancelled=đã hủy; expired=hết hạn; rejected=bị từ chối.; VD: confirmed');
            $table->string('walk_in_name', 255)->nullable()->comment('Tên khách tại quầy khi customer_id null.; VD: Sân Cầu Lông A1');
            $table->string('walk_in_phone', 20)->nullable()->comment('Số điện thoại khách tại quầy khi customer_id null.; VD: 0987654321');
            $table->text('status_reason')->nullable()->comment('Lý do hủy/từ chối/hết hiệu lực booking.; VD: Nội dung mẫu dùng để demo.');
            $table->char('cancelled_by', 36)->nullable()->comment('User/admin/chủ sân thực hiện hủy booking.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('cancelled_at')->nullable()->comment('Thời điểm booking bị hủy.; VD: 2026-06-15 18:00:00');
            $table->char('created_by', 36)->nullable()->comment('Người tạo booking: user online, chủ sân hoặc nhân viên quầy.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('court_changed_by', 36)->nullable()->comment('Chủ sân/nhân viên đổi sân thực tế so với sân khách yêu cầu.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('court_changed_at')->nullable()->comment('Thời điểm đổi sân con cho booking.; VD: 2026-06-15 18:00:00');
            $table->text('court_changed_reason')->nullable()->comment('Lý do đổi sân, ví dụ sân khách chọn bận hoặc bảo trì.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('reminder_sent_at')->nullable()->comment('Thời điểm hệ thống đã gửi nhắc lịch 30 phút trước giờ chơi.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['venue_court_id', 'booking_date', 'start_time', 'end_time'], 'bookings_availability_index');
            $table->index(['customer_id', 'created_at'], 'bookings_customer_id_created_at_index');
            $table->index(['venue_cluster_id', 'booking_date', 'status'], 'bookings_venue_cluster_id_booking_date_status_index');
            $table->index(['venue_court_id', 'booking_date', 'status'], 'bookings_venue_court_id_booking_date_status_index');
            $table->index(['booking_type', 'recurring_group_code'], 'bookings_type_group_index');
            $table->index(['recurring_group_code', 'booking_date'], 'bookings_group_date_index');
            $table->index(['requested_venue_court_id', 'booking_date'], 'bookings_requested_court_date_index');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('court_changed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('requested_venue_court_id')->references('id')->on('venue_courts')->onDelete('set null');
            $table->foreign('venue_court_id')->references('id')->on('venue_courts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
