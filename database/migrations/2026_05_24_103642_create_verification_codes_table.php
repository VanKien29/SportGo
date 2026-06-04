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
            $table->char('user_id', 36)->nullable()->comment('User liên quan nếu đã có tài khoản; nullable cho reset hoặc pre-register.');
            $table->string('identifier', 255)->comment('Email hoặc phone nhận mã.');
            $table->enum('type', ['register', 'reset_password', 'phone_verify', 'email_verify'])->comment('Mục đích mã: register, reset_password, phone_verify, email_verify.');
            $table->enum('channel', ['email', 'sms'])->comment('Kênh gửi mã: email hoặc sms.');
            $table->string('code', 255)->comment('Mã xác thực đã sinh.');
            $table->unsignedSmallInteger('attempt_count')->default(0)->comment('Số lần user đã thử nhập mã.');
            $table->unsignedSmallInteger('max_attempts')->default(5)->comment('Số lần thử tối đa trước khi khóa mã.');
            $table->boolean('is_used')->default(false)->comment('Đánh dấu mã đã được dùng để tránh dùng lại.');
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
