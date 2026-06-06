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
                'WDSEEDWDR001',
                now()->subDays(4),
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
                null,
                null,
                now()->subHours(6),
            ],
            [
                'WRADMREV1',
                250000,
                'reviewing',
                'Rút doanh thu đang được kiểm tra đối soát.',
                null,
                null,
                'Đang kiểm tra số dư và tài khoản nhận tiền.',
                null,
                null,
                null,
                null,
                null,
                null,
                now()->subHours(3),
            ],
            [
                'WRADMAPPR1',
                450000,
                'approved',
                'Yêu cầu đã duyệt, dùng để test QR và export MB bulk.',
                $admin?->id,
                now()->subHours(2),
                'Đã duyệt chuyển khoản cho chủ sân.',
                null,
                null,
                null,
                null,
                'WDSEEDWDR002',
                now()->subHours(2),
                now()->subHours(2),
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
                null,
                null,
                now()->subDays(3),
            ],
            [
                'WRADMCANC1',
                150000,
                'cancelled',
                'Owner hủy yêu cầu trước khi admin xử lý.',
                null,
                null,
                null,
                'Chủ sân đã hủy yêu cầu rút tiền.',
                null,
                null,
                null,
                null,
                null,
                now()->subDays(1),
            ],
        ];

        foreach ($requests as [$code, $amount, $status, $ownerNote, $reviewedBy, $reviewedAt, $reviewNote, $statusReason, $completedBy, $completedAt, $transferReference, $payoutTransferCode, $payoutQrCreatedAt, $requestedAt]) {
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
                    'payout_transfer_code' => $payoutTransferCode,
                    'payout_qr_created_at' => $payoutQrCreatedAt,
                    'metadata' => ['source' => 'seed'],
                    'requested_at' => $requestedAt,
                ]
            );
        }
    }
}
