<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_termination_status_histories')) {
            return;
        }

        Schema::create('partner_termination_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_termination_request_id');
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->char('changed_by', 36)->nullable();
            $table->string('actor_type', 50)->default('admin');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partner_termination_request_id', 'created_at'], 'partner_term_status_request_created_index');
            $table->foreign('partner_termination_request_id', 'partner_term_status_request_foreign')
                ->references('id')->on('partner_termination_requests')->onDelete('restrict');
            $table->foreign('changed_by', 'partner_term_status_changed_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_termination_status_histories');
    }
};
