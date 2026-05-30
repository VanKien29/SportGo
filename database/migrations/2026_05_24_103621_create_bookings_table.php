<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('booking_code', 30)->unique()->comment('Mã booking dễ đọc để user/chủ sân tra cứu.');
            $table->char('customer_id', 36)->nullable()->comment('User đặt online; nullable vì booking tại quầy không bắt buộc tài khoản.');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân denormalized từ venue_courts để lọc booking/dashboard nhanh.');
            $table->date('booking_date')->comment('Ngày chơi.');
            $table->decimal('total_price', 12, 2)->default(0.00)->comment('Tổng tiền = SUM(booking_items.subtotal).');
            $table->enum('payment_option', ['full_payment', 'deposit', 'no_prepay'])->default('no_prepay')->comment('Kiểu thanh toán user chọn.');
            $table->decimal('required_payment_amount', 12, 2)->default(0.00)->comment('Số tiền tối thiểu cần thanh toán.');
            $table->enum('source', ['online', 'counter'])->default('online')->comment('Nguồn booking: online hoặc counter.');
            $table->enum('booking_type', ['single', 'recurring'])->default('single')->comment('Kiểu booking: single=đặt lẻ; recurring=đặt cố định.');
            $table->string('recurring_group_code', 30)->nullable()->comment('Mã nhóm đơn đặt cố định.');
            $table->date('recurring_start_date')->nullable()->comment('Ngày bắt đầu của rule đặt cố định.');
            $table->date('recurring_end_date')->nullable()->comment('Ngày kết thúc của rule đặt cố định.');
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])->nullable()->comment('Kiểu lặp của đơn cố định.');
            $table->unsignedInteger('recurrence_interval')->nullable()->comment('Khoảng lặp theo recurrence_type.');
            $table->json('recurrence_days_of_week')->nullable();
            $table->json('recurrence_days_of_month')->nullable();
            $table->enum('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired', 'rejected'])->default('pending_approval')->comment('Trạng thái booking.');
            $table->string('walk_in_name', 255)->nullable()->comment('Tên khách tại quầy khi customer_id null.');
            $table->string('walk_in_phone', 20)->nullable()->comment('Số điện thoại khách tại quầy.');
            $table->text('status_reason')->nullable()->comment('Lý do hủy/từ chối/hết hiệu lực booking.');
            $table->char('cancelled_by', 36)->nullable()->comment('User/admin/chủ sân thực hiện hủy booking.');
            $table->timestamp('cancelled_at')->nullable()->comment('Thời điểm booking bị hủy.');
            $table->char('created_by', 36)->nullable()->comment('Người tạo booking.');
            $table->timestamp('reminder_sent_at')->nullable()->comment('Thời điểm hệ thống đã gửi nhắc lịch.');
            $table->timestamps();
            $table->index(['customer_id', 'created_at'], 'bookings_customer_id_created_at_index');
            $table->index(['venue_cluster_id', 'booking_date', 'status'], 'bookings_venue_cluster_id_booking_date_status_index');
            $table->index(['booking_type', 'recurring_group_code'], 'bookings_type_group_index');
            $table->index(['recurring_group_code', 'booking_date'], 'bookings_group_date_index');
            $table->index('booking_date', 'bookings_booking_date_index');
            $table->index('status', 'bookings_status_index');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
