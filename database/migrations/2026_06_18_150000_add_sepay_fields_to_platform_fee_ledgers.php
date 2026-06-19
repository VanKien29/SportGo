<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->char('system_bank_account_id', 36)->nullable()->after('amount_paid');
            $table->string('payment_code', 50)->nullable()->unique()->after('system_bank_account_id');
            $table->string('gateway_txn_id', 100)->nullable()->unique()->after('payment_code');
            $table->json('gateway_response')->nullable()->after('gateway_txn_id');

            $table->foreign('system_bank_account_id', 'vpfl_system_bank_account_foreign')
                ->references('id')
                ->on('system_bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->dropForeign('vpfl_system_bank_account_foreign');
            $table->dropUnique(['payment_code']);
            $table->dropUnique(['gateway_txn_id']);
            $table->dropColumn([
                'system_bank_account_id',
                'payment_code',
                'gateway_txn_id',
                'gateway_response',
            ]);
        });
    }
};
