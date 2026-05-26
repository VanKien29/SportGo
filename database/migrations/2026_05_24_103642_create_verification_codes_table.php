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
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->nullable()->comment('User liên quan nếu đã có tài khoản; nullable cho reset hoặc pre-register.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('identifier', 255)->comment('Email hoặc phone nhận mã.; VD: giá trị mẫu');
            $table->string('type')->comment('Mục đích mã: register, reset_password, phone_verify, email_verify. Giá trị enum: register=đăng ký; reset_password=đặt lại mật khẩu; phone_verify=xác thực phone; email_verify=xác thực email.; VD: booking_reminder');
            $table->string('channel')->comment('Kênh gửi mã: email hoặc sms. Giá trị enum: email=email; sms=tin nhắn SMS.; VD: giá trị mẫu');
            $table->string('code', 255)->comment('Mã xác thực đã sinh.; VD: SPORTGO_CODE_001');
            $table->smallInteger('attempt_count')->comment('Số lần user đã thử nhập mã.; VD: 60');
            $table->smallInteger('max_attempts')->comment('Số lần thử tối đa trước khi khóa mã.; VD: 60');
            $table->boolean('is_used')->comment('Đánh dấu mã đã được dùng để tránh dùng lại.; VD: true');
            $table->timestamp('expires_at')->comment('Thời điểm mã hết hạn.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm sinh mã.; VD: 2026-06-15 18:00:00');
            $table->index(['identifier', 'type', 'is_used'], 'verification_codes_lookup_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
