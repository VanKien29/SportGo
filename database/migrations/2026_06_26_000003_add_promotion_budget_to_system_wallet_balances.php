<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_wallet_balances', 'promotion_monthly_budget')) {
                $table->decimal('promotion_monthly_budget', 15, 2)
                    ->nullable()
                    ->after('alert_threshold')
                    ->comment('Ngân sách khuyến mãi theo kỳ, chỉ dùng để cảnh báo.');
            }

            if (! Schema::hasColumn('system_wallet_balances', 'budget_period_type')) {
                $table->string('budget_period_type', 10)
                    ->default('month')
                    ->after('promotion_monthly_budget')
                    ->comment('Kỳ ngân sách khuyến mãi: week, month hoặc year.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            if (Schema::hasColumn('system_wallet_balances', 'budget_period_type')) {
                $table->dropColumn('budget_period_type');
            }

            if (Schema::hasColumn('system_wallet_balances', 'promotion_monthly_budget')) {
                $table->dropColumn('promotion_monthly_budget');
            }
        });
    }
};
