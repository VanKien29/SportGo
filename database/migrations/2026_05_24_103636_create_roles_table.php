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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Mã role duy nhất để xử lý phân quyền trong code, ví dụ super_admin, venue_owner.; VD: Sân Cầu Lông A1');
            $table->string('display_name', 100)->comment('Tên role dễ đọc để hiển thị trong màn quản trị phân quyền.; VD: Sân Cầu Lông A1');
            $table->text('description')->nullable()->comment('Mô tả role này được phép làm gì.; VD: Nội dung mẫu dùng để demo.');
            $table->boolean('is_system')->comment('Đánh dấu role hệ thống mặc định, không nên sửa/xóa tùy tiện.; VD: true');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
