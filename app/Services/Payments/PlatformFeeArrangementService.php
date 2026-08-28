<?php

namespace App\Services\Payments;

use App\Models\PlatformFeePaymentArrangement;
use App\Models\PlatformFeePromotion;
use App\Models\PlatformFeePromotionAssignment;
use App\Models\PlatformFeeServicePeriod;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformFeeArrangementService
{
    public function propose(VenueCluster $cluster, array $data, ?int $adminId): PlatformFeePaymentArrangement
    {
        $start = CarbonImmutable::parse($data['service_start'], config('platform_fee.timezone'))->startOfMonth();
        $months = (int) $data['service_months'];
        $end = $start->addMonthsNoOverflow($months)->subDay();
        $dueDate = CarbonImmutable::parse($data['payment_due_date'], config('platform_fee.timezone'));
        if ($dueDate->lte($end)) {
            throw ValidationException::withMessages([
                'payment_due_date' => ['Ngày thanh toán phải sau ngày kết thúc thời gian được gia hạn.'],
            ]);
        }

        return DB::transaction(function () use ($cluster, $data, $adminId, $start, $end, $dueDate, $months): PlatformFeePaymentArrangement {
            $cluster = VenueCluster::query()->with('owner')->whereKey($cluster->id)->lockForUpdate()->firstOrFail();
            if (! $cluster->owner_id || ! $cluster->owner) {
                throw ValidationException::withMessages(['venue_cluster_id' => ['Cụm sân chưa có chủ sân hợp lệ.']]);
            }
            if (PlatformFeePaymentArrangement::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('status', ['pending_owner_acceptance', 'active', 'overdue'])
                ->exists()) {
                throw ValidationException::withMessages(['venue_cluster_id' => ['Cụm sân đang có một thỏa thuận trả chậm chưa kết thúc.']]);
            }

            $quotes = [];
            $total = 0.0;
            for ($offset = 0; $offset < $months; $offset++) {
                $periodStart = $start->addMonthsNoOverflow($offset);
                $periodEnd = $periodStart->endOfMonth()->startOfDay();
                $this->assertNoOverlap($cluster->id, $periodStart, $periodEnd);
                $quote = app(PlatformFeePricingService::class)->quote($cluster, $periodStart, $periodEnd);
                if (! ($quote['valid'] ?? false)) {
                    throw ValidationException::withMessages(['service_start' => [(string) ($quote['error'] ?? 'Không tính được phí cho kỳ trả chậm.')]]);
                }
                $quotes[] = $quote;
                $total += (float) $quote['net_amount'];
            }
            $total = round($total, 2);

            $arrangement = PlatformFeePaymentArrangement::query()->create([
                'code' => $this->nextCode(),
                'venue_cluster_id' => $cluster->id,
                'owner_id' => $cluster->owner_id,
                'status' => 'pending_owner_acceptance',
                'arrangement_type' => 'secured_deferred',
                'service_months' => $months,
                'service_start' => $start->toDateString(),
                'service_end' => $end->toDateString(),
                'payment_due_date' => $dueDate->toDateString(),
                'credit_limit' => $total,
                'total_amount' => $total,
                'secured_amount' => 0,
                'reason' => trim($data['reason']),
                'admin_note' => $data['admin_note'] ?? null,
                'proposed_by' => $adminId,
                'approved_by' => $adminId,
                'approved_at' => now(),
                'metadata' => ['no_prepay_discount' => true],
            ]);

            foreach ($quotes as $quote) {
                $key = sprintf(
                    'arrangement:%s:%s:%s',
                    $arrangement->id,
                    $quote['period_start']->toDateString(),
                    $quote['period_end']->toDateString(),
                );
                $ledger = VenuePlatformFeeLedger::query()->create([
                    'venue_cluster_id' => $cluster->id,
                    'creation_source' => 'admin_arrangement',
                    'automation_key' => $key,
                    'tier_id' => $quote['tier']->id,
                    'plan_version_id' => $quote['plan']->id,
                    'promotion_id' => $quote['promotion']?->id,
                    'payment_arrangement_id' => $arrangement->id,
                    'tier_name_snapshot' => $quote['tier']->name,
                    'tier_min_courts_snapshot' => $quote['tier']->min_courts,
                    'tier_max_courts_snapshot' => $quote['tier']->max_courts,
                    'court_count' => $quote['court_count'],
                    'billing_cycle' => 'monthly',
                    'period_months' => 1,
                    'period_start' => $quote['period_start']->toDateString(),
                    'period_end' => $quote['period_end']->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'original_due_date' => $quote['period_end']->toDateString(),
                    'price_per_court_month' => $quote['tier']->price_per_court_month,
                    'discount_percent' => 0,
                    'pricing_snapshotted_at' => now(),
                    'base_amount' => $quote['base_amount'],
                    'prepay_discount_amount' => 0,
                    'promotion_discount_amount' => $quote['promotion_discount_amount'],
                    'waiver_amount' => 0,
                    'settlement_type' => 'deferred',
                    'settlement_reason' => 'Thỏa thuận trả chậm '.$arrangement->code.'.',
                    'amount_due' => $quote['net_amount'],
                    'amount_paid' => 0,
                    'payment_proof_status' => 'none',
                    'status' => 'awaiting_acceptance',
                ]);
                PlatformFeeServicePeriod::query()->create([
                    'venue_cluster_id' => $cluster->id,
                    'ledger_id' => $ledger->id,
                    'plan_version_id' => $quote['plan']->id,
                    'tier_id' => $quote['tier']->id,
                    'purpose' => 'deferred',
                    'status' => 'reserved',
                    'period_start' => $quote['period_start']->toDateString(),
                    'period_end' => $quote['period_end']->toDateString(),
                    'court_count' => $quote['court_count'],
                    'price_per_court_month' => $quote['tier']->price_per_court_month,
                    'base_amount' => $quote['base_amount'],
                    'promotion_discount_amount' => $quote['promotion_discount_amount'],
                    'net_amount' => $quote['net_amount'],
                    'idempotency_key' => $key,
                    'calculation_snapshot' => ['arrangement_code' => $arrangement->code, 'no_prepay_discount' => true],
                ]);
                $arrangement->ledgers()->attach($ledger->id, ['original_due_date' => $ledger->original_due_date]);
            }

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers.planVersion', 'holds']);
        }, 3);
    }

    public function accept(PlatformFeePaymentArrangement $arrangement, int $ownerId): PlatformFeePaymentArrangement
    {
        return DB::transaction(function () use ($arrangement, $ownerId): PlatformFeePaymentArrangement {
            $arrangement = PlatformFeePaymentArrangement::query()
                ->with('ledgers')
                ->whereKey($arrangement->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $arrangement->owner_id !== $ownerId) {
                abort(403, 'Bạn không có quyền xác nhận thỏa thuận này.');
            }
            if ($arrangement->status !== 'pending_owner_acceptance') {
                abort(409, 'Thỏa thuận không còn chờ chủ sân xác nhận.');
            }

            $wallet = \App\Models\OwnerWallet::query()
                ->where('owner_id', $ownerId)
                ->where('venue_cluster_id', $arrangement->venue_cluster_id)
                ->lockForUpdate()
                ->first();
            $available = $wallet ? app(PlatformFeeWalletService::class)->withdrawableAmount($wallet) : 0.0;
            if ($available + 0.01 < (float) $arrangement->total_amount) {
                throw ValidationException::withMessages([
                    'balance' => ['Số dư chủ sân chưa đủ để bảo đảm toàn bộ khoản trả chậm; không thể xác nhận.'],
                ]);
            }

            foreach ($arrangement->ledgers as $ledger) {
                $this->consumeReservedPromotion($ledger);
                $ledger->forceFill([
                    'status' => (float) $ledger->amount_due > 0 ? 'pending' : 'settled_zero',
                ])->save();
                $ledger->servicePeriods()->update(['status' => (float) $ledger->amount_due > 0 ? 'issued' : 'settled_zero']);
                if ((float) $ledger->amount_due > 0) {
                    app(PlatformFeeWalletService::class)->ensureLedgerHold(
                        $ledger,
                        'Bảo đảm cho thỏa thuận trả chậm '.$arrangement->code.'.',
                    );
                }
            }

            $arrangement->forceFill([
                'status' => 'active',
                'secured_amount' => $arrangement->total_amount,
                'owner_accepted_by' => $ownerId,
                'owner_accepted_at' => now(),
                'metadata' => array_merge($arrangement->metadata ?? [], ['promotions_consumed' => true]),
            ])->save();

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers.planVersion', 'holds']);
        }, 3);
    }

    public function cancel(PlatformFeePaymentArrangement $arrangement, ?int $actorId): PlatformFeePaymentArrangement
    {
        return DB::transaction(function () use ($arrangement, $actorId): PlatformFeePaymentArrangement {
            $arrangement = PlatformFeePaymentArrangement::query()->with('ledgers')->whereKey($arrangement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($arrangement->status, ['pending_owner_acceptance', 'active'], true)) {
                abort(409, 'Thỏa thuận đã kết thúc nên không thể hủy.');
            }
            if ($arrangement->ledgers->contains(fn ($ledger): bool => (float) $ledger->amount_paid > 0 || $ledger->status === 'paid')) {
                abort(409, 'Thỏa thuận đã phát sinh thanh toán; phải đối soát thay vì hủy.');
            }

            $promotionsWereConsumed = (bool) data_get($arrangement->metadata, 'promotions_consumed', false);

            foreach ($arrangement->ledgers as $ledger) {
                if ($promotionsWereConsumed) {
                    $this->releaseConsumedPromotion($ledger);
                }
                app(PlatformFeeWalletService::class)->releaseLedgerHold($ledger, $actorId);
                $ledger->servicePeriods()->update(['status' => 'voided']);
                $ledger->forceFill([
                    'status' => 'voided',
                    'voided_by' => $actorId,
                    'voided_at' => now(),
                    'settlement_reason' => 'Đã hủy thỏa thuận trả chậm '.$arrangement->code.'.',
                ])->save();
            }
            $arrangement->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers', 'holds']);
        }, 3);
    }

    public function syncSettlement(VenuePlatformFeeLedger $ledger): void
    {
        if (! $ledger->payment_arrangement_id) {
            return;
        }

        $arrangement = PlatformFeePaymentArrangement::query()
            ->whereKey($ledger->payment_arrangement_id)
            ->lockForUpdate()
            ->first();
        if (! $arrangement || ! in_array($arrangement->status, ['active', 'overdue'], true)) {
            return;
        }

        $hasOutstanding = $arrangement->ledgers()
            ->whereNotIn('venue_platform_fee_ledgers.status', ['paid', 'settled_zero', 'cancelled', 'voided', 'written_off'])
            ->exists();
        if (! $hasOutstanding) {
            $arrangement->forceFill([
                'status' => 'fulfilled',
                'secured_amount' => 0,
                'fulfilled_at' => now(),
            ])->save();
        }
    }

    private function consumeReservedPromotion(VenuePlatformFeeLedger $ledger): void
    {
        $amount = (float) $ledger->promotion_discount_amount;
        if (! $ledger->promotion_id || $amount <= 0) {
            return;
        }

        $promotion = PlatformFeePromotion::query()->whereKey($ledger->promotion_id)->lockForUpdate()->firstOrFail();
        if ($promotion->budget_amount !== null
            && (float) $promotion->budget_amount - (float) $promotion->spent_amount + 0.01 < $amount) {
            throw ValidationException::withMessages([
                'promotion' => ['Ngân sách khuyến mại đã thay đổi; admin cần hủy và tạo lại thỏa thuận để chốt số tiền mới.'],
            ]);
        }

        $assignment = PlatformFeePromotionAssignment::query()
            ->where('promotion_id', $promotion->id)
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->where('status', 'active')
            ->where('remaining_cycles', '>', 0)
            ->lockForUpdate()
            ->first();
        if (! $promotion->applies_to_all_clusters && ! $assignment) {
            throw ValidationException::withMessages([
                'promotion' => ['Lượt khuyến mại của cụm sân đã hết; admin cần hủy và tạo lại thỏa thuận.'],
            ]);
        }

        $promotion->increment('spent_amount', $amount);
        if ($assignment) {
            $remaining = max((int) $assignment->remaining_cycles - 1, 0);
            $assignment->forceFill([
                'remaining_cycles' => $remaining,
                'status' => $remaining === 0 ? 'consumed' : 'active',
                'consumed_at' => $remaining === 0 ? now() : null,
            ])->save();
        }
    }

    private function releaseConsumedPromotion(VenuePlatformFeeLedger $ledger): void
    {
        $amount = (float) $ledger->promotion_discount_amount;
        if (! $ledger->promotion_id || $amount <= 0) {
            return;
        }

        $promotion = PlatformFeePromotion::query()->whereKey($ledger->promotion_id)->lockForUpdate()->first();
        if ($promotion) {
            $promotion->forceFill(['spent_amount' => max((float) $promotion->spent_amount - $amount, 0)])->save();
        }

        $assignment = PlatformFeePromotionAssignment::query()
            ->where('promotion_id', $ledger->promotion_id)
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->lockForUpdate()
            ->first();
        if ($assignment) {
            $assignment->forceFill([
                'remaining_cycles' => (int) $assignment->remaining_cycles + 1,
                'status' => 'active',
                'consumed_at' => null,
            ])->save();
        }
    }

    private function assertNoOverlap(int $clusterId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if (PlatformFeeServicePeriod::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', '!=', 'voided')
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->exists()) {
            throw ValidationException::withMessages(['service_start' => ['Khoảng thời gian trả chậm đang trùng một kỳ phí đã phát hành hoặc đã giữ chỗ.']]);
        }
    }

    private function nextCode(): string
    {
        $nextId = (int) PlatformFeePaymentArrangement::query()->lockForUpdate()->max('id') + 1;

        return sprintf('PFA-%s-%06d', now()->format('Ymd'), $nextId);
    }
}
