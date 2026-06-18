<?php

namespace App\Services\Partner;

use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerHistory;
use App\Models\User;
use App\Models\VenuePlatformFeeLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletRefundService
{
    public function initiateRefund(string $ownerId, string $venueClusterId, User $admin): ?OwnerWithdrawalRequest
    {
        return DB::transaction(function () use ($ownerId, $venueClusterId, $admin) {
            $wallet = OwnerWallet::where('owner_id', $ownerId)
                ->where('venue_cluster_id', $venueClusterId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return null;
            }

            // 1. Hoàn tiền phí hệ thống chưa sử dụng
            $activeFeeLedgers = VenuePlatformFeeLedger::where('venue_cluster_id', $venueClusterId)
                ->where('status', 'paid')
                ->where('period_end', '>', Carbon::now())
                ->get();

            $totalRefundAmount = 0;

            foreach ($activeFeeLedgers as $feeLedger) {
                $start = Carbon::parse($feeLedger->period_start);
                $end = Carbon::parse($feeLedger->period_end);
                $now = Carbon::now();

                $totalDays = max(1, $start->diffInDays($end));
                
                if ($start->gt($now)) {
                    // Chưa đến kỳ hoạt động -> hoàn 100%
                    $unusedDays = $totalDays;
                } else {
                    $unusedDays = max(0, $now->diffInDays($end));
                }

                $refundAmount = ($feeLedger->amount_paid * $unusedDays) / $totalDays;
                
                if ($refundAmount > 0) {
                    $totalRefundAmount += $refundAmount;

                    OwnerWalletLedger::create([
                        'owner_wallet_id' => $wallet->id,
                        'owner_id' => $ownerId,
                        'venue_cluster_id' => $venueClusterId,
                        'type' => 'credit',
                        'amount' => $refundAmount,
                        'balance_before' => $wallet->available_balance,
                        'balance_after' => $wallet->available_balance + $refundAmount,
                        'reference_code' => 'REF-' . strtoupper(Str::random(8)),
                        'description' => "Hoàn tiền phí hệ thống chưa sử dụng ($unusedDays/ $totalDays ngày) do chấm dứt hợp đồng.",
                        'metadata' => [
                            'reference_type' => 'platform_fee_refund',
                            'reference_id' => $feeLedger->id,
                        ],
                    ]);
                }

                // Có thể cập nhật ledger status thành cancelled
                $feeLedger->update(['status' => 'cancelled']);
            }

            if ($totalRefundAmount > 0) {
                $wallet->available_balance += $totalRefundAmount;
                $wallet->total_earned += $totalRefundAmount; // Tuỳ nghiệp vụ
                $wallet->save();
            }

            // 2. Rút toàn bộ số dư
            if ($wallet->available_balance <= 0) {
                return null;
            }

            $amount = $wallet->available_balance;

            // Get owner bank account
            $bankAccount = \App\Models\OwnerBankAccount::where('owner_id', $ownerId)
                ->where('is_default', true)
                ->first();

            if (!$bankAccount) {
                $bankAccount = \App\Models\OwnerBankAccount::where('owner_id', $ownerId)->first();
            }

            if (!$bankAccount) {
                // Cannot auto-withdraw if there's no bank account
                // However, balance has been updated with the refund.
                return null;
            }

            $wallet->available_balance -= $amount;
            $wallet->save();

            $withdrawal = OwnerWithdrawalRequest::create([
                'request_code' => 'WR-' . strtoupper(Str::random(8)),
                'owner_id' => $ownerId,
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => 'pending',
                'owner_note' => 'Tự động rút tiền do thanh lý hợp đồng hợp tác',
                'requested_at' => now(),
            ]);

            OwnerWalletLedger::create([
                'owner_wallet_id' => $wallet->id,
                'owner_id' => $ownerId,
                'venue_cluster_id' => $venueClusterId,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $amount,
                'balance_after' => 0,
                'reference_code' => 'WDR-' . strtoupper(Str::random(8)),
                'description' => "Rút tiền tự động do chấm dứt hợp đồng.",
                'metadata' => [
                    'reference_type' => 'withdrawal',
                    'reference_id' => $withdrawal->id,
                ],
            ]);

            return $withdrawal;
        });
    }
}
