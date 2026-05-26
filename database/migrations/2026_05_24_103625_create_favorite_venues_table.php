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
        Schema::create('favorite_venues', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('User yêu thích cụm sân.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân được user yêu thích.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm user thêm sân vào danh sách yêu thích.; VD: 2026-06-15 18:00:00');
            $table->unique(['user_id', 'venue_cluster_id'], 'favorite_venues_user_id_venue_cluster_id_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_venues');
    }
};
