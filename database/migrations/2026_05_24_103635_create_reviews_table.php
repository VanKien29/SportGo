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
        Schema::create('reviews', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('booking_id', 36)->unique()->comment('Booking được review; unique để một booking chỉ review một lần.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('customer_id', 36)->comment('User đã đặt sân, denormalized từ bookings để lọc nhanh.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân được review, denormalized từ bookings để thống kê rating.; VD: 10000000-0000-0000-0000-000000000001');
            $table->tinyInteger('rating')->comment('Điểm đánh giá cụm sân.; VD: 60');
            $table->text('comment')->nullable()->comment('Nội dung review của user.; VD: Nội dung mẫu dùng để demo.');
            $table->text('reply_content')->nullable()->comment('Phản hồi của chủ sân.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('replied_at')->nullable()->comment('Thời điểm chủ sân phản hồi review.; VD: 18:00:00');
            $table->boolean('is_visible')->comment('Review có hiển thị công khai hay không.; VD: true');
            $table->timestamps();
            $table->index(['venue_cluster_id', 'is_visible', 'created_at'], 'reviews_venue_cluster_id_is_visible_created_at_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
