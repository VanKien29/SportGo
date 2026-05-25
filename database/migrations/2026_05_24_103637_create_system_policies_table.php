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
        Schema::create('system_policies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('key', 100)->comment('Mã chính sách như terms_of_service, refund_policy.; VD: giá trị mẫu');
            $table->integer('version')->comment('Phiên bản chính sách; user acceptance gắn với version.; VD: 1');
            $table->string('title', 255)->comment('Tiêu đề chính sách hiển thị cho user.; VD: Sân Cầu Lông A1');
            $table->longText('content')->comment('Nội dung đầy đủ của chính sách.; VD: Nội dung mẫu dùng để demo.');
            $table->string('type')->comment('Loại chính sách: general, refund, booking, moderation. Giá trị enum: general=chung; refund=hoàn tiền; booking=booking; moderation=kiểm duyệt.; VD: booking_reminder');
            $table->boolean('is_active')->comment('Chính sách đang có hiệu lực hay không.; VD: true');
            $table->timestamp('effective_from')->nullable()->comment('Thời điểm chính sách bắt đầu hiệu lực.; VD: 18:00:00');
            $table->char('created_by', 36)->nullable()->comment('Admin tạo chính sách.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('updated_by', 36)->nullable()->comment('Admin cập nhật chính sách.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamps();
            $table->unique(['key', 'version'], 'system_policies_key_version_unique');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_policies');
    }
};
