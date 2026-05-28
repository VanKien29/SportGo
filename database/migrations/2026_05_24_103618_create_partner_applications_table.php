<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('User gửi hồ sơ đăng ký làm chủ sân.');
            $table->string('business_name', 255)->comment('Tên đơn vị/cá nhân kinh doanh sân.');
            $table->string('tax_code', 50)->nullable()->comment('Mã số thuế nếu có.');
            $table->string('venue_name', 255)->comment('Tên cụm sân dự kiến tạo khi duyệt hồ sơ.');
            $table->text('venue_address')->comment('Địa chỉ cụm sân nhập trong form đăng ký.');
            $table->string('venue_map_url', 1000)->nullable()->comment('Link Google Maps user dán trong form.');
            $table->decimal('venue_latitude', 10, 7)->comment('Vĩ độ cụm sân.');
            $table->decimal('venue_longitude', 10, 7)->comment('Kinh độ cụm sân.');
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'])->default('pending')->comment('Trạng thái hồ sơ.');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên duyệt hồ sơ.');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/hủy.');
            $table->char('approved_venue_cluster_id', 36)->nullable()->comment('ID cụm sân được tạo sau khi duyệt; logical.');
            $table->timestamp('submitted_at')->useCurrent()->comment('Thời điểm user gửi hồ sơ.');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm admin xử lý hồ sơ.');
            $table->timestamps();
            $table->index(['user_id', 'status'], 'partner_applications_user_id_status_index');
            $table->index('status', 'partner_applications_status_index');
            $table->index('submitted_at', 'partner_applications_submitted_at_index');
            $table->index('approved_venue_cluster_id', 'partner_applications_approved_venue_cluster_id_index');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
