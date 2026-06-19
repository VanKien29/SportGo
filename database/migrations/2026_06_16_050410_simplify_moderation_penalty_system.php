<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('penalty_escalation_rules');

        Schema::table('moderation_thresholds', function (Blueprint $table) {
            $table->string('action_type', 50)->nullable()->after('score_reset_days');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderation_thresholds', function (Blueprint $table) {
            $table->dropColumn(['action_type', 'duration_days']);
        });

        Schema::create('penalty_escalation_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('system_policy_id', 36);
            $table->string('target_type', 50);
            $table->unsignedSmallInteger('violation_count');
            $table->string('action_type', 50);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->boolean('is_catch_all')->default(false);
            $table->json('notify_channels')->nullable();
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['system_policy_id', 'target_type', 'violation_count'], 'penalty_escalation_unique');
            $table->index(['target_type', 'violation_count'], 'penalty_escalation_target_index');
            $table->foreign('system_policy_id', 'penalty_escalation_policy_foreign')
                ->references('id')->on('system_policies')->onDelete('cascade');
        });
    }
};
