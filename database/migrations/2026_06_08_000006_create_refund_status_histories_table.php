<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refund_status_histories')) {
            return;
        }

        Schema::create('refund_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->char('refund_id', 36);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->char('changed_by', 36)->nullable();
            $table->string('actor_type', 50)->default('system');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['refund_id', 'created_at'], 'refund_status_histories_refund_created_index');
            $table->index('new_status', 'refund_status_histories_new_status_index');
            $table->foreign('refund_id', 'refund_status_histories_refund_foreign')
                ->references('id')->on('refunds')->onDelete('restrict');
            $table->foreign('changed_by', 'refund_status_histories_changed_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_status_histories');
    }
};
