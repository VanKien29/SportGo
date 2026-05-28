<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('price_slots', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân áp dụng giá.');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân áp dụng giá.');
            $table->enum('booking_type', ['all', 'single', 'recurring'])->default('all')->comment('Kiểu booking áp dụng giá.');
            $table->time('start_time')->comment('Giờ bắt đầu khung giá.');
            $table->time('end_time')->comment('Giờ kết thúc khung giá.');
            $table->decimal('price', 12, 2)->default(0.00)->comment('Giá tiền.');
            $table->json('apply_to_days')->nullable();
            $table->boolean('is_active')->default(true)->comment('Khung giá còn dùng.');
            $table->timestamps();
            $table->index(['venue_cluster_id', 'court_type_id', 'booking_type', 'is_active'], 'price_slots_cluster_type_active_index');
            $table->index('booking_type', 'price_slots_booking_type_index');
            $table->index('start_time', 'price_slots_start_time_index');
            $table->index('end_time', 'price_slots_end_time_index');
            $table->index('is_active', 'price_slots_is_active_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('price_slots'); }
};
