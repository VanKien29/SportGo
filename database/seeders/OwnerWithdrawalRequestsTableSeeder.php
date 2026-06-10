<?php

namespace Database\Seeders;

use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerSettlement;
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
                'request_code' => 'WRADMCOMP1',
                'amount' => 700000,
                'status' => 'completed',
                'owner_note' => 'Rút doanh thu tuần trước.',
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now()->subDays(5),
                'review_note' => 'Đã kiểm tra số dư và thông tin ngân hàng.',
                'status_reason' => null,
                'completed_by' => $admin?->id,
                'completed_at' => now()->subDays(4),
                'transfer_reference' => 'VCB202605260001',
                'payout_transfer_code' => 'WDSEEDWDR001',
                'payout_qr_created_at' => now()->subDays(4),
                'requested_at' => now()->subDays(6),
                'source' => 'manual',
                'auto_created' => false,
            ],
            [
                'request_code' => 'WRADMPEND1',
                'amount' => 300000,
                'status' => 'pending',
                'owner_note' => 'Rút doanh thu cuối tuần.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'status_reason' => null,
                'completed_by' => null,
                'completed_at' => null,
                'transfer_reference' => null,
                'payout_transfer_code' => null,
                'payout_qr_created_at' => null,
                'requested_at' => now()->subHours(6),
                'source' => 'manual',
                'auto_created' => false,
            ],
            [
                'request_code' => 'WRADMREV1',
                'amount' => 250000,
                'status' => 'reviewing',
                'owner_note' => 'Rút doanh thu đang được kiểm tra đối soát.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => 'Đang kiểm tra số dư và tài khoản nhận tiền.',
                'status_reason' => null,
                'completed_by' => null,
                'completed_at' => null,
                'transfer_reference' => null,
                'payout_transfer_code' => null,
                'payout_qr_created_at' => null,
                'requested_at' => now()->subHours(3),
                'source' => 'manual',
                'auto_created' => false,
            ],
            [
                'request_code' => 'WRADMAPPR1',
                'amount' => 450000,
                'status' => 'approved',
                'owner_note' => 'Yêu cầu đã duyệt, dùng để test QR và export MB bulk.',
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now()->subHours(2),
                'review_note' => 'Đã duyệt chuyển khoản cho chủ sân.',
                'status_reason' => null,
                'completed_by' => null,
                'completed_at' => null,
                'transfer_reference' => null,
                'payout_transfer_code' => 'WDSEEDWDR002',
                'payout_qr_created_at' => now()->subHours(2),
                'requested_at' => now()->subHours(2),
                'source' => 'manual',
                'auto_created' => false,
            ],
            [
                'request_code' => 'WRADMREJ1',
                'amount' => 200000,
                'status' => 'rejected',
                'owner_note' => 'Rút thử với tài khoản cũ.',
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now()->subDays(2),
                'review_note' => null,
                'status_reason' => 'Tài khoản nhận tiền đã bị thay đổi, vui lòng chọn tài khoản đang hoạt động.',
                'completed_by' => null,
                'completed_at' => null,
                'transfer_reference' => null,
                'payout_transfer_code' => null,
                'payout_qr_created_at' => null,
                'requested_at' => now()->subDays(3),
                'source' => 'manual',
                'auto_created' => false,
            ],
            [
                'request_code' => 'WRADMCANC1',
                'amount' => 150000,
                'status' => 'cancelled',
                'owner_note' => 'Owner hủy yêu cầu trước khi admin xử lý.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'status_reason' => 'Chủ sân đã hủy yêu cầu rút tiền.',
                'completed_by' => null,
                'completed_at' => null,
                'transfer_reference' => null,
                'payout_transfer_code' => null,
                'payout_qr_created_at' => null,
                'requested_at' => now()->subDays(1),
                'source' => 'manual',
                'auto_created' => false,
            ],
        ];

        foreach ($requests as $row) {
            $this->seedRequest($owner, $wallet, $bankAccount, $row);
        }

        $this->seedSettlementWithdrawal($owner, $wallet, $bankAccount, $admin);
    }

    private function seedSettlementWithdrawal(User $owner, OwnerWallet $wallet, OwnerBankAccount $bankAccount, ?User $admin): void
    {
        if (! Schema::hasColumn('owner_withdrawal_requests', 'source')) {
            return;
        }

        $settlement = PartnerSettlement::query()->where('settlement_code', 'SETTLE-CG-001')->first();

        if (! $settlement || $settlement->final_payable_to_owner <= 0) {
            return;
        }

        $this->seedRequest($owner, $wallet, $bankAccount, [
            'request_code' => 'WRSETTLECG001',
            'amount' => $settlement->final_payable_to_owner,
            'status' => 'approved',
            'owner_note' => 'Yêu cầu rút tiền tự tạo từ quyết toán chấm dứt hợp tác.',
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now()->subDays(2),
            'review_note' => 'Yêu cầu được tạo sau khi admin duyệt biên bản quyết toán.',
            'status_reason' => null,
            'completed_by' => null,
            'completed_at' => null,
            'transfer_reference' => null,
            'payout_transfer_code' => 'WDSETTLECG001',
            'payout_qr_created_at' => now()->subDays(2),
            'requested_at' => now()->subDays(2),
            'source' => 'partner_termination_settlement',
            'auto_created' => true,
            'partner_settlement_id' => $settlement->id,
            'partner_termination_request_id' => $settlement->partner_termination_request_id,
        ]);
    }

    private function seedRequest(User $owner, OwnerWallet $wallet, OwnerBankAccount $bankAccount, array $row): void
    {
        $values = [
            'owner_id' => $owner->id,
            'owner_wallet_id' => $wallet->id,
            'owner_bank_account_id' => $bankAccount->id,
            'amount' => $row['amount'],
            'status' => $row['status'],
            'owner_note' => $row['owner_note'],
            'reviewed_by' => $row['reviewed_by'],
            'reviewed_at' => $row['reviewed_at'],
            'review_note' => $row['review_note'],
            'status_reason' => $row['status_reason'],
            'completed_by' => $row['completed_by'],
            'completed_at' => $row['completed_at'],
            'transfer_reference' => $row['transfer_reference'],
            'metadata' => ['source' => 'seed'],
            'requested_at' => $row['requested_at'],
        ];

        if (Schema::hasColumn('owner_withdrawal_requests', 'source')) {
            $values['source'] = $row['source'];
            $values['auto_created'] = $row['auto_created'];
            $values['partner_settlement_id'] = $row['partner_settlement_id'] ?? null;
            $values['partner_termination_request_id'] = $row['partner_termination_request_id'] ?? null;
        }

        if (Schema::hasColumn('owner_withdrawal_requests', 'payout_transfer_code')) {
            $values['payout_transfer_code'] = $row['payout_transfer_code'];
            $values['payout_qr_created_at'] = $row['payout_qr_created_at'];
        }

        OwnerWithdrawalRequest::query()->updateOrCreate(
            ['request_code' => $row['request_code']],
            $values,
        );
    }
}
