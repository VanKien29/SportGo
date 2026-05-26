<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_prices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân áp dụng giá đặc biệt.');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân áp dụng giá đặc biệt.');
            $table->enum('date_type', ['holiday', 'special_date'])->default('holiday')->comment('Loại ngày: holiday hoặc special_date.');
            $table->enum('booking_type', ['all', 'single', 'recurring'])->default('all')->comment('Kiểu booking áp dụng giá.');
            $table->date('holiday_date')->comment('Ngày cụ thể áp dụng giá đặc biệt.');
            $table->time('start_time')->default('00:00:00')->comment('Giờ bắt đầu khung giá.');
            $table->time('end_time')->default('23:59:59')->comment('Giờ kết thúc khung giá.');
            $table->decimal('price', 12, 2)->default(0.00)->comment('Giá đặc biệt.');
            $table->string('note', 255)->nullable()->comment('Ghi chú lý do.');
            $table->boolean('is_active')->default(true)->comment('Giá ngày lễ còn được áp dụng hay không.');
            $table->timestamps();
            $table->unique(['venue_cluster_id', 'court_type_id', 'holiday_date', 'start_time', 'end_time', 'booking_type'], 'holiday_prices_unique');
            $table->index(['venue_cluster_id', 'court_type_id', 'holiday_date', 'booking_type', 'is_active'], 'holiday_prices_lookup_index');
            $table->index('date_type', 'holiday_prices_date_type_index');
            $table->index('booking_type', 'holiday_prices_booking_type_index');
            $table->index('start_time', 'holiday_prices_start_time_index');
            $table->index('end_time', 'holiday_prices_end_time_index');
            $table->index('is_active', 'holiday_prices_is_active_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_prices');
    }
};
