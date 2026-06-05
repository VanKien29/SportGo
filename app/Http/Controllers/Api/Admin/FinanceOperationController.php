<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Refund;
use App\Models\VenueCluster;
use App\Services\Admin\AdminAuditService;
use App\Services\Finance\AdminRefundService;
use App\Services\Finance\AdminWithdrawalService;
use App\Services\Finance\MBBankBulkTransferExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class FinanceOperationController extends Controller
{
    public function __construct(
        private readonly AdminRefundService $refunds,
        private readonly AdminWithdrawalService $withdrawals,
        private readonly AdminAuditService $audit,
        private readonly MBBankBulkTransferExportService $mbBulkExport,
    ) {}

    public function refunds(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'refund.view');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending_confirmation', 'processing', 'completed', 'failed', 'rejected'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Refund::query()
            ->with([
                'payment:id,payment_code,booking_id,method,status,gateway_txn_id',
                'booking:id,booking_code,customer_id,venue_cluster_id,total_price,status',
                'booking.customer:id,username,full_name,email,phone',
                'booking.venueCluster:id,name,owner_id',
                'payoutAccount:id,user_id,bank_name,bank_account_number,bank_account_holder,bank_branch,status',
                'ownerConfirmedBy:id,username,full_name',
                'adminConfirmedBy:id,username,full_name',
                'receipt',
            ])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%'.$keyword.'%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->whereHas('payment', fn ($payment) => $payment->where('payment_code', 'like', $keyword))
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_code', 'like', $keyword))
                        ->orWhereHas('booking.customer', fn ($customer) => $customer
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword))
                        ->orWhereHas('booking.venueCluster', fn ($cluster) => $cluster->where('name', 'like', $keyword));
                });
            });

        $summary = [
            'total' => (clone $query)->count(),
            'pending_confirmation' => (clone $query)->where('status', 'pending_confirmation')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'requested_amount' => (float) (clone $query)->sum('amount'),
        ];

        $refunds = $query->latest()->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $refunds->getCollection()->map(fn (Refund $refund): array => $this->refundPayload($refund))->values(),
            'meta' => $this->paginationPayload($refunds),
            'summary' => $summary,
        ]);
    }

    public function updateRefund(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'refund.approve');
        $refund = Refund::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'completed', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
            'source' => ['nullable', Rule::in(['admin', 'gateway', 'mock'])],
            'gateway_refund_txn_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('refunds', 'gateway_refund_txn_id')->ignore($refund->id),
            ],
        ]);
        $oldValues = $refund->toArray();

        try {
            $updated = $this->refunds->updateStatus($refund, $data['status'], [
                'actor_id' => $request->user()->id,
                'reason' => $data['reason'] ?? 'Admin xử lý yêu cầu hoàn tiền.',
                'source' => $data['source'] ?? 'admin',
                'gateway_refund_txn_id' => $data['gateway_refund_txn_id'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request, 'refund', 'refund.status_updated', 'refunds', $updated->id, $oldValues, $updated->toArray(), [
            'reason' => $data['reason'] ?? null,
            'severity' => $data['status'] === 'completed' ? 'critical' : 'warning',
        ]);

        return response()->json([
            'message' => 'Đã cập nhật yêu cầu hoàn tiền.',
            'data' => $this->refundPayload($this->loadRefund($updated->id)),
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = OwnerWithdrawalRequest::query()
            ->with([
                'owner:id,username,full_name,email,phone',
                'wallet',
                'bankAccount',
                'reviewedBy:id,username,full_name',
                'completedBy:id,username,full_name',
                'receipt',
            ])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%'.$keyword.'%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('request_code', 'like', $keyword)
                        ->orWhereHas('owner', fn ($owner) => $owner
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword))
                        ->orWhereHas('bankAccount', fn ($account) => $account
                            ->where('account_number', 'like', $keyword)
                            ->orWhere('account_holder_name', 'like', $keyword));
                });
            });

        $summary = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->whereIn('status', ['pending', 'reviewing'])->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'requested_amount' => (float) (clone $query)->sum('amount'),
        ];

        $withdrawals = $query->latest('requested_at')->paginate((int) ($data['per_page'] ?? 20));
        $clusters = VenueCluster::query()
            ->whereIn('owner_id', $withdrawals->getCollection()->pluck('owner_id')->unique())
            ->get(['id', 'owner_id', 'name'])
            ->groupBy('owner_id');

        return response()->json([
            'data' => $withdrawals->getCollection()
                ->map(fn (OwnerWithdrawalRequest $withdrawal): array => $this->withdrawalPayload($withdrawal, $clusters->get($withdrawal->owner_id, collect())->pluck('name')->values()->all()))
                ->values(),
            'meta' => $this->paginationPayload($withdrawals),
            'summary' => $summary,
        ]);
    }

    public function updateWithdrawal(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');
        $withdrawal = OwnerWithdrawalRequest::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'completed'])],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
            'source' => ['nullable', Rule::in(['admin', 'mbbank_callback', 'mock'])],
            'transfer_reference' => ['nullable', 'string', 'max:100', 'required_if:status,completed'],
        ]);
        $oldValues = $withdrawal->toArray();

        try {
            $updated = $this->withdrawals->updateStatus($withdrawal, $data['status'], [
                'actor_id' => $request->user()->id,
                'reason' => $data['reason'] ?? 'Admin xử lý yêu cầu rút tiền.',
                'source' => $data['source'] ?? 'admin',
                'transfer_reference' => $data['transfer_reference'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request, 'withdrawal', 'withdrawal.status_updated', 'owner_withdrawal_requests', $updated->id, $oldValues, $updated->toArray(), [
            'reason' => $data['reason'] ?? null,
            'severity' => $data['status'] === 'completed' ? 'critical' : 'warning',
        ]);

        $clusters = VenueCluster::query()->where('owner_id', $updated->owner_id)->pluck('name')->all();

        return response()->json([
            'message' => 'Đã cập nhật yêu cầu rút tiền.',
            'data' => $this->withdrawalPayload($this->loadWithdrawal($updated->id), $clusters),
        ]);
    }

    public function exportWithdrawals(Request $request): Response|JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        $withdrawals = OwnerWithdrawalRequest::query()
            ->with('bankAccount')
            ->whereIn('id', $data['ids'])
            ->get();

        if ($withdrawals->count() !== count($data['ids']) || $withdrawals->contains(fn ($item) => $item->status !== 'approved')) {
            return response()->json(['message' => 'Chỉ được export các yêu cầu rút tiền đã duyệt.'], 422);
        }

        $batchCode = 'MBBULK-'.now()->format('YmdHis');

        foreach ($withdrawals as $withdrawal) {
            $withdrawal->update([
                'metadata' => array_merge($withdrawal->metadata ?? [], [
                    'mb_bulk_batch_code' => $batchCode,
                    'mb_bulk_exported_at' => now()->toIso8601String(),
                    'mb_bulk_exported_by' => $request->user()->id,
                ]),
            ]);
        }

        return response($this->mbBulkExport->build($withdrawals), 200, [
            'Content-Disposition' => 'attachment; filename="'.$batchCode.'.xlsx"',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function loadRefund(string $id): Refund
    {
        return Refund::query()->with([
            'payment:id,payment_code,booking_id,method,status,gateway_txn_id',
            'booking.customer:id,username,full_name,email,phone',
            'booking.venueCluster:id,name,owner_id',
            'payoutAccount',
            'ownerConfirmedBy:id,username,full_name',
            'adminConfirmedBy:id,username,full_name',
            'receipt',
        ])->findOrFail($id);
    }

    private function loadWithdrawal(string $id): OwnerWithdrawalRequest
    {
        return OwnerWithdrawalRequest::query()->with([
            'owner:id,username,full_name,email,phone',
            'wallet',
            'bankAccount',
            'reviewedBy:id,username,full_name',
            'completedBy:id,username,full_name',
            'receipt',
        ])->findOrFail($id);
    }

    private function refundPayload(Refund $refund): array
    {
        $destination = match ($refund->refund_destination) {
            'bank_account' => [
                'type' => 'bank_account',
                'label' => $refund->payoutAccount?->bank_name,
                'account_number' => $refund->payoutAccount?->bank_account_number,
                'account_holder' => $refund->payoutAccount?->bank_account_holder,
            ],
            'user_wallet' => ['type' => 'user_wallet', 'label' => 'Ví SportGo'],
            default => ['type' => 'original_payment', 'label' => 'Phương thức gốc: '.strtoupper((string) $refund->payment?->method)],
        };

        return [
            'id' => $refund->id,
            'booking' => $refund->booking,
            'payment' => $refund->payment,
            'customer' => $refund->booking?->customer,
            'venue_cluster' => $refund->booking?->venueCluster,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'status_reason' => $refund->status_reason,
            'refund_destination' => $destination,
            'owner_confirmation' => [
                'confirmed' => (bool) $refund->owner_confirmed_at,
                'confirmed_at' => $refund->owner_confirmed_at,
                'confirmed_by' => $refund->ownerConfirmedBy,
                'note' => $refund->owner_confirm_note,
            ],
            'gateway_refund_txn_id' => $refund->gateway_refund_txn_id,
            'processed_at' => $refund->processed_at,
            'created_at' => $refund->created_at,
            'receipt' => $this->receiptPayload($refund->receipt),
            'allowed_statuses' => [
                'pending_confirmation' => ['processing', 'rejected'],
                'processing' => ['completed', 'rejected'],
                'failed' => ['processing', 'rejected'],
            ][$refund->status] ?? [],
        ];
    }

    private function withdrawalPayload(OwnerWithdrawalRequest $withdrawal, array $clusterNames): array
    {
        return [
            'id' => $withdrawal->id,
            'request_code' => $withdrawal->request_code,
            'owner' => $withdrawal->owner,
            'venue_clusters' => $clusterNames,
            'wallet' => [
                'available_balance' => $withdrawal->wallet?->available_balance,
                'pending_withdrawal_balance' => $withdrawal->wallet?->pending_withdrawal_balance,
                'total_earned' => $withdrawal->wallet?->total_earned,
                'total_withdrawn' => $withdrawal->wallet?->total_withdrawn,
            ],
            'bank_account' => $withdrawal->bankAccount,
            'amount' => $withdrawal->amount,
            'status' => $withdrawal->status,
            'owner_note' => $withdrawal->owner_note,
            'review_note' => $withdrawal->review_note,
            'status_reason' => $withdrawal->status_reason,
            'transfer_reference' => $withdrawal->transfer_reference,
            'metadata' => $withdrawal->metadata,
            'requested_at' => $withdrawal->requested_at,
            'reviewed_at' => $withdrawal->reviewed_at,
            'completed_at' => $withdrawal->completed_at,
            'receipt' => $this->receiptPayload($withdrawal->receipt),
            'allowed_statuses' => [
                'pending' => ['approved', 'rejected'],
                'reviewing' => ['approved', 'rejected'],
                'approved' => ['completed', 'rejected'],
            ][$withdrawal->status] ?? [],
        ];
    }

    private function receiptPayload($receipt): ?array
    {
        if (! $receipt) {
            return null;
        }

        return [
            'id' => $receipt->id,
            'receipt_code' => $receipt->receipt_code,
            'title' => $receipt->title,
            'amount' => $receipt->amount,
            'status' => $receipt->status,
            'issued_at' => $receipt->issued_at,
            'metadata' => $receipt->metadata,
        ];
    }

    private function paginationPayload($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        $roles = $user->roles()->pluck('roles.name')->all();

        if (array_intersect($roles, ['super_admin', 'admin'])) {
            return;
        }

        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->where('permissions.code', $permission)
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}
