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
        Schema::create('price_slots', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân áp dụng giá.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân áp dụng giá trong cụm.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('booking_type')->comment('Kiểu booking áp dụng giá: all=tất cả; single=đặt lẻ; recurring=đặt cố định.; VD: recurring');
            $table->time('start_time')->comment('Giờ bắt đầu khung giá trong ngày.; VD: 18:00:00');
            $table->time('end_time')->comment('Giờ kết thúc khung giá trong ngày.; VD: 20:00:00');
            $table->decimal('price', 12, 2)->comment('Giá tiền áp dụng cho khung giờ/loại sân.; VD: 120000.00');
            $table->json('apply_to_days')->nullable();
            $table->boolean('is_active')->comment('Khung giá còn được dùng để tính tiền hay không.; VD: true');
            $table->timestamps();
            $table->index(['venue_cluster_id', 'court_type_id', 'booking_type', 'is_active'], 'price_slots_cluster_type_active_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_slots');
    }
};
