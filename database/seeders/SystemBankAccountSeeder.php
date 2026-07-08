<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('system_bank_accounts')->insertOrIgnore([
            [
                'name'                => 'Tài khoản nhận tiền SportGo',
                'bank_name'           => 'TPBank',
                'bank_code'           => 'TPBank',
                'account_number'      => '72906999999',
                'account_holder_name' => 'NGUYEN VAN KIEN',
                'status'              => 'active',
                'is_default'          => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ]);

        if (! Schema::hasTable('system_wallet_balances')) {
            return;
        }

        $bankAccountId = DB::table('system_bank_accounts')
            ->where('account_number', '72906999999')
            ->value('id');

        if (! $bankAccountId) {
            return;
        }

        $existingId = DB::table('system_wallet_balances')
            ->where('system_bank_account_id', $bankAccountId)
            ->value('id');

        $payload = [
            'system_bank_account_id' => $bankAccountId,
            'current_balance' => 10000000,
            'bank_balance' => 10000000,
            'refund_reserved_balance' => 0,
            'voucher_reserved_balance' => 0,
            'last_synced_at' => now(),
            'bank_synced_at' => now(),
            'alert_threshold' => 1000000,
            'promotion_monthly_budget' => 2000000,
            'budget_period_type' => 'month',
            'is_alert_enabled' => true,
            'last_alerted_at' => null,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('system_wallet_balances')->where('id', $existingId)->update($payload);
            return;
        }
        $payload['created_at'] = now();
        DB::table('system_wallet_balances')->insert($payload);
    }
}
