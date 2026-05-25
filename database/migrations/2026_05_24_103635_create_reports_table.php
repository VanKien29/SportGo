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
        Schema::create('reports', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('reporter_id', 36)->comment('User gửi report.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('reportable_type', 100)->comment('Loại đối tượng bị report như users, community_posts, player_posts; logical reference.; VD: booking_reminder');
            $table->string('reportable_id', 100)->comment('ID đối tượng bị report; validate bằng service theo reportable_type.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('reason')->comment('Lý do report: spam, offensive, fake, harassment, other. Giá trị enum: spam=spam; offensive=phản cảm; fake=giả mạo; harassment=quấy rối; other=khác.; VD: Nội dung mẫu dùng để demo.');
            $table->text('description')->nullable()->comment('Mô tả chi tiết nội dung vi phạm.; VD: Nội dung mẫu dùng để demo.');
            $table->string('status')->comment('Trạng thái xử lý report: pending, reviewing, resolved, dismissed. Giá trị enum: pending=chờ xử lý; reviewing=đang xem xét; resolved=đã xử lý; dismissed=bỏ qua.; VD: confirmed');
            $table->string('action_taken')->nullable()->comment('Hành động đã áp dụng như warning, content_hidden, account_locked. Giá trị enum: warning=cảnh báo; content_hidden=ẩn nội dung; content_deleted=xóa nội dung; account_locked=khóa tài khoản; venue_locked=khóa sân.; VD: giá trị mẫu');
            $table->text('action_note')->nullable()->comment('Ghi chú xử lý của admin/nhân viên.; VD: Nội dung mẫu dùng để demo.');
            $table->char('reviewed_by', 36)->nullable()->comment('Người xử lý report.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm xử lý report.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gửi report.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['reportable_type', 'reportable_id'], 'reports_reportable_type_reportable_id_index');
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_reporter_target_unique');
            $table->index(['status', 'created_at'], 'reports_status_created_at_index');
            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
