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
        $revenue = DB::table('venue_platform_fee_ledgers')
            ->where('status', 'paid')
            ->sum('amount_paid') ?? 0;
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
            'period_start' => $revenue['period_start'],
            'period_end' => $revenue['period_end'],
            'revenue' => [
                'platform_fees' => $revenue['platform_fees'],
                'booking_payments' => $revenue['booking_payments'],
                'total' => $totalRevenue,
                'custody_total' => (float) ($revenue['custody_total'] ?? $revenue['booking_payments']['total'] ?? 0),
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
            'charts' => [
                'trend' => $this->trendSeries($periodType, $revenue['period_start'], $revenue['period_end']),
                'composition' => $this->compositionSeries($revenue, $expenses),
            ],
            'tables' => [
                'revenue_breakdown' => $this->revenueBreakdown($revenue),
                'expense_breakdown' => $this->expenseBreakdown($expenses),
                'operation_breakdown' => $this->operationBreakdown($revenue, $expenses),
            ],
        ];
    }

    private function trendSeries(string $periodType, string $periodStart, string $periodEnd): array
    {
        $start = \Carbon\CarbonImmutable::parse($periodStart)->startOfDay();
        $end = \Carbon\CarbonImmutable::parse($periodEnd)->endOfDay();
        $buckets = [];

        if ($periodType === 'year') {
            for ($cursor = $start->startOfMonth(); $cursor <= $end; $cursor = $cursor->addMonth()) {
                $buckets[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => 'T' . $cursor->format('n'),
                    'start' => $cursor->startOfMonth(),
                    'end' => $cursor->endOfMonth(),
                ];
            }
        } else {
            for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addDay()) {
                $buckets[] = [
                    'key' => $cursor->toDateString(),
                    'label' => $cursor->format('d/m'),
                    'start' => $cursor->startOfDay(),
                    'end' => $cursor->endOfDay(),
                ];
            }
        }

        return array_map(function (array $bucket): array {
            $platformFees = (float) DB::table('venue_platform_fee_ledgers')
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount_paid');

            $bookingCustody = (float) DB::table('payments')
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount');

            $voucherCost = (float) DB::table('system_wallet_ledgers')
                ->where('entry_kind', 'voucher_subsidy')
                ->whereBetween('transacted_at', [$bucket['start'], $bucket['end']])
                ->sum('amount');

            $refundCost = (float) DB::table('system_wallet_ledgers')
                ->where('entry_kind', 'refund_to_wallet')
                ->whereBetween('transacted_at', [$bucket['start'], $bucket['end']])
                ->sum('amount');

            $systemExpense = round($voucherCost + $refundCost, 2);

            return [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'system_revenue' => round($platformFees, 2),
                'booking_custody' => round($bookingCustody, 2),
                'system_expense' => $systemExpense,
                'net_revenue' => round($platformFees - $systemExpense, 2),
            ];
        }, $buckets);
    }

    private function compositionSeries(array $revenue, array $expenses): array
    {
        return [
            [
                'label' => 'Phí nền tảng',
                'value' => (float) ($revenue['platform_fees']['total_paid'] ?? 0),
                'group' => 'revenue',
            ],
            [
                'label' => 'Booking',
                'value' => (float) ($revenue['custody_total'] ?? 0),
                'group' => 'custody',
            ],
            [
                'label' => 'Voucher hệ thống',
                'value' => (float) ($expenses['voucher_total'] ?? 0),
                'group' => 'expense',
            ],
            [
                'label' => 'Hoàn vào ví',
                'value' => (float) ($expenses['refund_total'] ?? 0),
                'group' => 'expense',
            ],
        ];
    }

    private function revenueBreakdown(array $revenue): array
    {
        return [
            [
                'label' => 'Phí nền tảng đã thu',
                'amount' => (float) ($revenue['platform_fees']['total_paid'] ?? 0),
                'count' => (int) ($revenue['platform_fees']['paid_count'] ?? 0),
                'note' => 'Doanh thu thật của hệ thống.',
            ],
            [
                'label' => 'Phí nền tảng phải thu',
                'amount' => (float) ($revenue['platform_fees']['total_due'] ?? 0),
                'count' => null,
                'note' => 'Tổng nghĩa vụ trong kỳ, chưa chắc đã thu đủ.',
            ],
            [
                'label' => 'Phí nền tảng quá hạn',
                'amount' => (float) ($revenue['platform_fees']['overdue_amount'] ?? 0),
                'count' => (int) ($revenue['platform_fees']['overdue_count'] ?? 0),
                'note' => 'Khoản cần xử lý/nhắc phí.',
            ],
            [
                'label' => 'Booking online thu hộ',
                'amount' => (float) ($revenue['custody_total'] ?? 0),
                'count' => (int) ($revenue['booking_payments']['count'] ?? 0),
                'note' => 'Tiền của chủ sân, không tính doanh thu admin.',
            ],
        ];
    }

    private function expenseBreakdown(array $expenses): array
    {
        return [
            [
                'label' => 'Voucher hệ thống',
                'amount' => (float) ($expenses['voucher_total'] ?? 0),
                'count' => (int) ($expenses['voucher_count'] ?? 0),
                'note' => 'Khoản hệ thống bù cho chủ sân.',
            ],
            [
                'label' => 'Hoàn vào ví khách',
                'amount' => (float) ($expenses['refund_total'] ?? 0),
                'count' => (int) ($expenses['refund_count'] ?? 0),
                'note' => 'Chi phí hoàn tiền từ quỹ hệ thống.',
            ],
            [
                'label' => 'Tổng chi phí khuyến mãi',
                'amount' => (float) ($expenses['total'] ?? 0),
                'count' => (int) (($expenses['voucher_count'] ?? 0) + ($expenses['refund_count'] ?? 0)),
                'note' => 'Dùng để tính lãi ròng tham chiếu.',
            ],
        ];
    }

    private function operationBreakdown(array $revenue, array $expenses): array
    {
        $systemRevenue = (float) ($revenue['total_revenue'] ?? 0);
        $systemExpense = (float) ($expenses['total'] ?? 0);

        return [
            [
                'label' => 'Lãi ròng tham chiếu',
                'amount' => round($systemRevenue - $systemExpense, 2),
                'note' => 'Doanh thu hệ thống trừ chi phí hệ thống.',
            ],
            [
                'label' => 'Tiền thu hộ chủ sân',
                'amount' => (float) ($revenue['custody_total'] ?? 0),
                'note' => 'Theo dõi dòng tiền vận hành, không cộng vào doanh thu.',
            ],
            [
                'label' => 'Tỷ lệ chi phí / doanh thu',
                'amount' => $systemRevenue > 0 ? round(($systemExpense / $systemRevenue) * 100, 2) : null,
                'unit' => '%',
                'note' => 'Đánh giá sức khỏe chi phí khuyến mãi.',
            ],
        ];
    }
}