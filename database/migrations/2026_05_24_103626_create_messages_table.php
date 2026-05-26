<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('conversation_id', 36)->comment('Conversation chứa tin nhắn.');
            $table->char('sender_id', 36)->nullable()->comment('User gửi tin nhắn; nullable cho tin nhắn hệ thống.');
            $table->text('content')->comment('Nội dung tin nhắn.');
            $table->boolean('is_system')->default(false)->comment('Đánh dấu tin nhắn hệ thống.');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng đính kèm; logical reference.');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng đính kèm.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gửi tin nhắn.');
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_id_created_at_index');
            $table->index(['reference_type', 'reference_id'], 'messages_reference_type_reference_id_index');
            $table->index('created_at', 'messages_created_at_index');
            $table->index('is_system', 'messages_is_system_index');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
