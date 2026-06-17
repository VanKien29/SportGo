<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lock_policies', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_lock_enabled')->default(false)->comment('Bật/tắt khóa tự động');
            $table->unsignedInteger('report_threshold')->default(5)->comment('Số lượt báo cáo để kích hoạt khóa');
            $table->unsignedInteger('lock_duration_hours')->nullable()->comment('Thời hạn khóa (giờ), NULL = vĩnh viễn');
            $table->boolean('is_active')->default(false)->index()->comment('Policy đang được áp dụng');
            $table->char('created_by', 36)->comment('Admin tạo policy');
            $table->char('updated_by', 36)->nullable()->comment('Admin cập nhật cuối');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lock_policies');
    }
};
