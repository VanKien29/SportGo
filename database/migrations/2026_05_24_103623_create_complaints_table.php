<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->enum('complaint_type', ['venue', 'system'])->comment('Loại khiếu nại: venue với sân hoặc system với hệ thống.');
            $table->char('booking_id', 36)->nullable()->comment('Booking liên quan nếu khiếu nại phát sinh từ booking.');
            $table->char('venue_cluster_id', 36)->nullable()->comment('Cụm sân liên quan nếu là khiếu nại với sân.');
            $table->char('customer_id', 36)->comment('User gửi khiếu nại.');
            $table->text('content')->comment('Nội dung khiếu nại.');
            $table->enum('status', ['open', 'processing', 'resolved', 'rejected', 'closed'])->default('open')->comment('Trạng thái khiếu nại.');
            $table->char('assigned_to', 36)->nullable()->comment('Nhân viên/admin được gán xử lý khiếu nại.');
            $table->text('resolution_note')->nullable()->comment('Ghi chú giải quyết khiếu nại.');
            $table->char('resolved_by', 36)->nullable()->comment('Người xử lý xong khiếu nại.');
            $table->timestamp('resolved_at')->nullable()->comment('Thời điểm xử lý xong.');
            $table->timestamps();
            $table->index('complaint_type', 'complaints_complaint_type_index');
            $table->index('status', 'complaints_status_index');
            $table->index(['status', 'created_at'], 'complaints_status_created_at_index');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
