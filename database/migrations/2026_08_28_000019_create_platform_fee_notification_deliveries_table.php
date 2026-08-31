<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 190)->unique();
            $table->string('event_type', 60);
            $table->unsignedInteger('event_revision')->default(1);
            $table->unsignedBigInteger('plan_version_id')->nullable();
            $table->unsignedBigInteger('ledger_id')->nullable();
            $table->unsignedBigInteger('arrangement_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id');
            $table->string('channel', 20);
            $table->string('destination', 255)->nullable();
            $table->string('title', 255);
            $table->text('body');
            $table->string('action_url', 500)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'pf_notification_deliveries_status_queue_index');
            $table->index(['recipient_user_id', 'event_type'], 'pf_notification_deliveries_recipient_event_index');
            $table->foreign('plan_version_id')->references('id')->on('platform_fee_plan_versions')->nullOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->nullOnDelete();
            $table->foreign('arrangement_id')->references('id')->on('platform_fee_payment_arrangements')->nullOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_notification_deliveries');
    }
};
