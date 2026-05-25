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
        Schema::create('post_hashtags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hashtag_id')->comment('Hashtag được gắn.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('post_type', 50)->comment('Loại bài viết được gắn hashtag: system_posts, community_posts, venue_posts.; VD: booking_reminder');
            $table->string('post_id', 100)->comment('ID bài viết được gắn hashtag; logical reference.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gắn hashtag vào bài.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['post_type', 'post_id'], 'post_hashtags_post_type_post_id_index');
            $table->unique(['hashtag_id', 'post_type', 'post_id'], 'post_hashtags_unique');
            $table->foreign('hashtag_id')->references('id')->on('hashtags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_hashtags');
    }
};
