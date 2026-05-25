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
        Schema::create('venue_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('Nhân viên sân được gán quyền.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân mà nhân viên được phép quản lý.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('scope_type')->comment('Phạm vi quản lý: all_cluster là toàn cụm, court_type là theo loại sân. Giá trị enum: all_cluster=toàn cụm sân; court_type=theo loại sân.; VD: booking_reminder');
            $table->unsignedBigInteger('court_type_id')->nullable()->comment('Loại sân nhân viên được quản lý nếu scope_type là court_type.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('scope_key', 50)->unique()->comment('Khóa unique kỹ thuật: all hoặc court_type:{id}, tránh trùng khi court_type_id null.; VD: giá trị mẫu');
            $table->char('assigned_by', 36)->nullable()->comment('Chủ sân/admin gán quyền cho nhân viên.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('status')->comment('Trạng thái phân quyền: active hoặc inactive. Giá trị enum: active=đang hoạt động; inactive=không hoạt động.; VD: confirmed');
            $table->timestamps();
            $table->unique(['user_id', 'venue_cluster_id', 'scope_key'], 'venue_staff_assignments_unique');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_staff_assignments');
    }
};
