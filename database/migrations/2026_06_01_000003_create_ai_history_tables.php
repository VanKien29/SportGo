<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36)->comment('User sở hữu lịch sử trò chuyện AI.');
                $table->string('title', 255)->nullable()->comment('Tiêu đề cuộc trò chuyện AI.');
                $table->enum('status', ['active', 'archived', 'deleted'])->default('active')
                    ->comment('Trạng thái hiển thị lịch sử AI.');
                $table->softDeletes();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'ai_conversations_user_status_index');
                $table->index('deleted_at', 'ai_conversations_deleted_at_index');
                $table->foreign('user_id', 'ai_conversations_user_foreign')
                    ->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('ai_conversation_id', 36)->comment('Cuộc trò chuyện AI chứa message.');
                $table->enum('role', ['user', 'assistant', 'system'])->comment('Vai trò message trong cuộc trò chuyện AI.');
                $table->longText('content')->comment('Nội dung message.');
                $table->json('metadata')->nullable()->comment('Dữ liệu phụ như token, model, context rút gọn.');
                $table->softDeletes();
                $table->timestamps();

                $table->index(['ai_conversation_id', 'created_at'], 'ai_messages_conversation_created_index');
                $table->index('role', 'ai_messages_role_index');
                $table->index('deleted_at', 'ai_messages_deleted_at_index');
                $table->foreign('ai_conversation_id', 'ai_messages_conversation_foreign')
                    ->references('id')->on('ai_conversations')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('ai_feedbacks')) {
            Schema::create('ai_feedbacks', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('ai_message_id', 36)->comment('Message assistant được đánh giá.');
                $table->char('user_id', 36)->comment('User gửi feedback.');
                $table->tinyInteger('rating')->nullable()->comment('Điểm đánh giá, ví dụ 1-5.');
                $table->text('comment')->nullable()->comment('Góp ý của user.');
                $table->timestamps();

                $table->unique(['ai_message_id', 'user_id'], 'ai_feedbacks_message_user_unique');
                $table->index('rating', 'ai_feedbacks_rating_index');
                $table->foreign('ai_message_id', 'ai_feedbacks_message_foreign')
                    ->references('id')->on('ai_messages')->onDelete('cascade');
                $table->foreign('user_id', 'ai_feedbacks_user_foreign')
                    ->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedbacks');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
