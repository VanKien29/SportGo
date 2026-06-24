<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('penalty_escalation_rules')) {
            return;
        }

        Schema::create('penalty_escalation_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('system_policy_id', 36);
            $table->string('target_type', 50);
            $table->unsignedTinyInteger('violation_count');
            $table->boolean('is_catch_all')->default(false);
            $table->string('action_type', 50);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->json('notify_channels');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['system_policy_id', 'target_type', 'violation_count'], 'penalty_escalation_policy_target_count_unique');
            $table->index(['target_type', 'is_catch_all'], 'penalty_escalation_target_catch_all_index');
            $table->foreign('system_policy_id', 'penalty_escalation_policy_foreign')
                ->references('id')->on('system_policies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_escalation_rules');
    }
};
