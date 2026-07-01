<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeTier;
use App\Models\SystemBankAccount;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use App\Services\Payments\PlatformFeePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

class PlatformFeeController extends Controller
{
    public function __construct(private readonly PlatformFeePaymentService $platformFeePayments) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $defaultPaymentAccount = $this->defaultPaymentAccount();
        $ledgers = VenuePlatformFeeLedger::query()
            ->with(['tier', 'systemBankAccount'])
            ->where('venue_cluster_id', $cluster->id)
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (VenuePlatformFeeLedger $ledger): array => $this->ledgerPayload($ledger, $defaultPaymentAccount));

        $outstanding = $ledgers
            ->whereIn('effective_status', ['pending', 'overdue'])
            ->sum('amount_remaining');

        return response()->json([
            'data' => $ledgers->values(),
            'summary' => [
                'total' => $ledgers->count(),
                'pending' => $ledgers->where('effective_status', 'pending')->count(),
                'overdue' => $ledgers->where('effective_status', 'overdue')->count(),
                'outstanding_amount' => round($outstanding, 2),
            ],
            'venue_cluster' => [
                'id' => $cluster->id,
                'name' => $cluster->name,
            ],
            'payment_account' => $this->paymentAccountPayload($defaultPaymentAccount),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()
            ->with(['tier', 'systemBankAccount'])
            ->findOrFail($id);

        $this->ownedCluster($request, $ledger->venue_cluster_id);
        $defaultPaymentAccount = $this->defaultPaymentAccount();

        return response()->json([
            'data' => $this->ledgerPayload($ledger, $ledger->systemBankAccount ?: $defaultPaymentAccount),
            'payment_account' => $this->paymentAccountPayload($defaultPaymentAccount),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $clusters = VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->withCount('venueCourts')
            ->orderBy('name')
            ->get();

        $ledgersByCluster = VenuePlatformFeeLedger::query()
            ->with(['tier', 'systemBankAccount'])
            ->whereIn('venue_cluster_id', $clusters->pluck('id'))
            ->orderBy('due_date')
            ->get()
            ->groupBy('venue_cluster_id');

        $tiers = PlatformFeeTier::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->orderByDesc('min_courts')
            ->get();

        $data = $clusters->map(function (VenueCluster $cluster) use ($ledgersByCluster, $tiers): array {
            $ledgers = $ledgersByCluster->get($cluster->id, collect());
            $unpaid = $ledgers
                ->map(fn (VenuePlatformFeeLedger $ledger): array => $this->ledgerPayload($ledger))
                ->filter(fn (array $ledger): bool => in_array($ledger['effective_status'], ['pending', 'overdue'], true)
                    && $ledger['amount_remaining'] > 0)
                ->values();

            $tier = $tiers->first(fn (PlatformFeeTier $item): bool => $item->min_courts <= $cluster->venue_courts_count
                && ($item->max_courts === null || $item->max_courts >= $cluster->venue_courts_count));
            $monthlyAmount = $tier
                ? round($cluster->venue_courts_count * (float) $tier->price_per_court_month, 2)
                : 0;

            return [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'status' => $cluster->status,
                'court_count' => $cluster->venue_courts_count,
                'tier_name' => $tier?->name,
                'monthly_amount' => $monthlyAmount,
                'estimated_amounts' => collect([1, 3, 6, 9])
                    ->mapWithKeys(fn (int $months): array => [(string) $months => $monthlyAmount * $months]),
                'outstanding_count' => $unpaid->count(),
                'overdue_count' => $unpaid->where('effective_status', 'overdue')->count(),
                'outstanding_amount' => round($unpaid->sum('amount_remaining'), 2),
                'oldest_outstanding' => $unpaid->first(),
                'can_prepay' => $cluster->venue_courts_count > 0 && $unpaid->isEmpty() && $tier !== null,
                'prepay_block_reason' => match (true) {
                    $cluster->venue_courts_count < 1 => 'Cụm sân chưa có sân con để tính phí.',
                    $unpaid->isNotEmpty() => 'Cần thanh toán các kỳ còn thiếu trước khi trả trước.',
                    $tier === null => 'Chưa có bậc phí phù hợp.',
                    default => null,
                },
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function createPayment(Request $request, string $id): JsonResponse
    {
        $ledger = VenuePlatformFeeLedger::query()->findOrFail($id);
        $this->ownedCluster($request, $ledger->venue_cluster_id);

        try {
            $result = $this->platformFeePayments->createPayment($ledger, $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->paymentResponse($result);
    }

    public function createAdvancePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid'],
            'months' => ['required', 'integer', Rule::in([1, 3, 6, 9])],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);

        try {
            $result = $this->platformFeePayments->createAdvancePayment(
                $cluster,
                (int) $data['months'],
                $request->user()->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->paymentResponse(
            $result,
            "Đã tạo mã thanh toán trước {$data['months']} tháng.",
        );
    }

    private function ownedCluster(Request $request, string $clusterId): VenueCluster
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            abort(403, 'Bạn không có quyền xem phí của cụm sân này.');
        }

        return $cluster;
    }

    private function ledgerPayload(VenuePlatformFeeLedger $ledger, ?SystemBankAccount $defaultPaymentAccount = null): array
    {
        $effectiveStatus = $this->effectiveStatus($ledger);
        $dueDate = $ledger->due_date ?? $ledger->period_end;
        $daysUntilDue = $dueDate ? today()->diffInDays($dueDate, false) : null;
        $amountRemaining = max(0, (float) $ledger->amount_due - (float) $ledger->amount_paid);
        $paymentAccount = $ledger->systemBankAccount ?: $defaultPaymentAccount;

        return [
            'id' => $ledger->id,
            'court_count' => $ledger->court_count,
            'billing_cycle' => $ledger->billing_cycle,
            'period_months' => $ledger->period_months,
            'period_start' => $ledger->period_start?->toDateString(),
            'period_end' => $ledger->period_end?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
            'price_per_court_month' => (float) $ledger->price_per_court_month,
            'discount_percent' => (float) $ledger->discount_percent,
            'amount_due' => (float) $ledger->amount_due,
            'amount_paid' => (float) $ledger->amount_paid,
            'amount_remaining' => round($amountRemaining, 2),
            'payment_reference' => $ledger->payment_code,
            'status' => $ledger->status,
            'effective_status' => $effectiveStatus,
            'paid_at' => $ledger->paid_at?->toISOString(),
            'payment' => [
                'method' => 'sepay',
                'code' => $ledger->payment_code,
                'auto_confirm' => true,
                'bank_account' => $this->paymentAccountPayload($paymentAccount),
            ],
            'days_until_due' => $daysUntilDue,
            'warning_level' => match (true) {
                $effectiveStatus === 'overdue' => 'overdue',
                $effectiveStatus === 'pending' && $daysUntilDue !== null && $daysUntilDue <= 7 => 'due_soon',
                default => null,
            },
            'tier' => $ledger->tier ? [
                'id' => $ledger->tier->id,
                'name' => $ledger->tier->name,
            ] : null,
        ];
    }

    private function effectiveStatus(VenuePlatformFeeLedger $ledger): string
    {
        if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
            return $ledger->status;
        }

        $dueDate = $ledger->due_date ?? $ledger->period_end;

        return $dueDate && Carbon::parse($dueDate)->isBefore(today()) ? 'overdue' : 'pending';
    }

    private function paymentResponse(array $result, string $message = 'Đã tạo mã thanh toán phí nền tảng.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $this->ledgerPayload($result['ledger']->load(['tier', 'systemBankAccount'])),
            'payment_account' => $this->paymentAccountPayload($result['payment_account']),
            'transfer_content' => $result['transfer_content'],
            'amount' => $result['amount'],
            'qr_url' => $result['qr_url'],
        ]);
    }

    private function paymentAccountPayload(?SystemBankAccount $account = null): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'account_number' => $account->account_number,
            'account_holder_name' => $account->account_holder_name,
        ];
    }

    private function defaultPaymentAccount(): ?SystemBankAccount
    {
        return SystemBankAccount::query()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();
    }
}
