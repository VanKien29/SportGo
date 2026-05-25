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
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('User gửi hồ sơ đăng ký làm chủ sân.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('business_name', 255)->comment('Tên đơn vị/cá nhân kinh doanh sân.; VD: Sân Cầu Lông A1');
            $table->string('tax_code', 50)->nullable()->comment('Mã số thuế nếu có.; VD: SPORTGO_CODE_001');
            $table->string('venue_name', 255)->comment('Tên cụm sân dự kiến tạo khi duyệt hồ sơ.; VD: Sân Cầu Lông A1');
            $table->text('venue_address')->comment('Địa chỉ cụm sân nhập trong form đăng ký.; VD: 123 Nguyễn Trãi, Hà Nội');
            $table->string('venue_map_url', 1000)->nullable()->comment('Link Google Maps user dán trong form; BE/FE có thể dùng để hỗ trợ lấy tọa độ.; VD: uploads/demo/san-a1.jpg');
            $table->decimal('venue_latitude', 10, 7)->comment('Vĩ độ cụm sân đã chốt để lọc sân gần người dùng.; VD: 21.027800');
            $table->decimal('venue_longitude', 10, 7)->comment('Kinh độ cụm sân đã chốt để lọc sân gần người dùng.; VD: 105.834200');
            $table->string('status')->comment('Trạng thái hồ sơ: pending, reviewing, approved, rejected, cancelled. Giá trị enum: pending=chờ xử lý; reviewing=đang xem xét; approved=đã duyệt; rejected=bị từ chối; cancelled=đã hủy.; VD: confirmed');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên duyệt hồ sơ.; VD: 10000000-0000-0000-0000-000000000001');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/hủy/không xác nhận để user xem lại.; VD: Nội dung mẫu dùng để demo.');
            $table->char('approved_venue_cluster_id', 36)->nullable()->comment('ID cụm sân được tạo sau khi duyệt; lưu logical để tránh vòng FK.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('submitted_at')->comment('Thời điểm user gửi hồ sơ.; VD: 18:00:00');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm admin xử lý hồ sơ.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['user_id', 'status'], 'partner_applications_user_id_status_index');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
