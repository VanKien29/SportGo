<?php

namespace Database\Seeders;

use App\Models\PartnerSettlement;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerSettlementsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_settlements') || ! Schema::hasTable('partner_termination_requests')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        if (! $admin) {
            return;
        }

        $this->seedSettlement(
            'SETTLE-CG-001',
            'TERM-MUTUAL-CG-001',
            2500000,
            450000,
            300000,
            650000,
            200000,
            -50000,
            1900000,
            0,
            'payout_created',
            'Quyết toán còn tiền trả owner, có phí nền tảng dư được hoàn và đã tạo yêu cầu rút tiền tự động.',
            $admin,
        );

        $this->seedSettlement(
            'SETTLE-CG-DEBT',
            'TERM-SPORTGO-CG-DONE',
            200000,
            0,
            0,
            850000,
            300000,
            50000,
            0,
            1000000,
            'completed',
            'Quyết toán còn công nợ chủ sân phải trả SportGo.',
            $admin,
        );

        $this->seedSettlement(
            'SETTLE-CG-PROCESSING',
            'TERM-MUTUAL-CG-SETTLE',
            1200000,
            150000,
            100000,
            200000,
            0,
            0,
            0,
            0,
            'pending_approval',
            'Quyết toán đang chờ admin duyệt, chưa tạo yêu cầu rút tiền.',
            $admin,
        );
    }

    private function seedSettlement(
        string $settlementCode,
        string $terminationCode,
        int $availableAmount,
        int $pendingAmount,
        int $feeRefundAmount,
        int $unpaidFeeAmount,
        int $penaltyAmount,
        int $adjustmentAmount,
        int $payableToOwner,
        int $receivableFromOwner,
        string $status,
        string $note,
        User $admin
    ): void {
        $request = PartnerTerminationRequest::query()->where('termination_code', $terminationCode)->first();

        if (! $request) {
            return;
        }

        PartnerSettlement::query()->updateOrCreate(
            ['settlement_code' => $settlementCode],
            [
                'partner_termination_request_id' => $request->id,
                'partner_contract_id' => $request->partner_contract_id,
                'owner_id' => $request->owner_id,
                'venue_cluster_id' => $request->venue_cluster_id,
                'owner_wallet_available_amount' => $availableAmount,
                'owner_wallet_pending_amount' => $pendingAmount,
                'platform_fee_remaining_refund_amount' => $feeRefundAmount,
                'unpaid_platform_fee_amount' => $unpaidFeeAmount,
                'penalty_amount' => $penaltyAmount,
                'adjustment_amount' => $adjustmentAmount,
                'final_payable_to_owner' => $payableToOwner,
                'final_receivable_from_owner' => $receivableFromOwner,
                'status' => $status,
                'calculated_by' => $admin->id,
                'approved_by' => in_array($status, ['approved', 'payout_created', 'completed'], true) ? $admin->id : null,
                'approved_at' => in_array($status, ['approved', 'payout_created', 'completed'], true) ? now()->subDays(3) : null,
                'note' => $note,
            ],
        );
    }
}
