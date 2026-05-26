<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->char('author_id', 36);
            $table->longText('content');
            $table->enum('status', ['pending_review', 'published', 'rejected', 'hidden'])->default('pending_review');
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();

            $table->index(['venue_cluster_id', 'status'], 'venue_posts_venue_cluster_id_status_index');
            $table->index('status', 'venue_posts_status_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_posts');
    }
};
