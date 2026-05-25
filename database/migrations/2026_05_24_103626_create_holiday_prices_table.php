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
        Schema::create('holiday_prices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân áp dụng giá đặc biệt.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân áp dụng giá đặc biệt.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('date_type')->comment('Loại ngày: holiday=ngày lễ; special_date=ngày đặc biệt do chủ sân tự cấu hình.; VD: special_date');
            $table->string('booking_type')->comment('Kiểu booking áp dụng giá: all=tất cả; single=đặt lẻ; recurring=đặt cố định.; VD: recurring');
            $table->date('holiday_date')->comment('Ngày cụ thể áp dụng giá đặc biệt.; VD: 2026-06-15');
            $table->time('start_time')->comment('Giờ bắt đầu khung giá trong ngày lễ/ngày đặc biệt.; VD: 18:00:00');
            $table->time('end_time')->comment('Giờ kết thúc khung giá trong ngày lễ/ngày đặc biệt.; VD: 20:00:00');
            $table->decimal('price', 12, 2)->comment('Giá đặc biệt của ngày và khung giờ đó.; VD: 120000.00');
            $table->string('note', 255)->nullable()->comment('Ghi chú lý do như lễ/tết/sự kiện.; VD: Nội dung mẫu dùng để demo.');
            $table->boolean('is_active')->comment('Giá ngày lễ/ngày đặc biệt còn được áp dụng hay không.; VD: true');
            $table->timestamps();
            $table->unique(['venue_cluster_id', 'court_type_id', 'holiday_date', 'start_time', 'end_time', 'booking_type'], 'holiday_prices_unique');
            $table->index(['venue_cluster_id', 'court_type_id', 'holiday_date', 'booking_type', 'is_active'], 'holiday_prices_lookup_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_prices');
    }
};
