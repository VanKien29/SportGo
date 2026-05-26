<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Tên loại sân như Badminton court, Football 7-a-side; admin quản lý.');
            $table->text('description')->nullable()->comment('Mô tả ngắn loại sân để admin/FE hiển thị.');
            $table->unsignedInteger('player_count')->default(0)->comment('Số người chơi tham khảo cho loại sân.');
            $table->boolean('is_active')->default(true)->comment('Loại sân còn được chủ sân chọn hay không.');
            $table->timestamps();
            $table->softDeletes();
            $table->index('is_active', 'court_types_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_types');
    }
};
