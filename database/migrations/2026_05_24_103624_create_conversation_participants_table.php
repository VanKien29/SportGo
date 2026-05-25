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
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->char('conversation_id', 36)->comment('Conversation mà user tham gia.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('user_id', 36)->comment('User tham gia conversation.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('last_read_at')->nullable()->comment('Thời điểm user đọc tin nhắn gần nhất.; VD: 2026-06-15 18:00:00');
            $table->timestamp('joined_at')->comment('Thời điểm user vào conversation.; VD: 2026-06-15 18:00:00');
            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_conversation_id_user_id_unique');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
