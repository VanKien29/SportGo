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
        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Tên hashtag hiển thị.; VD: Sân Cầu Lông A1');
            $table->string('slug', 100)->unique()->comment('Slug hashtag duy nhất dùng để tìm kiếm/lọc.; VD: san-cau-long-a1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hashtags');
    }
};
