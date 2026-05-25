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
        Schema::create('player_preferred_court_types', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('Người chơi chọn loại sân yêu thích, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân được yêu thích, trỏ court_types.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->integer('sort_order')->comment('Thứ tự ưu tiên nếu user chọn nhiều loại sân.; VD: 1');
            $table->timestamps();
            $table->unique(['user_id', 'court_type_id'], 'player_preferred_court_types_user_id_court_type_id_unique');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_preferred_court_types');
    }
};
