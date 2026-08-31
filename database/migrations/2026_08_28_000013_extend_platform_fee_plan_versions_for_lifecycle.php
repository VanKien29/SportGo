<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_plan_versions', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->unsignedTinyInteger('billing_anchor_day')->default(1)->after('trial_days');
            $table->string('notification_mode', 30)->default('notice_only')->after('notice_days');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('published_by');
            $table->timestamp('cancelled_at')->nullable()->after('scheduled_at');

            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_plan_versions', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'revision',
                'billing_anchor_day',
                'notification_mode',
                'cancelled_by',
                'cancelled_at',
            ]);
        });
    }
};
