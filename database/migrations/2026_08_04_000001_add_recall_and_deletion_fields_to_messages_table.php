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
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_recalled')->default(false)->after('is_pinned')->comment('Đánh dấu tin nhắn đã bị thu hồi.');
            $table->timestamp('recalled_at')->nullable()->after('is_recalled')->comment('Thời điểm thu hồi tin nhắn.');
        });

        Schema::create('user_message_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('message_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->unique(['user_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_message_deletions');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_recalled', 'recalled_at']);
        });
    }
};
