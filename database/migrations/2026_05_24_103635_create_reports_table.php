<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('reporter_id', 36)->comment('User gửi report.');
            $table->string('reportable_type', 100)->comment('Loại đối tượng bị report; logical reference.');
            $table->string('reportable_id', 100)->comment('ID đối tượng bị report.');
            $table->enum('reason', ['spam', 'offensive', 'fake', 'harassment', 'other'])->comment('Lý do report.');
            $table->text('description')->nullable()->comment('Mô tả chi tiết.');
            $table->enum('status', ['pending', 'reviewing', 'resolved', 'dismissed'])->default('pending')->comment('Trạng thái xử lý.');
            $table->enum('action_taken', ['warning', 'content_hidden', 'content_deleted', 'account_locked', 'venue_locked'])->nullable()->comment('Hành động đã áp dụng.');
            $table->text('action_note')->nullable()->comment('Ghi chú xử lý.');
            $table->char('reviewed_by', 36)->nullable()->comment('Người xử lý.');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm xử lý.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gửi report.');
            $table->index(['reportable_type', 'reportable_id'], 'reports_reportable_type_reportable_id_index');
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_reporter_target_unique');
            $table->index('status', 'reports_status_index');
            $table->index(['status', 'created_at'], 'reports_status_created_at_index');
            $table->index('created_at', 'reports_created_at_index');
            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('reports'); }
};
