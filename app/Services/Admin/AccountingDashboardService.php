<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingDashboardService
{
    public function payload(string $periodType): array
    {
        [$start, $end, $label] = $this->periodRange($periodType);

        $bookingCollected = $this->paidBookingPayments($start, $end);
        $platformFeeRevenue = $this->paidPlatformFees($start, $end);
        $vipRevenue = $this->paidVipPayments($start, $end);
        $systemRevenue = round($platformFeeRevenue + $vipRevenue, 2);
        $voucherCost = $this->systemLedgerAmount('out', ['voucher_subsidy'], [], $start, $end);
        $ownerWithdrawals = $this->completedOwnerWithdrawals($start, $end);
        $userWithdrawals = $this->paidUserWithdrawals($start, $end);
        $withdrawalTotal = round($ownerWithdrawals + $userWithdrawals, 2);
        $ownerDebt = $this->ownerDebtTotal();
        $customerDebt = $this->customerDebtTotal();
        $cashOut = round($withdrawalTotal + $voucherCost, 2);
        $managedTotal = round($bookingCollected + $systemRevenue - $cashOut, 2);
        $systemCashBalance = round($managedTotal - $ownerDebt - $customerDebt, 2);

        return [
            'period_type' => $periodType,
            'period_label' => $label,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'overview' => [
                'system_cash_balance' => $systemCashBalance,
                'system_revenue' => $systemRevenue,
                'owner_debt_total' => $ownerDebt,
                'customer_debt_total' => $customerDebt,
                'managed_total' => round($systemCashBalance + $ownerDebt + $customerDebt, 2),
                'booking_collected_total' => $bookingCollected,
                'withdrawal_total' => $withdrawalTotal,
                'voucher_cost_total' => $voucherCost,
                'platform_fee_revenue_total' => $platformFeeRevenue,
                'membership_revenue_total' => $vipRevenue,
                'cash_out_total' => $cashOut,
            ],
            'charts' => [
                'cash_flow' => $this->cashFlowSeries($periodType, $start, $end),
                'managed_composition' => [
                    ['label' => 'Tiền hệ thống còn lại', 'value' => $systemCashBalance, 'group' => 'cash'],
                    ['label' => 'Công nợ chủ sân', 'value' => $ownerDebt, 'group' => 'owner_debt'],
                    ['label' => 'Công nợ khách hàng', 'value' => $customerDebt, 'group' => 'customer_debt'],
                ],
            ],
            'tables' => [
                'booking_ledgers' => $this->bookingLedgers($start, $end),
                'withdrawal_ledgers' => $this->withdrawalLedgers($start, $end),
                'owner_debts' => $this->ownerDebts(),
                'customer_debts' => $this->customerDebts(),
                'voucher_ledgers' => $this->voucherLedgers($start, $end),
                'revenue_ledgers' => $this->revenueLedgers($start, $end),
            ],
        ];
    }

    private function periodRange(string $periodType): array
    {
        $now = CarbonImmutable::now();

        return match ($periodType) {
            'week' => [$now->startOfWeek(), $now->endOfWeek(), 'Tuần này'],
            'year' => [$now->startOfYear(), $now->endOfYear(), 'Năm ' . $now->year],
            default => [$now->startOfMonth(), $now->endOfMonth(), 'Tháng ' . $now->format('m/Y')],
        };
    }

    private function paidBookingPayments(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): float
    {
        $query = DB::table('payments')
            ->where('status', 'paid')
            ->whereNotIn(DB::raw('LOWER(method)'), ['cash', 'direct', 'offline']);

        if (Schema::hasColumn('payments', 'payment_context')) {
            $query->where(function ($builder): void {
                $builder->whereNull('payment_context')->orWhere('payment_context', 'booking');
            });
        }

        $this->between($query, 'paid_at', $start, $end);

        return round((float) $query->sum('amount'), 2);
    }

    private function paidPlatformFees(CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) DB::table('venue_platform_fee_ledgers')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount_paid'), 2);
    }

    private function paidVipPayments(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! Schema::hasColumn('payments', 'payment_context')) {
            return 0.0;
        }

        return round((float) DB::table('payments')
            ->where('status', 'paid')
            ->where('payment_context', 'vip_subscription')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount'), 2);
    }

    private function completedOwnerWithdrawals(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): float
    {
        $query = DB::table('owner_withdrawal_requests')->where('status', 'completed');
        $this->between($query, 'completed_at', $start, $end);

        return round((float) $query->sum('amount'), 2);
    }

    private function paidUserWithdrawals(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): float
    {
        $query = DB::table('user_withdrawal_requests')->where('status', 'paid');
        $this->between($query, 'paid_at', $start, $end);

        return round((float) $query->sum('amount'), 2);
    }

    private function ownerDebtTotal(): float
    {
        return round((float) DB::table('owner_wallets')
            ->sum(DB::raw('available_balance + pending_withdrawal_balance')), 2);
    }

    private function customerDebtTotal(): float
    {
        return round((float) DB::table('user_wallets')
            ->sum(DB::raw('balance + locked_balance')), 2);
    }

    private function cashFlowSeries(string $periodType, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $buckets = [];
        $cursor = $periodType === 'year' ? $start->startOfMonth() : $start->startOfDay();

        while ($cursor <= $end) {
            $bucketStart = $periodType === 'year' ? $cursor->startOfMonth() : $cursor->startOfDay();
            $bucketEnd = $periodType === 'year' ? $cursor->endOfMonth() : $cursor->endOfDay();
            $buckets[] = [
                'label' => $periodType === 'year' ? 'T' . $cursor->format('n') : $cursor->format('d/m'),
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];
            $cursor = $periodType === 'year' ? $cursor->addMonth() : $cursor->addDay();
        }

        return array_map(function (array $bucket): array {
            $bookingIn = $this->paidBookingPayments($bucket['start'], $bucket['end']);
            $systemRevenue = $this->paidPlatformFees($bucket['start'], $bucket['end'])
                + $this->paidVipPayments($bucket['start'], $bucket['end']);
            $withdrawals = $this->completedOwnerWithdrawals($bucket['start'], $bucket['end'])
                + $this->paidUserWithdrawals($bucket['start'], $bucket['end']);
            $voucherCost = $this->systemLedgerAmount('out', ['voucher_subsidy'], [], $bucket['start'], $bucket['end']);

            return [
                'label' => $bucket['label'],
                'money_in' => round($bookingIn + $systemRevenue, 2),
                'money_out' => round($withdrawals + $voucherCost, 2),
                'system_revenue' => round($systemRevenue, 2),
                'booking_collected' => $bookingIn,
                'net_movement' => round($bookingIn + $systemRevenue - $withdrawals - $voucherCost, 2),
            ];
        }, $buckets);
    }

    private function bookingLedgers(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = DB::table('payments as p')
            ->leftJoin('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.customer_id')
            ->leftJoin('venue_clusters as vc', 'vc.id', '=', 'b.venue_cluster_id')
            ->where('p.status', 'paid')
            ->whereNotIn(DB::raw('LOWER(p.method)'), ['cash', 'direct', 'offline'])
            ->whereBetween('p.paid_at', [$start, $end]);

        if (Schema::hasColumn('payments', 'payment_context')) {
            $query->where(function ($builder): void {
                $builder->whereNull('p.payment_context')->orWhere('p.payment_context', 'booking');
            });
        }

        return $query
            ->orderByDesc('p.paid_at')
            ->limit(12)
            ->get([
                'p.id',
                'p.payment_code',
                'p.amount',
                'p.payment_kind',
                'p.method',
                'p.paid_at',
                'b.booking_code',
                'b.source',
                'b.walk_in_name',
                'b.walk_in_phone',
                'vc.name as venue_cluster_name',
                'u.full_name as customer_name',
                'u.phone as customer_phone',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'code' => $row->payment_code,
                'booking_code' => $row->booking_code,
                'customer' => $row->customer_name ?: $row->walk_in_name ?: 'Khách vãng lai',
                'customer_contact' => $row->customer_phone ?: $row->walk_in_phone,
                'venue_cluster' => $row->venue_cluster_name ?: '-',
                'amount' => (float) $row->amount,
                'kind' => $row->payment_kind,
                'method' => $row->method,
                'paid_at' => $row->paid_at,
                'source' => $row->source ?: '-',
            ])
            ->all();
    }

    private function withdrawalLedgers(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $owners = DB::table('owner_withdrawal_requests as wr')
            ->leftJoin('users as u', 'u.id', '=', 'wr.owner_id')
            ->leftJoin('owner_wallets as ow', 'ow.id', '=', 'wr.owner_wallet_id')
            ->leftJoin('venue_clusters as vc', 'vc.id', '=', 'ow.venue_cluster_id')
            ->whereBetween('wr.requested_at', [$start, $end])
            ->orderByDesc('wr.requested_at')
            ->limit(12)
            ->get([
                'wr.id',
                'wr.request_code',
                'wr.amount',
                'wr.status',
                'wr.requested_at',
                'wr.completed_at',
                'u.full_name as requester_name',
                'vc.name as venue_cluster_name',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'type' => 'owner',
                'code' => $row->request_code,
                'requester' => $row->requester_name ?: 'Chủ sân',
                'scope' => $row->venue_cluster_name ?: '-',
                'amount' => (float) $row->amount,
                'status' => $row->status,
                'requested_at' => $row->requested_at,
                'completed_at' => $row->completed_at,
            ]);

        $users = DB::table('user_withdrawal_requests as wr')
            ->leftJoin('users as u', 'u.id', '=', 'wr.user_id')
            ->whereBetween('wr.requested_at', [$start, $end])
            ->orderByDesc('wr.requested_at')
            ->limit(12)
            ->get([
                'wr.id',
                'wr.amount',
                'wr.status',
                'wr.requested_at',
                'wr.paid_at',
                'u.full_name as requester_name',
                'u.phone',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'type' => 'user',
                'code' => substr((string) $row->id, 0, 8),
                'requester' => $row->requester_name ?: 'Người dùng',
                'scope' => $row->phone ?: '-',
                'amount' => (float) $row->amount,
                'status' => $row->status,
                'requested_at' => $row->requested_at,
                'completed_at' => $row->paid_at,
            ]);

        return $owners
            ->concat($users)
            ->sortByDesc('requested_at')
            ->take(12)
            ->values()
            ->all();
    }

    private function ownerDebts(): array
    {
        return DB::table('owner_wallets as ow')
            ->leftJoin('users as u', 'u.id', '=', 'ow.owner_id')
            ->leftJoin('venue_clusters as vc', 'vc.id', '=', 'ow.venue_cluster_id')
            ->orderByDesc(DB::raw('ow.available_balance + ow.pending_withdrawal_balance'))
            ->limit(12)
            ->get([
                'ow.id',
                'u.full_name as owner_name',
                'vc.name as venue_cluster_name',
                'ow.available_balance',
                'ow.pending_withdrawal_balance',
                'ow.total_earned',
                'ow.total_withdrawn',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'owner' => $row->owner_name ?: 'Chủ sân',
                'venue_cluster' => $row->venue_cluster_name ?: '-',
                'available_balance' => (float) $row->available_balance,
                'pending_balance' => (float) $row->pending_withdrawal_balance,
                'debt_total' => round((float) $row->available_balance + (float) $row->pending_withdrawal_balance, 2),
                'total_earned' => (float) $row->total_earned,
                'total_withdrawn' => (float) $row->total_withdrawn,
            ])
            ->all();
    }

    private function customerDebts(): array
    {
        return DB::table('user_wallets as uw')
            ->leftJoin('users as u', 'u.id', '=', 'uw.user_id')
            ->orderByDesc(DB::raw('uw.balance + uw.locked_balance'))
            ->limit(12)
            ->get([
                'uw.id',
                'u.full_name',
                'u.phone',
                'u.email',
                'uw.balance',
                'uw.locked_balance',
                'uw.status',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'customer' => $row->full_name ?: 'Người dùng',
                'contact' => $row->phone ?: $row->email,
                'balance' => (float) $row->balance,
                'locked_balance' => (float) $row->locked_balance,
                'debt_total' => round((float) $row->balance + (float) $row->locked_balance, 2),
                'status' => $row->status,
            ])
            ->all();
    }

    private function voucherLedgers(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('system_wallet_ledgers')) {
            return [];
        }

        $query = DB::table('system_wallet_ledgers')
            ->whereBetween('transacted_at', [$start, $end])
            ->orderByDesc('transacted_at')
            ->limit(12);

        if (Schema::hasColumn('system_wallet_ledgers', 'entry_kind')) {
            $query->where('entry_kind', 'voucher_subsidy');
        } else {
            $query->where('transaction_type', 'adjustment')->where('direction', 'out');
        }

        return $query
            ->get(['id', 'transaction_ref', 'amount', 'balance_after', 'reference_type', 'reference_id', 'description', 'transacted_at'])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'code' => $row->transaction_ref ?: substr((string) $row->id, 0, 8),
                'amount' => (float) $row->amount,
                'balance_after' => (float) $row->balance_after,
                'reference' => trim(($row->reference_type ?: '-') . ' ' . ($row->reference_id ?: '')),
                'description' => $row->description,
                'transacted_at' => $row->transacted_at,
            ])
            ->all();
    }

    private function revenueLedgers(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $platformFees = DB::table('venue_platform_fee_ledgers as pfl')
            ->leftJoin('venue_clusters as vc', 'vc.id', '=', 'pfl.venue_cluster_id')
            ->where('pfl.status', 'paid')
            ->whereBetween('pfl.paid_at', [$start, $end])
            ->orderByDesc('pfl.paid_at')
            ->limit(12)
            ->get([
                'pfl.id',
                'pfl.amount_paid',
                'pfl.paid_at',
                'pfl.billing_cycle',
                'vc.name as venue_cluster_name',
            ])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'type' => 'platform_fee',
                'label' => 'Phí nền tảng',
                'source' => $row->venue_cluster_name ?: '-',
                'amount' => (float) $row->amount_paid,
                'note' => $row->billing_cycle,
                'paid_at' => $row->paid_at,
            ]);

        $vipPayments = collect();
        if (Schema::hasColumn('payments', 'payment_context')) {
            $vipPayments = DB::table('payments as p')
                ->leftJoin('user_subscriptions as us', 'us.id', '=', 'p.subscription_id')
                ->leftJoin('users as u', 'u.id', '=', 'us.user_id')
                ->where('p.status', 'paid')
                ->where('p.payment_context', 'vip_subscription')
                ->whereBetween('p.paid_at', [$start, $end])
                ->orderByDesc('p.paid_at')
                ->limit(12)
                ->get(['p.id', 'p.payment_code', 'p.amount', 'p.paid_at', 'u.full_name'])
                ->map(fn ($row): array => [
                    'id' => $row->id,
                    'type' => 'vip_subscription',
                    'label' => 'Gói hội viên',
                    'source' => $row->full_name ?: 'Người dùng',
                    'amount' => (float) $row->amount,
                    'note' => $row->payment_code,
                    'paid_at' => $row->paid_at,
                ]);
        }

        return $platformFees
            ->concat($vipPayments)
            ->sortByDesc('paid_at')
            ->take(12)
            ->values()
            ->all();
    }

    private function systemLedgerAmount(
        ?string $direction,
        array $entryKinds,
        array $transactionTypes,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null
    ): float {
        if (! Schema::hasTable('system_wallet_ledgers')) {
            return 0.0;
        }

        $query = DB::table('system_wallet_ledgers');

        if ($direction) {
            $query->where('direction', $direction);
        }

        if ($entryKinds && Schema::hasColumn('system_wallet_ledgers', 'entry_kind')) {
            $query->whereIn('entry_kind', $entryKinds);
        } elseif ($transactionTypes) {
            $query->whereIn('transaction_type', $transactionTypes);
        }

        $this->between($query, 'transacted_at', $start, $end);

        return round((float) $query->sum('amount'), 2);
    }

    private function between($query, string $column, ?CarbonImmutable $start, ?CarbonImmutable $end): void
    {
        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
        }
    }
}
