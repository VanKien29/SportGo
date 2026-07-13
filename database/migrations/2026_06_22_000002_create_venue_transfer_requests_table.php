<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_code', 50)->unique()->comment('Ma yeu cau chuyen giao cum san.');
            $table->unsignedBigInteger('venue_cluster_id')->comment('Cum san duoc chuyen giao.');
            $table->unsignedBigInteger('from_owner_id')->comment('Chu san hien tai.');
            $table->unsignedBigInteger('to_owner_id')->comment('Chu san tiep nhan.');
            $table->text('reason')->nullable()->comment('Ly do chuyen giao.');
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])
                ->default('pending')->comment('Trang thai yeu cau chuyen giao.');
            $table->unsignedBigInteger('requested_by')->comment('Nguoi tao yeu cau.');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin duyet yeu cau.');
            $table->text('status_reason')->nullable()->comment('Ly do tu choi hoac huy.');
            $table->date('effective_date')->nullable()->comment('Ngay chuyen giao co hieu luc.');
            $table->timestamp('completed_at')->nullable()->comment('Thoi diem hoan tat chuyen giao.');
            $table->timestamp('reviewed_at')->nullable()->comment('Thoi diem admin xu ly.');
            $table->timestamps();

            $table->index(['venue_cluster_id', 'status'], 'venue_transfer_requests_cluster_status_index');
            $table->index(['from_owner_id', 'status'], 'venue_transfer_requests_from_owner_status_index');
            $table->index(['to_owner_id', 'status'], 'venue_transfer_requests_to_owner_status_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('restrict');
            $table->foreign('from_owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('to_owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_transfer_requests');
    }
};
