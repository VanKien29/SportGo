<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_support_requests', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('conversation_id', 36);
            $table->char('booking_id', 36);
            $table->char('customer_id', 36);
            $table->char('venue_cluster_id', 36);
            $table->string('request_type', 40);
            $table->text('note')->nullable();
            $table->string('status', 30)->default('pending');
            $table->char('handled_by', 36)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'booking_support_conv_created_index');
            $table->index(['booking_id', 'status'], 'booking_support_booking_status_index');
            $table->index(['venue_cluster_id', 'status'], 'booking_support_cluster_status_index');
            $table->foreign('conversation_id', 'booking_support_conversation_foreign')
                ->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('booking_id', 'booking_support_booking_foreign')
                ->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('customer_id', 'booking_support_customer_foreign')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id', 'booking_support_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->foreign('handled_by', 'booking_support_handled_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_support_requests');
    }
};