<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->enum('type', ['direct', 'player_post', 'venue_contact'])->comment('Loại chat: direct, player_post hoặc venue_contact.');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng chat gắn vào; logical reference.');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng chat gắn vào.');
            $table->string('title', 255)->nullable()->comment('Tiêu đề chat để hiển thị trong danh sách.');
            $table->char('created_by', 36)->nullable()->comment('User tạo conversation.');
            $table->timestamp('last_message_at')->nullable()->comment('Thời điểm tin nhắn cuối để sắp xếp danh sách chat.');
            $table->timestamps();
            $table->index('type', 'conversations_type_index');
            $table->index(['reference_type', 'reference_id'], 'conversations_reference_type_reference_id_index');
            $table->index('last_message_at', 'conversations_last_message_at_index');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
