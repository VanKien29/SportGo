<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Wallets\SystemWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private readonly SystemWalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'finance_period' => ['nullable', Rule::in(['week', 'month', 'year'])],
        ]);

        $financePeriod = $data['finance_period'] ?? 'month';
        $usersCount = User::query()->count();
        $venuesCount = DB::table('venue_clusters')->count();
        $bookingsCount = DB::table('bookings')->count();
        $revenue = DB::table('payments')->where('status', 'paid')->sum('amount') ?? 0;
        $finance = $this->financePayload($financePeriod);

        return response()->json([
            'users' => $usersCount,
            'venues' => $venuesCount,
            'bookings' => $bookingsCount,
            'revenue' => (float)$revenue,
            'finance' => $finance,
        ]);
    }

    private function financePayload(string $periodType): array
    {
        $wallet = $this->wallets->snapshot();
        $revenue = $this->wallets->revenueSummaryByPeriod($periodType);
        $expenses = $this->wallets->promotionExpenseByPeriod($periodType);
        $budget = $wallet->promotion_monthly_budget !== null ? (float) $wallet->promotion_monthly_budget : null;
        $spent = (float) $expenses['total'];
        $totalRevenue = (float) $revenue['total_revenue'];

        return [
            'period_type' => $periodType,
            'period_label' => $revenue['period_label'],
            'revenue' => [
                'platform_fees' => $revenue['platform_fees'],
                'booking_payments' => $revenue['booking_payments'],
                'total' => $totalRevenue,
            ],
            'promotion_expenses' => [
                'voucher_subsidies' => [
                    'total' => (float) $expenses['voucher_total'],
                    'count' => (int) $expenses['voucher_count'],
                ],
                'refunds' => [
                    'total' => (float) $expenses['refund_total'],
                    'count' => (int) $expenses['refund_count'],
                ],
                'total' => $spent,
            ],
            'promotion_budget' => [
                'budget' => $budget,
                'budget_period' => $wallet->budget_period_type ?: 'month',
                'spent' => $spent,
                'remaining' => $budget !== null ? round($budget - $spent, 2) : null,
                'usage_percent' => $budget && $budget > 0 ? (int) round(($spent / $budget) * 100) : null,
                'is_over_budget' => $budget !== null && $spent > $budget,
            ],
            'bank_balance' => $wallet->bank_balance !== null ? (float) $wallet->bank_balance : null,
            'bank_synced_at' => $wallet->bank_synced_at,
            'net_revenue' => round($totalRevenue - $spent, 2),
        ];
    }
}
