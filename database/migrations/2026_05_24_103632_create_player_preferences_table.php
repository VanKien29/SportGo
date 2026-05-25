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
        Schema::create('player_preferences', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique()->comment('User sở hữu hồ sơ người chơi, mỗi user chỉ có một bản ghi.; VD: 10000000-0000-0000-0000-000000000001');
            $table->decimal('player_rating_avg', 3, 2)->comment('Điểm trung bình của user, tính lại từ player_ratings.; VD: 60');
            $table->integer('player_rating_count')->comment('Số lượt đánh giá hợp lệ của user, tính từ player_ratings.; VD: 60');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_preferences');
    }
};
