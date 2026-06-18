<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_location_change_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->char('requested_by', 36);
            $table->char('reviewed_by', 36)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('note')->nullable();           // lý do owner muốn đổi vị trí
            $table->text('status_reason')->nullable();  // lý do từ chối / ghi chú admin

            // Thông tin vị trí mới (snapshot khi gửi yêu cầu)
            $table->string('new_address', 255)->nullable();
            $table->string('new_province', 255)->nullable();
            $table->string('new_ward', 255)->nullable();
            $table->decimal('new_latitude', 10, 7)->nullable();
            $table->decimal('new_longitude', 10, 7)->nullable();
            $table->string('new_map_url', 2000)->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status', 'venue_location_change_requests_status_index');
            $table->index('venue_cluster_id', 'venue_location_change_requests_cluster_index');

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_location_change_requests');
    }
};
