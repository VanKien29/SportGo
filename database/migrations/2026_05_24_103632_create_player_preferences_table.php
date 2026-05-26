<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_preferences', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique()->comment('User sở hữu hồ sơ người chơi.');
            $table->decimal('player_rating_avg', 3, 2)->default(0.00)->comment('Điểm trung bình.');
            $table->unsignedInteger('player_rating_count')->default(0)->comment('Số lượt đánh giá.');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('player_preferences'); }
};
