<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_termination_requests', function (Blueprint $table): void {
            $table->timestamp('platform_fee_cutoff_at')->nullable()->after('requested_effective_date');
            $table->decimal('platform_fee_outstanding_amount', 14, 2)->default(0)->after('withdrawable_amount');
            $table->decimal('platform_fee_accrued_amount', 14, 2)->default(0)->after('platform_fee_outstanding_amount');
            $table->decimal('platform_fee_prepaid_refund_amount', 14, 2)->default(0)->after('platform_fee_accrued_amount');
            $table->decimal('platform_fee_hold_amount', 14, 2)->default(0)->after('platform_fee_prepaid_refund_amount');
            $table->string('platform_fee_settlement_status', 30)->default('pending')->after('platform_fee_hold_amount');
            $table->timestamp('reactivation_billing_started_at')->nullable()->after('platform_fee_settlement_status');
        });
    }

    public function down(): void
    {
        Schema::table('partner_termination_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'platform_fee_cutoff_at',
                'platform_fee_outstanding_amount',
                'platform_fee_accrued_amount',
                'platform_fee_prepaid_refund_amount',
                'platform_fee_hold_amount',
                'platform_fee_settlement_status',
                'reactivation_billing_started_at',
            ]);
        });
    }
};
