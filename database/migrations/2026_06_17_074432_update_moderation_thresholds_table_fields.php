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
        Schema::table('moderation_thresholds', function (Blueprint $table) {
            $table->dropColumn([
                'auto_hide_score',
                'admin_alert_score',
                'score_window_days',
                'score_reset_days',
                'action_type',
                'duration_days',
            ]);

            $table->unsignedSmallInteger('warning_threshold')->default(3)->comment('Ngưỡng cảnh báo');
            $table->unsignedSmallInteger('action_threshold')->default(5)->comment('Ngưỡng thực hiện thao tác Ẩn/Khóa');
            $table->unsignedSmallInteger('unique_reporters_threshold')->default(2)->comment('Ngưỡng số người báo cáo khác nhau');
            $table->unsignedSmallInteger('timeframe_days')->default(7)->comment('Ngưỡng trong khoảng thời gian (ngày)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderation_thresholds', function (Blueprint $table) {
            $table->dropColumn([
                'warning_threshold',
                'action_threshold',
                'unique_reporters_threshold',
                'timeframe_days',
            ]);

            $table->unsignedSmallInteger('auto_hide_score')->default(10);
            $table->unsignedSmallInteger('admin_alert_score')->default(20);
            $table->unsignedTinyInteger('score_window_days')->default(30);
            $table->unsignedSmallInteger('score_reset_days')->default(90);
            $table->string('action_type', 50)->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
        });
    }
};
