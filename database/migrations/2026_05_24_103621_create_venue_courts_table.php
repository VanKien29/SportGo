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
        Schema::create('venue_courts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân chứa sân con này.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân của sân con.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('name', 100)->comment('Tên sân con hiển thị trong lịch đặt sân.; VD: Sân Cầu Lông A1');
            $table->string('status')->comment('Trạng thái sân con: active cho đặt, maintenance bảo trì, inactive không hoạt động. Giá trị enum: active=đang hoạt động; maintenance=bảo trì; inactive=không hoạt động.; VD: confirmed');
            $table->integer('sort_order')->comment('Thứ tự hiển thị sân con trong cụm sân.; VD: 1');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_cluster_id', 'status'], 'venue_courts_venue_cluster_id_status_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_courts');
    }
};
