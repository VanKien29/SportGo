<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_configs', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->comment('Giá trị cấu hình lưu dạng text.');
            $table->enum('value_type', ['string', 'integer', 'float', 'boolean', 'json'])->default('string')->comment('Kiểu dữ liệu của value.');
            $table->text('description')->nullable()->comment('Mô tả cấu hình.');
            $table->char('updated_by', 36)->nullable()->comment('Admin/nhân viên cập nhật cấu hình.');
            $table->timestamps();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_configs');
    }
};
