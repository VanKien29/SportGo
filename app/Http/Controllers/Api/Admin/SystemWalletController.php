<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemWalletLedger;
use App\Services\Wallets\SystemWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemWalletController extends Controller
{
    public function __construct(private readonly SystemWalletService $wallets) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'entry_kind' => ['nullable', 'string', 'max:40'],
            'period_type' => ['nullable', Rule::in(['week', 'month', 'year'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $periodType = $data['period_type'] ?? 'month';
        $account = $this->wallets->defaultAccount();
        $wallet = $this->wallets->snapshot($account);
        $promotionExpenses = $this->wallets->promotionExpenseByPeriod($periodType);
        $revenueSummary = $this->wallets->revenueSummaryByPeriod($periodType);
        $ledgers = SystemWalletLedger::query()
            ->where('system_bank_account_id', $account->id)
            ->when($data['direction'] ?? null, fn ($query, string $value) => $query->where('direction', $value))
            ->when($data['entry_kind'] ?? null, fn ($query, string $value) => $query->where('entry_kind', $value))
            ->latest('transacted_at')
            ->paginate((int) ($data['per_page'] ?? 20));
        $ledgers->getCollection()->transform(function (SystemWalletLedger $ledger): SystemWalletLedger {
            $ledger->setAttribute('reference_label', $this->referenceLabel($ledger));

            return $ledger;
        });

        return response()->json([
            'account' => $account,
            'wallet' => $this->walletPayload($wallet),
            'promotion_expenses' => $promotionExpenses,
            'revenue_summary' => $revenueSummary,
            'promotion_budget' => $this->promotionBudgetPayload($wallet, $promotionExpenses),
            'ledgers' => $ledgers,
        ]);
    }

    public function sync(): JsonResponse
    {
        $wallet = $this->wallets->sync();

        return response()->json([
            'message' => 'Đã đồng bộ số dư ATM và lịch sử giao dịch từ SePay.',
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'promotion_budget' => ['required', 'numeric', 'min:0'],
            'budget_period' => ['required', Rule::in(['week', 'month', 'year'])],
            'is_alert_enabled' => ['required', 'boolean'],
        ]);

        $wallet = $this->wallets->updatePromotionBudget(
            (float) $data['promotion_budget'],
            $data['budget_period'],
            (bool) $data['is_alert_enabled'],
        );

        return response()->json([
            'message' => 'Đã lưu ngân sách khuyến mãi.',
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    private function walletPayload($wallet): array
    {
        return [
            'id' => $wallet->id,
            'current_balance' => (float) $wallet->current_balance,
            'bank_balance' => (float) $wallet->bank_balance,
            'refund_reserved_balance' => (float) $wallet->refund_reserved_balance,
            'voucher_reserved_balance' => (float) $wallet->voucher_reserved_balance,
            'reserved_balance' => $wallet->reserved_balance,
            'available_balance' => $wallet->available_balance,
            'reference_balance' => $wallet->reference_balance,
            'alert_threshold' => $wallet->alert_threshold !== null ? (float) $wallet->alert_threshold : null,
            'promotion_monthly_budget' => $wallet->promotion_monthly_budget !== null ? (float) $wallet->promotion_monthly_budget : null,
            'budget_period_type' => $wallet->budget_period_type ?: 'month',
            'is_alert_enabled' => (bool) $wallet->is_alert_enabled,
            'last_synced_at' => $wallet->last_synced_at,
            'bank_synced_at' => $wallet->bank_synced_at,
            'is_low_balance' => false,
        ];
    }

    private function promotionBudgetPayload($wallet, array $promotionExpenses): array
    {
        $budget = $wallet->promotion_monthly_budget !== null ? (float) $wallet->promotion_monthly_budget : null;
        $spent = (float) ($promotionExpenses['total'] ?? 0);

        return [
            'budget' => $budget,
            'budget_period' => $wallet->budget_period_type ?: 'month',
            'spent' => $spent,
            'remaining' => $budget !== null ? round($budget - $spent, 2) : null,
            'usage_percent' => $budget && $budget > 0 ? (int) round(($spent / $budget) * 100) : null,
            'is_over_budget' => $budget !== null && $spent > $budget,
        ];
    }

    private function referenceLabel(SystemWalletLedger $ledger): ?string
    {
        $metadata = is_array($ledger->metadata) ? $ledger->metadata : [];
        if (! empty($metadata['booking_code'])) {
            return 'Booking '.$metadata['booking_code'];
        }

        if (! $ledger->reference_type || ! $ledger->reference_id) {
            return null;
        }

        return match ($ledger->reference_type) {
            'booking', 'bookings' => $this->codeLabel('bookings', $ledger->reference_id, 'booking_code', 'Booking'),
            'payment', 'payments' => $this->codeLabel('payments', $ledger->reference_id, 'payment_code', 'Thanh toán'),
            'refund', 'refunds' => $this->refundLabel($ledger->reference_id),
            'withdrawal', 'owner_withdrawal', 'owner_withdrawal_request' => $this->codeLabel('owner_withdrawal_requests', $ledger->reference_id, 'request_code', 'Rút tiền chủ sân'),
            default => null,
        };
    }

    private function codeLabel(string $table, string $id, string $codeColumn, string $prefix): ?string
    {
        $code = DB::table($table)->where('id', $id)->value($codeColumn);

        return $code ? $prefix.' '.$code : null;
    }

    private function refundLabel(string $id): ?string
    {
        $refund = DB::table('refunds')
            ->leftJoin('bookings', 'bookings.id', '=', 'refunds.booking_id')
            ->where('refunds.id', $id)
            ->select('bookings.booking_code', 'refunds.payout_transfer_code')
            ->first();

        if (! $refund) {
            return null;
        }

        if ($refund->payout_transfer_code) {
            return 'Hoàn tiền '.$refund->payout_transfer_code;
        }

        return $refund->booking_code ? 'Hoàn tiền booking '.$refund->booking_code : null;
    }
}
