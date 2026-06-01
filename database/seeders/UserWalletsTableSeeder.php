<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserWalletsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('user_wallets')) {
            return;
        }

        $users = DB::table('users')
            ->whereIn('username', ['user'])
            ->get(['id', 'username', 'full_name']);

        foreach ($users as $user) {
            $walletId = $this->upsertWallet($user->id);

            if (Schema::hasTable('user_payout_accounts')) {
                $this->upsertPayoutAccount($user->id);
            }

            if (Schema::hasTable('user_wallet_ledgers')) {
                $this->upsertOpeningLedger($walletId);
            }
        }
    }

    private function upsertWallet(string $userId): string
    {
        $existingId = DB::table('user_wallets')->where('user_id', $userId)->value('id');
        $payload = [
            'user_id' => $userId,
            'balance' => 0,
            'locked_balance' => 0,
            'status' => 'active',
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('user_wallets')->where('id', $existingId)->update($payload);
            return $existingId;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('user_wallets')->insert($payload);

        return $payload['id'];
    }

    private function upsertPayoutAccount(string $userId): void
    {
        $existingId = DB::table('user_payout_accounts')
            ->where('user_id', $userId)
            ->where('bank_account_number', '190000000006')
            ->value('id');

        $payload = [
            'user_id' => $userId,
            'bank_name' => 'Ngân hàng demo',
            'bank_account_number' => '190000000006',
            'bank_account_holder' => 'NGUOI DUNG SPORTGO',
            'bank_branch' => 'Hà Nội',
            'is_default' => true,
            'status' => 'active',
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('user_payout_accounts')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('user_payout_accounts')->insert($payload);
    }

    private function upsertOpeningLedger(string $walletId): void
    {
        $transactionCode = 'UW-OPENING-' . substr($walletId, 0, 8);
        $existingId = DB::table('user_wallet_ledgers')
            ->where('transaction_code', $transactionCode)
            ->value('id');

        $payload = [
            'user_wallet_id' => $walletId,
            'transaction_code' => $transactionCode,
            'type' => 'adjustment',
            'direction' => 'credit',
            'amount' => 0,
            'balance_before' => 0,
            'balance_after' => 0,
            'reference_type' => 'seed',
            'reference_id' => $walletId,
            'status' => 'completed',
            'note' => 'Số dư khởi tạo ví user demo.',
            'created_by' => null,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('user_wallet_ledgers')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('user_wallet_ledgers')->insert($payload);
    }
}
