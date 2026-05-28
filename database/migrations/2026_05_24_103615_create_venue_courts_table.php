<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_courts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân chứa sân con này.');
            $table->unsignedBigInteger('court_type_id')->comment('Loại sân của sân con.');
            $table->string('name', 100)->comment('Tên sân con hiển thị trong lịch đặt sân.');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->comment('Trạng thái sân con: active cho đặt, maintenance bảo trì, inactive không hoạt động.');
            $table->integer('sort_order')->default(0)->comment('Thứ tự hiển thị sân con trong cụm sân.');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['venue_cluster_id', 'status'], 'venue_courts_venue_cluster_id_status_index');
            $table->index('name', 'venue_courts_name_index');
            $table->index('status', 'venue_courts_status_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_courts');
    }
};
