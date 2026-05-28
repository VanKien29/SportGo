<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('system_policies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('key', 100)->comment('Mã chính sách.');
            $table->unsignedInteger('version')->default(1)->comment('Phiên bản chính sách.');
            $table->string('title', 255)->comment('Tiêu đề chính sách.');
            $table->longText('content')->comment('Nội dung đầy đủ.');
            $table->enum('type', ['general', 'refund', 'booking', 'moderation'])->default('general')->comment('Loại chính sách.');
            $table->boolean('is_active')->default(true)->comment('Chính sách đang có hiệu lực.');
            $table->timestamp('effective_from')->nullable()->comment('Thời điểm bắt đầu hiệu lực.');
            $table->char('created_by', 36)->nullable()->comment('Admin tạo.');
            $table->char('updated_by', 36)->nullable()->comment('Admin cập nhật.');
            $table->timestamps();
            $table->unique(['key', 'version'], 'system_policies_key_version_unique');
            $table->index('type', 'system_policies_type_index');
            $table->index('is_active', 'system_policies_is_active_index');
            $table->index('effective_from', 'system_policies_effective_from_index');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('system_policies'); }
};
