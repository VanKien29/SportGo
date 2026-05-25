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
        Schema::create('venue_court_approval_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân mà chủ sân muốn thêm sân con.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân của sân con mới.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('name', 100)->comment('Tên sân con chủ sân đề xuất.; VD: Sân Cầu Lông A1');
            $table->string('status')->comment('Trạng thái yêu cầu: pending, approved, rejected, cancelled. Giá trị enum: pending=chờ xử lý; approved=đã duyệt; rejected=bị từ chối; cancelled=đã hủy.; VD: confirmed');
            $table->char('requested_by', 36)->comment('Chủ sân/nhân viên gửi yêu cầu.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên duyệt yêu cầu.; VD: 10000000-0000-0000-0000-000000000001');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/hủy yêu cầu.; VD: Nội dung mẫu dùng để demo.');
            $table->char('approved_venue_court_id', 36)->nullable()->comment('ID sân con được tạo sau khi duyệt; logical để tránh vòng FK.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm xử lý yêu cầu.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_court_approval_requests');
    }
};
