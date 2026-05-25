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
        Schema::create('court_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Tên loại sân như Badminton court, Football 7-a-side; admin quản lý.; VD: Sân Cầu Lông A1');
            $table->text('description')->nullable()->comment('Mô tả ngắn loại sân để admin/FE hiển thị.; VD: Nội dung mẫu dùng để demo.');
            $table->integer('player_count')->comment('Số người chơi tham khảo cho loại sân.; VD: 60');
            $table->boolean('is_active')->comment('Loại sân còn được chủ sân chọn hay không.; VD: true');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_types');
    }
};
