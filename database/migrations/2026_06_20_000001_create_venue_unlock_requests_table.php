<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_unlock_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('venue_cluster_id');
            $table->uuid('requested_by');
            $table->uuid('reviewed_by')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected');
            $table->text('reason')->comment('Lý do giải trình của chủ sân.');
            $table->text('admin_note')->nullable()->comment('Phản hồi của admin.');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['venue_cluster_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_unlock_requests');
    }
};
