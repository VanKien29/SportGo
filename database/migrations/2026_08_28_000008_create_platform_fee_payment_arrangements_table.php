<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_payment_arrangements', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('status', 40)->default('pending_owner_acceptance');
            $table->string('arrangement_type', 40)->default('secured_deferred');
            $table->unsignedSmallInteger('service_months');
            $table->date('service_start');
            $table->date('service_end');
            $table->date('payment_due_date');
            $table->decimal('credit_limit', 16, 2);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('secured_amount', 16, 2)->default(0);
            $table->text('reason');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('proposed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('owner_accepted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('owner_accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['venue_cluster_id', 'status'], 'pf_arrangements_cluster_status_index');
            $table->index(['status', 'payment_due_date'], 'pf_arrangements_status_due_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('proposed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('owner_accepted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_payment_arrangements');
    }
};
