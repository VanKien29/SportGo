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
        Schema::create('messages', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('conversation_id', 36)->comment('Conversation chứa tin nhắn.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('sender_id', 36)->nullable()->comment('User gửi tin nhắn; nullable cho tin nhắn hệ thống.; VD: 10000000-0000-0000-0000-000000000001');
            $table->text('content')->comment('Nội dung tin nhắn.; VD: Nội dung mẫu dùng để demo.');
            $table->boolean('is_system')->comment('Đánh dấu tin nhắn hệ thống tự sinh hay user gửi.; VD: true');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng đính kèm trong tin nhắn, ví dụ bookings; logical reference.; VD: booking_reminder');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng đính kèm trong tin nhắn.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gửi tin nhắn.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_id_created_at_index');
            $table->index(['reference_type', 'reference_id'], 'messages_reference_type_reference_id_index');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
