<?php

namespace Database\Seeders;

use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerSettlement;
use App\Models\PartnerSettlementItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerSettlementItemsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_settlement_items') || ! Schema::hasTable('partner_settlements')) {
            return;
        }

        PartnerSettlement::query()->get()->each(function (PartnerSettlement $settlement): void {
            foreach ($this->items($settlement) as [$type, $description, $amount, $direction, $referenceType, $referenceId]) {
                if ((float) $amount <= 0) {
                    continue;
                }

                PartnerSettlementItem::query()->updateOrCreate(
                    [
                        'partner_settlement_id' => $settlement->id,
                        'item_type' => $type,
                    ],
                    [
                        'description' => $description,
                        'amount' => $amount,
                        'direction' => $direction,
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                        'created_at' => now()->subDays(3),
                    ],
                );
            }
        });
    }

    private function items(PartnerSettlement $settlement): array
    {
        $adjustmentAmount = abs((float) $settlement->adjustment_amount);
        $adjustmentDirection = (float) $settlement->adjustment_amount >= 0
            ? 'payable_to_owner'
            : 'receivable_from_owner';

        return [
            ['owner_wallet_balance', 'Số dư ví owner còn có thể rút', $settlement->owner_wallet_available_amount, 'payable_to_owner', OwnerWallet::class, null],
            ['pending_withdrawal', 'Số tiền đang giữ cho yêu cầu rút chưa hoàn tất', $settlement->owner_wallet_pending_amount, 'receivable_from_owner', OwnerWithdrawalRequest::class, null],
            ['platform_fee_remaining_refund', 'Phí duy trì còn lại được hoàn cho owner', $settlement->platform_fee_remaining_refund_amount, 'payable_to_owner', null, null],
            ['unpaid_platform_fee', 'Phí duy trì còn nợ SportGo', $settlement->unpaid_platform_fee_amount, 'receivable_from_owner', null, null],
            ['penalty', 'Phí phạt theo điều khoản chấm dứt hợp đồng', $settlement->penalty_amount, 'receivable_from_owner', null, null],
            ['adjustment', 'Điều chỉnh theo biên bản đối soát', $adjustmentAmount, $adjustmentDirection, null, null],
        ];
    }
}
