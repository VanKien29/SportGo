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
        Schema::dropIfExists('venue_post_comments');

        Schema::create('venue_post_comments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_post_id', 36);
            $table->char('user_id', 36);
            $table->char('parent_id', 36)->nullable();
            $table->text('content');
            $table->enum('status', ['published', 'hidden', 'deleted'])->default('published');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_post_id')->references('id')->on('venue_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('venue_post_comments')->onDelete('set null');

            $table->index(['venue_post_id', 'status', 'created_at'], 'venue_post_comments_query_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_post_comments');
    }
};
