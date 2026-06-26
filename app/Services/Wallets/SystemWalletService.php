<?php

namespace App\Services\Wallets;

use App\Models\Notification;
use App\Models\SystemBankAccount;
use App\Models\SystemWalletBalance;
use App\Models\SystemWalletLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SystemWalletService
{
    public function defaultAccount(): SystemBankAccount
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        if (! $account) {
            throw new RuntimeException('Chưa có tài khoản ngân hàng hệ thống đang hoạt động.');
        }

        return $account;
    }

    public function snapshot(?SystemBankAccount $account = null): SystemWalletBalance
    {
        $account ??= $this->defaultAccount();

        return SystemWalletBalance::query()->firstOrCreate(
            ['system_bank_account_id' => $account->id],
            [
                'current_balance' => 0,
                'bank_balance' => 0,
                'refund_reserved_balance' => 0,
                'voucher_reserved_balance' => 0,
                'is_alert_enabled' => true,
            ],
        );
    }

    public function sync(?SystemBankAccount $account = null): SystemWalletBalance
    {
        $account ??= $this->defaultAccount();
        $token = trim((string) config('services.sepay.api_token'));

        if ($token === '') {
            throw new RuntimeException('Chưa cấu hình SEPAY_API_TOKEN để đồng bộ số dư tài khoản hệ thống.');
        }

        $baseUrl = rtrim((string) config('services.sepay.api_base_url', 'https://userapi.sepay.vn/v2'), '/');
        $accountResponse = Http::acceptJson()->withToken($token)->get($baseUrl.'/bank-accounts');

        if ($accountResponse->failed()) {
            throw new RuntimeException('Không đọc được số dư tài khoản từ SePay.');
        }

        $remoteAccount = collect($this->responseRows($accountResponse->json()))
            ->first(fn (array $item): bool => $this->digits($this->value($item, [
                'account_number', 'accountNumber', 'account_no', 'number',
            ])) === $this->digits($account->account_number));

        if (! $remoteAccount) {
            throw new RuntimeException('SePay chưa trả về tài khoản '.$account->account_number.'.');
        }

        $balanceValue = $this->value($remoteAccount, [
            'balance', 'current_balance', 'currentBalance', 'account_balance',
            'accountBalance', 'available_balance', 'availableBalance',
        ]);

        if (! is_numeric($balanceValue)) {
            throw new RuntimeException('SePay không trả về trường số dư cho tài khoản đã cấu hình.');
        }

        $wallet = DB::transaction(function () use ($account, $balanceValue): SystemWalletBalance {
            $wallet = $this->lockedSnapshot($account);
            $wallet->forceFill([
                'bank_balance' => round((float) $balanceValue, 2),
                'bank_synced_at' => now(),
            ])->save();

            return $wallet->fresh();
        });

        $this->syncTransactions($account, $wallet, $token, $baseUrl);
        $wallet = $this->snapshot($account)->fresh();

        return $wallet;
    }

    public function recordIncoming(
        SystemBankAccount $account,
        string $transactionRef,
        float $amount,
        string $transactionType,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        array $metadata = [],
        mixed $transactedAt = null,
    ): SystemWalletLedger {
        return DB::transaction(function () use (
            $account,
            $transactionRef,
            $amount,
            $transactionType,
            $referenceType,
            $referenceId,
            $description,
            $metadata,
            $transactedAt,
        ): SystemWalletLedger {
            $existing = $this->existingLedger($account, $transactionRef);
            if ($existing) {
                return $existing;
            }

            $wallet = $this->lockedSnapshot($account);
            $before = (float) $wallet->current_balance;

            $ledger = $this->createLedger($wallet, [
                'transaction_ref' => $transactionRef,
                'direction' => 'in',
                'entry_kind' => 'bank_in',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $before,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
                'transacted_at' => $transactedAt,
            ]);

            return $ledger;
        });
    }

    public function reserveRefund(
        float $amount,
        string $refundId,
        ?SystemBankAccount $account = null,
        array $metadata = [],
    ): SystemWalletLedger {
        return $this->spendInternalFund(
            $account ?? $this->defaultAccount(),
            $amount,
            'refund_to_customer',
            'refund_to_wallet',
            'refund',
            $refundId,
            'Trừ quỹ hệ thống do hoàn tiền vào ví khách hàng.',
            $metadata,
        );
    }

    public function reserveVoucher(
        float $amount,
        string $paymentId,
        ?SystemBankAccount $account = null,
        array $metadata = [],
    ): ?SystemWalletLedger {
        if ($amount <= 0) {
            return null;
        }

        return $this->spendInternalFund(
            $account ?? $this->defaultAccount(),
            $amount,
            'adjustment',
            'voucher_subsidy',
            'payment',
            $paymentId,
            'Trừ quỹ hệ thống cho voucher hệ thống.',
            $metadata,
        );
    }

    public function reserveVoucherForBooking(
        float $amount,
        string $bookingId,
        ?SystemBankAccount $account = null,
        array $metadata = [],
    ): ?SystemWalletLedger {
        if ($amount <= 0) {
            return null;
        }

        return $this->spendInternalFund(
            $account ?? $this->defaultAccount(),
            $amount,
            'adjustment',
            'voucher_subsidy',
            'booking',
            $bookingId,
            'Trừ quỹ hệ thống cho voucher hệ thống.',
            $metadata,
        );
    }

    public function recordOutgoing(
        SystemBankAccount $account,
        string $transactionRef,
        float $amount,
        string $transactionType,
        string $releaseType = 'none',
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        array $metadata = [],
        mixed $transactedAt = null,
    ): SystemWalletLedger {
        return DB::transaction(function () use (
            $account,
            $transactionRef,
            $amount,
            $transactionType,
            $releaseType,
            $referenceType,
            $referenceId,
            $description,
            $metadata,
            $transactedAt,
        ): SystemWalletLedger {
            $existing = $this->existingLedger($account, $transactionRef);
            if ($existing) {
                return $existing;
            }

            $wallet = $this->lockedSnapshot($account);

            $balanceBefore = (float) $wallet->current_balance;
            $refundBefore = (float) $wallet->refund_reserved_balance;
            $voucherBefore = (float) $wallet->voucher_reserved_balance;
            $refundAfter = $refundBefore;
            $voucherAfter = $voucherBefore;

            $balanceAfter = round($balanceBefore - $amount, 2);
            $wallet->forceFill([
                'current_balance' => $balanceAfter,
            ])->save();

            $ledger = $this->createLedger($wallet, [
                'transaction_ref' => $transactionRef,
                'direction' => 'out',
                'entry_kind' => 'manual_out',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'refund_reserved_before' => $refundBefore,
                'refund_reserved_after' => $refundAfter,
                'voucher_reserved_before' => $voucherBefore,
                'voucher_reserved_after' => $voucherAfter,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
                'transacted_at' => $transactedAt,
            ]);

            return $ledger;
        });
    }

    public function updatePromotionBudget(float $budget, string $budgetPeriod, bool $enabled): SystemWalletBalance
    {
        $wallet = $this->snapshot();
        $wallet->forceFill([
            'promotion_monthly_budget' => round(max($budget, 0), 2),
            'budget_period_type' => $budgetPeriod,
            'is_alert_enabled' => $enabled,
            'last_alerted_at' => null,
        ])->save();

        $this->notifyIfOverBudget($wallet->fresh());

        return $wallet->fresh();
    }

    public function promotionExpenseByPeriod(string $periodType, ?Carbon $date = null): array
    {
        [$start, $end, $label] = $this->periodBounds($periodType, $date);

        $voucher = SystemWalletLedger::query()
            ->where('entry_kind', 'voucher_subsidy')
            ->whereBetween('transacted_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $refund = SystemWalletLedger::query()
            ->where('entry_kind', 'refund_to_wallet')
            ->whereBetween('transacted_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $voucherTotal = (float) ($voucher->total ?? 0);
        $refundTotal = (float) ($refund->total ?? 0);

        return [
            'period_type' => $periodType,
            'period_label' => $label,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'voucher_total' => $voucherTotal,
            'voucher_count' => (int) ($voucher->count ?? 0),
            'refund_total' => $refundTotal,
            'refund_count' => (int) ($refund->count ?? 0),
            'total' => round($voucherTotal + $refundTotal, 2),
        ];
    }

    public function revenueSummaryByPeriod(string $periodType, ?Carbon $date = null): array
    {
        [$start, $end, $label] = $this->periodBounds($periodType, $date);

        $platformPaid = DB::table('venue_platform_fee_ledgers')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_paid, COUNT(*) as paid_count')
            ->first();

        $platformDue = DB::table('venue_platform_fee_ledgers')
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->selectRaw('COALESCE(SUM(amount_due), 0) as total_due')
            ->first();

        $platformOverdue = DB::table('venue_platform_fee_ledgers')
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) as overdue_amount, COUNT(*) as overdue_count')
            ->first();

        $bookingPayments = DB::table('payments')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $platformTotalPaid = (float) ($platformPaid->total_paid ?? 0);
        $bookingTotal = (float) ($bookingPayments->total ?? 0);

        return [
            'period_type' => $periodType,
            'period_label' => $label,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'platform_fees' => [
                'total_paid' => $platformTotalPaid,
                'total_due' => (float) ($platformDue->total_due ?? 0),
                'overdue_amount' => (float) ($platformOverdue->overdue_amount ?? 0),
                'overdue_count' => (int) ($platformOverdue->overdue_count ?? 0),
                'paid_count' => (int) ($platformPaid->paid_count ?? 0),
            ],
            'booking_payments' => [
                'total' => $bookingTotal,
                'count' => (int) ($bookingPayments->count ?? 0),
            ],
            'total_revenue' => round($platformTotalPaid + $bookingTotal, 2),
        ];
    }

    private function spendInternalFund(
        SystemBankAccount $account,
        float $amount,
        string $transactionType,
        string $entryKind,
        string $referenceType,
        string $referenceId,
        string $description,
        array $metadata,
    ): SystemWalletLedger {
        $amount = round(max($amount, 0), 2);
        $transactionRef = strtoupper($entryKind).'-'.$referenceId;

        return DB::transaction(function () use (
            $account,
            $amount,
            $transactionType,
            $entryKind,
            $referenceType,
            $referenceId,
            $description,
            $metadata,
            $transactionRef,
        ): SystemWalletLedger {
            $existing = $this->existingLedger($account, $transactionRef);
            if ($existing) {
                return $existing;
            }

            $wallet = $this->lockedSnapshot($account);

            $before = (float) $wallet->current_balance;
            $after = round($before - $amount, 2);
            $wallet->forceFill([
                'current_balance' => $after,
                'last_synced_at' => now(),
            ])->save();

            $ledger = $this->createLedger($wallet, [
                'transaction_ref' => $transactionRef,
                'direction' => 'out',
                'entry_kind' => $entryKind,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'transaction_type' => $transactionType,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            if (in_array($entryKind, ['voucher_subsidy', 'refund_to_wallet'], true)) {
                $this->notifyIfOverBudget($wallet->fresh());
            }

            return $ledger;
        });
    }

    private function syncTransactions(
        SystemBankAccount $account,
        SystemWalletBalance $wallet,
        string $token,
        string $baseUrl,
    ): void {
        $response = Http::acceptJson()->withToken($token)->get($baseUrl.'/transactions', [
            'account_number' => $account->account_number,
            'transaction_date_from' => now()->subDays(30)->format('Y-m-d 00:00:00'),
            'transaction_date_to' => now()->format('Y-m-d 23:59:59'),
            'transaction_date_sort' => 'desc',
            'per_page' => 100,
            'timestamp_format' => 'iso8601',
        ]);

        if ($response->failed()) {
            return;
        }

        $rows = collect($this->responseRows($response->json()))
            ->map(fn (array $item): array => $this->normalizeTransaction($item))
            ->filter(fn (array $item): bool => $item['transaction_ref'] !== '' && $item['amount'] > 0)
            ->sortByDesc('transacted_at')
            ->values();

        $runningAfter = (float) $wallet->bank_balance;

        foreach ($rows as $item) {
            $balanceAfter = $runningAfter;
            $balanceBefore = $item['direction'] === 'in'
                ? $balanceAfter - $item['amount']
                : $balanceAfter + $item['amount'];
            $runningAfter = $balanceBefore;

            SystemWalletLedger::query()->firstOrCreate(
                [
                    'system_bank_account_id' => $account->id,
                    'transaction_ref' => $item['transaction_ref'],
                ],
                [
                    'direction' => $item['direction'],
                    'entry_kind' => $item['direction'] === 'in' ? 'bank_in' : 'bank_out',
                    'amount' => $item['amount'],
                    'balance_before' => round($balanceBefore, 2),
                    'balance_after' => round($balanceAfter, 2),
                    'refund_reserved_before' => $wallet->refund_reserved_balance,
                    'refund_reserved_after' => $wallet->refund_reserved_balance,
                    'voucher_reserved_before' => $wallet->voucher_reserved_balance,
                    'voucher_reserved_after' => $wallet->voucher_reserved_balance,
                    'transaction_type' => 'other',
                    'description' => $item['content'] ?: 'Giao dịch đồng bộ từ SePay.',
                    'metadata' => ['source' => 'sepay_sync'],
                    'transacted_at' => $item['transacted_at'],
                    'synced_at' => now(),
                ],
            );
        }
    }

    private function normalizeTransaction(array $item): array
    {
        $type = strtolower((string) $this->value($item, ['transfer_type', 'transferType', 'type']));
        $amountIn = (float) ($this->value($item, ['amount_in', 'amountIn']) ?: 0);
        $amountOut = (float) ($this->value($item, ['amount_out', 'amountOut']) ?: 0);
        $genericAmount = (float) ($this->value($item, ['amount', 'transferAmount']) ?: 0);
        $direction = in_array($type, ['out', 'debit'], true) || $amountOut > 0 ? 'out' : 'in';

        return [
            'transaction_ref' => (string) $this->value($item, [
                'id', 'transaction_id', 'transactionId', 'reference_code', 'referenceCode',
            ]),
            'direction' => $direction,
            'amount' => round($direction === 'out' ? ($amountOut ?: $genericAmount) : ($amountIn ?: $genericAmount), 2),
            'content' => (string) $this->value($item, ['content', 'description']),
            'transacted_at' => $this->value($item, ['transaction_date', 'transactionDate', 'created_at', 'createdAt']) ?: now(),
        ];
    }

    private function createLedger(SystemWalletBalance $wallet, array $data): SystemWalletLedger
    {
        return SystemWalletLedger::query()->create(array_merge([
            'system_bank_account_id' => $wallet->system_bank_account_id,
            'refund_reserved_before' => $wallet->refund_reserved_balance,
            'refund_reserved_after' => $wallet->refund_reserved_balance,
            'voucher_reserved_before' => $wallet->voucher_reserved_balance,
            'voucher_reserved_after' => $wallet->voucher_reserved_balance,
            'transacted_at' => now(),
            'synced_at' => now(),
        ], $data));
    }

    private function lockedSnapshot(SystemBankAccount $account): SystemWalletBalance
    {
        $this->snapshot($account);

        return SystemWalletBalance::query()
            ->where('system_bank_account_id', $account->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function existingLedger(SystemBankAccount $account, string $transactionRef): ?SystemWalletLedger
    {
        return SystemWalletLedger::query()
            ->where('system_bank_account_id', $account->id)
            ->where('transaction_ref', $transactionRef)
            ->first();
    }

    private function notifyIfOverBudget(SystemWalletBalance $wallet, bool $force = false): void
    {
        $budget = (float) ($wallet->promotion_monthly_budget ?? 0);

        if (! $wallet->is_alert_enabled || $budget <= 0) {
            return;
        }

        $periodType = $wallet->budget_period_type ?: 'month';
        $expense = $this->promotionExpenseByPeriod($periodType);

        if (! $force && $expense['total'] <= $budget) {
            return;
        }

        if (! $force && $wallet->last_alerted_at && $wallet->last_alerted_at->gt(now()->subHours(6))) {
            return;
        }

        $adminIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('roles.name', ['admin', 'super_admin'])
            ->distinct()
            ->pluck('user_roles.user_id');

        foreach ($adminIds as $adminId) {
            Notification::query()->create([
                'user_id' => $adminId,
                'type' => 'system_promotion_over_budget',
                'title' => 'Chi phí khuyến mãi đã vượt ngân sách',
                'body' => 'Kỳ '.$expense['period_label'].' đã chi '.
                    number_format($expense['total'], 0, ',', '.').' đ / ngân sách '.
                    number_format($budget, 0, ',', '.').' đ.',
                'reference_type' => 'system_wallet',
                'reference_id' => $wallet->id,
                'data' => [
                    'period_type' => $expense['period_type'],
                    'period_label' => $expense['period_label'],
                    'budget' => $budget,
                    'spent' => $expense['total'],
                    'voucher_total' => $expense['voucher_total'],
                    'refund_total' => $expense['refund_total'],
                ],
                'is_read' => false,
            ]);
        }

        $wallet->forceFill(['last_alerted_at' => now()])->save();
    }

    private function periodBounds(string $periodType, ?Carbon $date = null): array
    {
        $periodType = in_array($periodType, ['week', 'month', 'year'], true) ? $periodType : 'month';
        $date = ($date ?: now())->copy();

        if ($periodType === 'week') {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
            $label = 'Tuần '.$start->format('d/m/Y').' - '.$end->format('d/m/Y');
        } elseif ($periodType === 'year') {
            $start = $date->copy()->startOfYear();
            $end = $date->copy()->endOfYear();
            $label = 'Năm '.$date->format('Y');
        } else {
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $label = 'Tháng '.$date->format('m/Y');
        }

        return [$start->startOfDay(), $end->endOfDay(), $label];
    }

    private function responseRows(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        if (isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (isset($data['bank_accounts']) && is_array($data['bank_accounts'])) {
            return $data['bank_accounts'];
        }

        return is_array($data) && array_is_list($data) ? $data : [];
    }

    private function value(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item)) {
                return $item[$key];
            }
        }

        return null;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
