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
        Schema::create('venue_lock_appeals', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->char('owner_id', 36);
            $table->string('title', 255);
            $table->text('content');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending');
            $table->text('reply_content')->nullable();
            $table->char('replied_by', 36)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('replied_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_lock_appeals');
    }
};
