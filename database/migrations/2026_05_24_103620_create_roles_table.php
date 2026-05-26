<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Mã role duy nhất để xử lý phân quyền trong code.');
            $table->string('display_name', 100)->comment('Tên role dễ đọc để hiển thị trong màn quản trị.');
            $table->text('description')->nullable()->comment('Mô tả role này được phép làm gì.');
            $table->boolean('is_system')->default(false)->comment('Đánh dấu role hệ thống mặc định, không nên sửa/xóa tùy tiện.');
            $table->timestamps();
            $table->index('is_system', 'roles_is_system_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
