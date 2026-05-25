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
        Schema::create('moderation_configs', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->comment('Giá trị cấu hình lưu dạng text, cast theo value_type ở service.; VD: giá trị mẫu');
            $table->string('value_type')->comment('Kiểu dữ liệu của value: string, integer, float, boolean, json. Giá trị enum: string=chuỗi; integer=số nguyên; float=số thực; boolean=đúng/sai; json=JSON.; VD: booking_reminder');
            $table->text('description')->nullable()->comment('Mô tả cấu hình ảnh hưởng nghiệp vụ nào.; VD: Nội dung mẫu dùng để demo.');
            $table->char('updated_by', 36)->nullable()->comment('Admin/nhân viên cập nhật cấu hình.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamps();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_configs');
    }
};
