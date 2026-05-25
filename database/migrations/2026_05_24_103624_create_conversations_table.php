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
        Schema::create('conversations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('type')->comment('Loại chat: direct, player_post hoặc venue_contact. Giá trị enum: direct=chat trực tiếp; player_post=chat bài tuyển; venue_contact=chat với chủ sân.; VD: booking_reminder');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng chat gắn vào như player_posts hoặc venue_clusters; logical reference.; VD: booking_reminder');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng chat gắn vào.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('title', 255)->nullable()->comment('Tiêu đề chat để hiển thị trong danh sách.; VD: Sân Cầu Lông A1');
            $table->char('created_by', 36)->nullable()->comment('User tạo conversation.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('last_message_at')->nullable()->comment('Thời điểm tin nhắn cuối để sắp xếp danh sách chat.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamps();
            $table->index(['reference_type', 'reference_id'], 'conversations_reference_type_reference_id_index');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
