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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Mã quyền duy nhất dùng trong BE để kiểm tra quyền, ví dụ booking.manage.; VD: SPORTGO_CODE_001');
            $table->string('name', 255)->comment('Tên quyền dễ đọc trên màn hình quản trị.; VD: Sân Cầu Lông A1');
            $table->string('group_name', 50)->comment('Nhóm quyền để FE gom quyền theo module như booking, payment, moderation.; VD: Sân Cầu Lông A1');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo quyền.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
