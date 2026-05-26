<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('reviews', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('booking_id', 36)->unique()->comment('Booking được review; unique.');
            $table->char('customer_id', 36)->comment('User đã đặt sân, denormalized.');
            $table->char('venue_cluster_id', 36)->comment('Cụm sân được review, denormalized.');
            $table->unsignedTinyInteger('rating')->comment('Điểm đánh giá.');
            $table->text('comment')->nullable()->comment('Nội dung review.');
            $table->text('reply_content')->nullable()->comment('Phản hồi của chủ sân.');
            $table->timestamp('replied_at')->nullable()->comment('Thời điểm chủ sân phản hồi.');
            $table->boolean('is_visible')->default(true)->comment('Review có hiển thị công khai.');
            $table->timestamps();
            $table->index('customer_id', 'reviews_customer_id_index');
            $table->index('venue_cluster_id', 'reviews_venue_cluster_id_index');
            $table->index('is_visible', 'reviews_is_visible_index');
            $table->index(['venue_cluster_id', 'is_visible', 'created_at'], 'reviews_venue_cluster_id_is_visible_created_at_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('restrict');
        });
    }
    public function down(): void { Schema::dropIfExists('reviews'); }
};
