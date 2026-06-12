<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_jobs')) {
            return;
        }

        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('backup_code', 50)->unique()->comment('Mã backup để admin tra cứu.');
            $table->string('file_name', 255)->nullable()->comment('Tên file backup đã tạo.');
            $table->string('file_path', 1000)->nullable()->comment('Đường dẫn file backup ngoài DB.');
            $table->string('disk', 100)->nullable()->comment('Storage disk lưu backup.');
            $table->unsignedBigInteger('size_bytes')->nullable()->comment('Dung lượng file backup.');
            $table->string('checksum', 128)->nullable()->comment('Checksum kiểm tra file backup.');
            $table->enum('type', ['manual', 'auto'])->default('manual')->comment('Backup thủ công hay tự động.');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')
                ->comment('Trạng thái job backup.');
            $table->char('created_by', 36)->nullable()->comment('Admin tạo backup thủ công.');
            $table->timestamp('started_at')->nullable()->comment('Thời điểm bắt đầu backup.');
            $table->timestamp('completed_at')->nullable()->comment('Thời điểm hoàn tất backup.');
            $table->text('error_message')->nullable()->comment('Lỗi backup nếu thất bại.');
            $table->unsignedInteger('retention_days')->nullable()->comment('Số ngày giữ file backup.');
            $table->timestamps();

            $table->index(['type', 'status'], 'backup_jobs_type_status_index');
            $table->index('created_at', 'backup_jobs_created_at_index');
            $table->index('completed_at', 'backup_jobs_completed_at_index');
            $table->foreign('created_by', 'backup_jobs_created_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
