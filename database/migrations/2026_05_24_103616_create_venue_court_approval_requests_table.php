<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_court_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedBigInteger('court_type_id');
            $table->string('name', 100);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('status_reason')->nullable();
            $table->unsignedBigInteger('approved_venue_court_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('approved_venue_court_id', 'venue_court_approval_requests_approved_venue_court_id_index');
            $table->index('status', 'venue_court_approval_requests_status_index');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_court_approval_requests');
    }
};
