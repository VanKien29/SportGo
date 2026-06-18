<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('moderation_thresholds')) {
            return;
        }

        Schema::create('moderation_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->char('system_policy_id', 36);
            $table->string('target_type', 50);
            $table->unsignedSmallInteger('auto_hide_score')->default(10);
            $table->unsignedSmallInteger('admin_alert_score')->default(20);
            $table->unsignedTinyInteger('score_window_days')->default(30);
            $table->unsignedSmallInteger('score_reset_days')->default(90);
            $table->timestamps();

            $table->unique(['system_policy_id', 'target_type'], 'moderation_thresholds_policy_target_unique');
            $table->index('target_type', 'moderation_thresholds_target_type_index');
            $table->foreign('system_policy_id', 'moderation_thresholds_policy_foreign')
                ->references('id')->on('system_policies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_thresholds');
    }
};
