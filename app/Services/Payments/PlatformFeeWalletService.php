<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\PlatformFeeWalletHold;
use App\Models\VenueAccessRestriction;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlatformFeeWalletService
{
    public function withdrawableAmount(OwnerWallet $wallet, ?int $excludingLedgerId = null): float
    {
        $held = PlatformFeeWalletHold::query()
            ->where('owner_wallet_id', $wallet->id)
            ->where('status', 'active')
            ->when($excludingLedgerId, fn ($query) => $query->where(function ($holdQuery) use ($excludingLedgerId): void {
                $holdQuery->whereNull('ledger_id')->orWhere('ledger_id', '!=', $excludingLedgerId);
            }))
            ->sum('amount');

        return round(max((float) $wallet->available_balance - (float) $held, 0), 2);
    }

    public function activeHoldAmount(OwnerWallet $wallet): float
    {
        return round((float) PlatformFeeWalletHold::query()
            ->where('owner_wallet_id', $wallet->id)
            ->where('status', 'active')
            ->sum('amount'), 2);
    }

    public function ensureLedgerHold(VenuePlatformFeeLedger $ledger, string $reason): PlatformFeeWalletHold
    {
        return DB::transaction(function () use ($ledger, $reason): PlatformFeeWalletHold {
            $ledger = VenuePlatformFeeLedger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();
            if (in_array($ledger->status, ['paid', 'settled_zero', 'cancelled', 'voided', 'written_off'], true)) {
                throw new RuntimeException('Kỳ phí đã kết thúc nên không cần tạm giữ số dư.');
            }

            $wallet = OwnerWallet::query()
                ->where('venue_cluster_id', $ledger->venue_cluster_id)
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                throw new RuntimeException('Cụm sân chưa có số dư chủ sân để tạm giữ.');
            }

            $amount = round(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 2);
            return PlatformFeeWalletHold::query()->updateOrCreate(
                ['ledger_id' => $ledger->id],
                [
                    'owner_wallet_id' => $wallet->id,
                    'owner_id' => $wallet->owner_id,
                    'venue_cluster_id' => $ledger->venue_cluster_id,
                    'arrangement_id' => $ledger->payment_arrangement_id,
                    'amount' => $amount,
                    'status' => 'active',
                    'reason' => $reason,
                    'starts_at' => now(),
                    'released_at' => null,
                    'released_by' => null,
                    'metadata' => ['ledger_status' => $ledger->status],
                ],
            );
        });
    }

    public function releaseLedgerHold(VenuePlatformFeeLedger $ledger, ?int $actorId = null): void
    {
        PlatformFeeWalletHold::query()
            ->where('ledger_id', $ledger->id)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $actorId,
            ]);
    }

    public function payFromBalance(VenuePlatformFeeLedger $ledger, int $ownerId, bool $automatic = false): VenuePlatformFeeLedger
    {
        return DB::transaction(function () use ($ledger, $ownerId, $automatic): VenuePlatformFeeLedger {
            $ledger = VenuePlatformFeeLedger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();
            if (in_array($ledger->status, ['paid', 'settled_zero', 'cancelled', 'voided', 'written_off'], true)) {
                throw new RuntimeException('Kỳ phí đã kết thúc hoặc đã bị hủy.');
            }

            $amount = round(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 2);
            if ($amount <= 0) {
                throw new RuntimeException('Kỳ phí không còn số tiền cần thanh toán.');
            }

            $existing = OwnerWalletLedger::query()
                ->where('reference_type', 'platform_fee')
                ->where('reference_id', (string) $ledger->id)
                ->where('status', 'completed')
                ->first();
            if ($existing) {
                return $ledger;
            }

            $wallet = OwnerWallet::query()
                ->where('owner_id', $ownerId)
                ->where('venue_cluster_id', $ledger->venue_cluster_id)
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                throw new RuntimeException('Cụm sân chưa có số dư chủ sân để thanh toán.');
            }

            $spendable = $this->withdrawableAmount($wallet, $ledger->id);
            if ($amount > $spendable + 0.01) {
                throw new RuntimeException(sprintf(
                    'Số dư có thể dùng sau các khoản tạm giữ chỉ còn %s đ.',
                    number_format($spendable, 0, ',', '.'),
                ));
            }

            $balanceBefore = (float) $wallet->available_balance;
            $balanceAfter = round($balanceBefore - $amount, 2);
            $wallet->forceFill(['available_balance' => $balanceAfter])->save();

            OwnerWalletLedger::query()->create([
                'owner_wallet_id' => $wallet->id,
                'owner_id' => $wallet->owner_id,
                'venue_cluster_id' => $wallet->venue_cluster_id,
                'type' => 'debit',
                'direction' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'reference_code' => 'PF-'.$ledger->id,
                'reference_type' => 'platform_fee',
                'reference_id' => (string) $ledger->id,
                'transaction_code' => 'OWPF-'.substr(hash('sha256', (string) $ledger->id), 0, 32),
                'description' => 'Thanh toán phí nền tảng kỳ '.$ledger->period_start?->format('d/m/Y').' - '.$ledger->period_end?->format('d/m/Y').'.',
                'note' => $automatic ? 'Tự động thanh toán theo cấu hình của chủ sân.' : 'Chủ sân xác nhận thanh toán bằng số dư.',
                'metadata' => [
                    'source' => $automatic ? 'platform_fee_auto_pay' : 'platform_fee_owner_payment',
                    'ledger_id' => $ledger->id,
                    'plan_version_id' => $ledger->plan_version_id,
                ],
            ]);

            $ledger->forceFill([
                'amount_paid' => $ledger->amount_due,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_confirmed_at' => now(),
                'payment_confirmed_by' => $automatic ? null : $ownerId,
                'gateway_response' => [
                    'method' => 'owner_balance',
                    'wallet_id' => $wallet->id,
                    'automatic' => $automatic,
                ],
            ])->save();

            $this->releaseLedgerHold($ledger, $ownerId);
            $this->clearRestrictionWhenSettled($ledger);
            AuditLog::query()->create([
                'actor_id' => $automatic ? null : $ownerId,
                'actor_type' => $automatic ? 'system' : 'owner',
                'module' => 'platform_fee',
                'action' => $automatic ? 'platform_fee.auto_paid_from_balance' : 'platform_fee.paid_from_balance',
                'entity_type' => 'venue_platform_fee_ledgers',
                'entity_id' => $ledger->id,
                'new_values' => ['amount_paid' => $amount, 'wallet_id' => $wallet->id],
                'context' => $automatic ? 'system' : 'owner',
                'metadata' => ['venue_cluster_id' => $ledger->venue_cluster_id],
            ]);

            return $ledger->fresh(['tier', 'planVersion', 'servicePeriods']);
        }, 3);
    }

    private function clearRestrictionWhenSettled(VenuePlatformFeeLedger $ledger): void
    {
        $hasDebt = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->whereKeyNot($ledger->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereRaw('amount_paid < amount_due')
            ->exists();
        if (! $hasDebt) {
            VenueAccessRestriction::query()
                ->where('venue_cluster_id', $ledger->venue_cluster_id)
                ->where('restriction_type', 'platform_fee_overdue')
                ->where('status', 'active')
                ->update(['status' => 'expired', 'ends_at' => now()]);
        }
    }
}
