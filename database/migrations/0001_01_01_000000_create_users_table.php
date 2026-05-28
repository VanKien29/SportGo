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
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary()->comment('UUID định danh user, dùng làm khóa chính và tham chiếu từ các bảng quyền, booking, chat, bài viết.');
            $table->string('username', 50)->unique()->comment('Tên tài khoản dùng để đăng nhập, khác với họ tên hiển thị; phải unique.');
            $table->string('full_name', 255)->comment('Họ tên hiển thị trong hồ sơ, booking, chat, đánh giá.');
            $table->string('phone', 20)->unique()->nullable()->comment('Số điện thoại chính khi đăng ký thường và đặt sân; Google login có thể chưa có phone.');
            $table->string('email', 255)->unique()->nullable()->comment('Email phụ nhưng vẫn dùng đăng nhập, nhận mã xác thực và reset mật khẩu; unique khi có giá trị.');
            $table->timestamp('email_verified_at')->nullable()->comment('Thời điểm email được xác thực; dùng để biết user đã xác thực email chưa.');
            $table->timestamp('phone_verified_at')->nullable()->comment('Thời điểm phone được xác thực; chuẩn bị cho phase SMS.');
            $table->string('password', 255)->comment('Mật khẩu đã hash, không lưu plain text.');
            $table->string('avatar_url', 500)->nullable()->comment('Đường dẫn avatar hiện tại của user; file chi tiết có thể lưu thêm trong media.');
            $table->text('bio')->nullable()->comment('Mô tả cá nhân do user tự nhập, thay cho các field chơi thể thao mơ hồ như field vị trí ưa thích cũ.');
            $table->enum('status', ['pending_verify', 'active', 'locked', 'deactivated'])->default('pending_verify')->comment('Trạng thái tài khoản: pending_verify chờ xác thực, active được dùng, locked bị khóa, deactivated ngừng dùng.');
            $table->enum('verification_channel', ['email', 'sms'])->default('email')->comment('Kênh user chọn để nhận mã xác thực: email hiện làm trước, sms để phase sau.');
            $table->enum('lock_type', ['temporary', 'permanent', 'auto'])->nullable()->comment('Kiểu khóa tài khoản: temporary theo thời hạn, permanent vĩnh viễn, auto do cấu hình tự động.');
            $table->text('status_reason')->nullable()->comment('Lý do khóa/hủy/ngừng tài khoản để hiển thị cho user và phục vụ audit.');
            $table->timestamp('locked_at')->nullable()->comment('Thời điểm tài khoản bị khóa.');
            $table->timestamp('locked_until')->nullable()->comment('Thời điểm hết khóa tạm thời; null khi không khóa hoặc khóa vĩnh viễn tùy lock_type.');
            $table->char('locked_by', 36)->nullable()->comment('Admin/nhân viên khóa tài khoản, trỏ users.id.');
            $table->string('remember_token', 100)->nullable()->comment('Token remember me mặc định của Laravel.');
            $table->timestamps();

            $table->index('status', 'users_status_index');
            $table->index('locked_until', 'users_locked_until_index');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
