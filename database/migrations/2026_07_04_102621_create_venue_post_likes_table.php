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
        Schema::create('venue_post_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('post_id', 36);
            $table->char('user_id', 36);
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->foreign('post_id')->references('id')->on('venue_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_post_likes');
    }
};
