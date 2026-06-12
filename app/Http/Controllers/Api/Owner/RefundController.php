<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\Admin\AdminAuditService;
use App\Services\Finance\OwnerRefundService;
use App\Services\Policies\RefundPolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(
        private readonly OwnerRefundService $refunds,
        private readonly RefundPolicyEvaluator $refundPolicies,
        private readonly AdminAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'pending_owner_confirmation',
                'owner_confirmed',
                'owner_rejected',
                'admin_processing',
                'processing',
                'completed',
                'failed',
                'rejected',
                'cancelled',
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
        ]);

        $query = Refund::query()
            ->with([
                'booking.customer:id,username,full_name,email,phone',
                'booking.venueCluster:id,name,owner_id',
                'payment:id,payment_code,booking_id,amount,method,payment_kind,status',
                'ownerConfirmedBy:id,username,full_name',
                'statusHistories.changedBy:id,username,full_name',
            ])
            ->whereHas('booking.venueCluster', fn ($builder) => $builder->where('owner_id', $request->user()->id))
            ->when($data['status'] ?? null, fn ($builder, string $status) => $builder->where('status', $status))
            ->when($data['date_from'] ?? null, fn ($builder, string $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($builder, string $date) => $builder->whereDate('created_at', '<=', $date))
            ->when($data['keyword'] ?? null, function ($builder, string $keyword): void {
                $search = '%'.trim($keyword).'%';
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('id', 'like', $search)
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_code', 'like', $search))
                        ->orWhereHas('payment', fn ($payment) => $payment->where('payment_code', 'like', $search))
                        ->orWhereHas('customer', function ($customer) use ($search): void {
                            $customer
                                ->where('full_name', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('phone', 'like', $search)
                                ->orWhere('email', 'like', $search);
                        });
                });
            });

        $refunds = $query->latest()->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $refunds->getCollection()->map(fn (Refund $refund): array => $this->payload($refund))->values(),
            'meta' => $this->pagination($refunds),
        ]);
    }

    public function decide(Request $request, string $id): JsonResponse
    {
        $refund = Refund::query()
            ->whereKey($id)
            ->whereHas('booking.venueCluster', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->firstOrFail();

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'amount' => ['nullable', 'numeric', 'min:1', 'required_if:decision,approve'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $oldValues = $refund->toArray();

        $updated = $this->refunds->decide(
            $refund,
            $request->user(),
            $data['decision'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            trim($data['note']),
        );

        $this->audit->log(
            $request,
            'refund',
            $data['decision'] === 'approve' ? 'refund.owner_approved' : 'refund.owner_rejected',
            'refunds',
            $updated->id,
            $oldValues,
            $updated->toArray(),
            [
                'context' => 'owner',
                'reason' => $data['note'],
                'severity' => $data['decision'] === 'approve' ? 'info' : 'warning',
            ],
        );

        return response()->json([
            'message' => $data['decision'] === 'approve'
                ? 'Đã xác nhận yêu cầu hoàn tiền và chuyển sang bước xử lý của SportGo.'
                : 'Đã từ chối yêu cầu hoàn tiền.',
            'data' => $this->payload($updated),
        ]);
    }

    private function payload(Refund $refund): array
    {
        $policy = $this->refundPolicies->evaluate($refund);

        return [
            'id' => $refund->id,
            'booking' => $refund->booking,
            'customer' => $refund->booking?->customer ?: $refund->customer,
            'venue_cluster' => $refund->booking?->venueCluster,
            'payment' => $refund->payment,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'status_reason' => $refund->status_reason,
            'refund_destination' => $refund->refund_destination,
            'owner_confirm_note' => $refund->owner_confirm_note,
            'owner_confirmed_at' => $refund->owner_confirmed_at,
            'owner_confirmed_by' => $refund->ownerConfirmedBy,
            'created_at' => $refund->created_at,
            'policy_evaluation' => $policy,
            'histories' => $refund->statusHistories
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($history): array => [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'reason' => $history->reason,
                    'actor_type' => $history->actor_type,
                    'changed_by' => $history->changedBy,
                    'created_at' => $history->created_at,
                ])
                ->all(),
            'can_decide' => $refund->status === 'pending_owner_confirmation',
        ];
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
