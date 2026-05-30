<?php

namespace Database\Seeders;

use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OwnerWithdrawalRequestsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('owner_withdrawal_requests') || ! Schema::hasTable('owner_wallets') || ! Schema::hasTable('owner_bank_accounts')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();
        $wallet = $owner ? OwnerWallet::query()->where('owner_id', $owner->id)->first() : null;
        $bankAccount = $owner ? OwnerBankAccount::query()->where('owner_id', $owner->id)->where('status', 'active')->first() : null;

        if (! $owner || ! $wallet || ! $bankAccount) {
            return;
        }

        $requests = [
            [
                'WRADMCOMP1',
                700000,
                'completed',
                'Rút doanh thu tuần trước.',
                $admin?->id,
                now()->subDays(5),
                'Đã kiểm tra số dư và thông tin ngân hàng.',
                null,
                $admin?->id,
                now()->subDays(4),
                'VCB202605260001',
                now()->subDays(6),
            ],
            [
                'WRADMPEND1',
                300000,
                'pending',
                'Rút doanh thu cuối tuần.',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                now()->subHours(6),
            ],
            [
                'WRADMREJ1',
                200000,
                'rejected',
                'Rút thử với tài khoản cũ.',
                $admin?->id,
                now()->subDays(2),
                null,
                'Tài khoản nhận tiền đã bị thay đổi, vui lòng chọn tài khoản đang hoạt động.',
                null,
                null,
                null,
                now()->subDays(3),
            ],
        ];

        foreach ($requests as [$code, $amount, $status, $ownerNote, $reviewedBy, $reviewedAt, $reviewNote, $statusReason, $completedBy, $completedAt, $transferReference, $requestedAt]) {
            OwnerWithdrawalRequest::query()->updateOrCreate(
                ['request_code' => $code],
                [
                    'owner_id' => $owner->id,
                    'owner_wallet_id' => $wallet->id,
                    'owner_bank_account_id' => $bankAccount->id,
                    'amount' => $amount,
                    'status' => $status,
                    'owner_note' => $ownerNote,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_note' => $reviewNote,
                    'status_reason' => $statusReason,
                    'completed_by' => $completedBy,
                    'completed_at' => $completedAt,
                    'transfer_reference' => $transferReference,
                    'metadata' => ['source' => 'seed'],
                    'requested_at' => $requestedAt,
                ]
            );
        }
    }
}
