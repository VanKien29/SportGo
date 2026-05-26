<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('Người nhận thông báo.');
            $table->string('type', 50)->comment('Loại thông báo.');
            $table->string('title', 255)->comment('Tiêu đề thông báo.');
            $table->text('body')->nullable()->comment('Nội dung ngắn.');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng điều hướng; logical reference.');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng điều hướng.');
            $table->json('data')->nullable()->comment('JSON dữ liệu phụ.');
            $table->boolean('is_read')->default(false)->comment('Đánh dấu user đã đọc.');
            $table->timestamp('read_at')->nullable()->comment('Thời điểm user đọc.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo thông báo.');
            $table->index('type', 'notifications_type_index');
            $table->index('is_read', 'notifications_is_read_index');
            $table->index('created_at', 'notifications_created_at_index');
            $table->index(['reference_type', 'reference_id'], 'notifications_reference_type_reference_id_index');
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_id_is_read_created_at_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
