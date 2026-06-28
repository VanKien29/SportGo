<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Refund;
use App\Models\UserWithdrawalRequest;
use App\Models\UserPayoutAccount;
use App\Models\VenueCluster;
use App\Services\Admin\AdminAuditService;
use App\Services\Finance\AdminRefundService;
use App\Services\Finance\AdminWithdrawalService;
use App\Services\Finance\MBBankBulkTransferExportService;
use App\Services\Finance\SepayPayoutService;
use App\Services\Finance\UserWithdrawalPaymentService;
use App\Services\Policies\RefundPolicyEvaluator;
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
        private readonly SepayPayoutService $sepayPayouts,
        private readonly RefundPolicyEvaluator $refundPolicies,
        private readonly UserWithdrawalPaymentService $userWithdrawalPayments,
    ) {}

    public function refunds(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'refund.view');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'pending_confirmation',
                'pending_owner_confirmation',
                'owner_confirmed',
                'owner_rejected',
                'admin_processing',
                'processing',
                'completed',
                'completed_cash',
                'failed',
                'rejected',
                'cancelled',
            ])],
            'refund_destination' => ['nullable', Rule::in(['bank_account', 'user_wallet', 'original_payment', 'cash'])],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_kind' => ['nullable', Rule::in(['full', 'deposit', 'partial'])],
            'venue_cluster_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'owner_confirmed' => ['nullable', Rule::in(['yes', 'no'])],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'gte:amount_min'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Refund::query()
            ->with([
                'payment:id,payment_code,booking_id,amount,method,payment_kind,status,gateway_txn_id',
                'booking:id,booking_code,customer_id,venue_cluster_id,total_price,status,booking_date,start_time,end_time,cancelled_at,walk_in_name,walk_in_phone',
                'booking.customer:id,username,full_name,email,phone',
                'booking.venueCluster:id,name,owner_id',
                'payoutAccount:id,user_id,bank_name,bank_account_number,bank_account_holder,bank_branch,status',
                'ownerConfirmedBy:id,username,full_name',
                'adminConfirmedBy:id,username,full_name',
                'cashRefundedBy:id,username,full_name',
                'receipt',
            ])
            ->when($data['status'] ?? null, fn($query, string $status) => $query->where('status', $status));

        $this->applyRefundFilters($query, $data);

        $summary = [
            'total' => (clone $query)->count(),
            'pending_confirmation' => (clone $query)->whereIn('status', ['pending_confirmation', 'pending_owner_confirmation'])->count(),
            'processing' => (clone $query)->whereIn('status', $this->refundPayableStatuses())->count(),
            'completed' => (clone $query)->whereIn('status', ['completed', 'completed_cash'])->count(),
            'requested_amount' => (float) (clone $query)->sum('amount'),
        ];

        $refunds = $query->latest()->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $refunds->getCollection()->map(fn(Refund $refund): array => $this->refundPayload($refund))->values(),
            'meta' => $this->paginationPayload($refunds),
            'summary' => $summary,
        ]);
    }

    public function updateRefund(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'refund.approve');

        return response()->json([
            'message' => 'Hoàn tiền do chủ sân xác nhận sẽ tự cộng vào ví khách. Admin chỉ xem lịch sử hoàn tiền.',
        ], 422);
    }

    public function refundPayoutQr(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'refund.approve');

        return response()->json([
            'message' => 'Hoàn tiền hiện được cộng trực tiếp vào ví SportGo của khách, không tạo QR chuyển khoản.',
        ], 422);
    }

    public function checkRefundPayout(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'refund.approve');

        return response()->json([
            'message' => 'Hoàn tiền hiện xử lý vào ví khách hàng, không đối soát giao dịch chuyển khoản.',
        ], 422);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])],
            'owner_id' => ['nullable', 'uuid'],
            'bank_code' => ['nullable', 'string', 'max:30'],
            'venue_cluster_id' => ['nullable', 'uuid'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'gte:amount_min'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
            ->when($data['status'] ?? null, fn($query, string $status) => $query->where('status', $status));

        $this->applyWithdrawalFilters($query, $data);

        $summary = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->whereIn('status', $this->withdrawalPayableStatuses())->count(),
            'approved' => 0,
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
                ->map(fn(OwnerWithdrawalRequest $withdrawal): array => $this->withdrawalPayload($withdrawal, $clusters->get($withdrawal->owner_id, collect())->pluck('name')->values()->all()))
                ->values(),
            'meta' => $this->paginationPayload($withdrawals),
            'summary' => $summary,
        ]);
    }

    public function userWithdrawals(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'paid', 'cancelled'])],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'gte:amount_min'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = UserWithdrawalRequest::query()
            ->with([
                'user:id,username,full_name,email,phone',
                'wallet',
                'payoutAccount',
                'approvedBy:id,username,full_name',
                'paidBy:id,username,full_name',
                'receipt',
            ])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));

        $this->applyUserWithdrawalFilters($query, $data);

        $summary = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->whereIn('status', ['pending', 'approved'])->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'completed' => (clone $query)->where('status', 'paid')->count(),
            'requested_amount' => (float) (clone $query)->sum('amount'),
        ];

        $withdrawals = $query->latest('requested_at')->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $withdrawals->getCollection()
                ->map(fn (UserWithdrawalRequest $withdrawal): array => $this->userWithdrawalPayload($withdrawal))
                ->values(),
            'meta' => $this->paginationPayload($withdrawals),
            'summary' => $summary,
        ]);
    }

    public function payUserWithdrawal(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['bank_transfer'])],
            'transfer_reference' => ['nullable', 'string', 'max:100', 'required_if:payment_method,bank_transfer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $withdrawal = UserWithdrawalRequest::query()->findOrFail($id);
        $oldValues = $withdrawal->toArray();

        try {
            $updated = $this->userWithdrawalPayments->pay(
                $withdrawal,
                $request->user(),
                $data['payment_method'],
                $data['transfer_reference'] ?? null,
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request, 'withdrawal', 'user_withdrawal.paid', 'user_withdrawal_requests', $updated->id, $oldValues, $updated->toArray(), [
            'payment_method' => $data['payment_method'],
            'transfer_reference' => $data['transfer_reference'] ?? null,
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Đã ghi nhận chi trả rút tiền người dùng.',
            'data' => $this->userWithdrawalPayload($updated),
        ]);
    }

    public function userWithdrawalPayoutQr(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        try {
            $payout = $this->sepayPayouts->userWithdrawalQr($this->loadUserWithdrawal($id));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã tạo QR chi trả rút tiền người dùng.',
            'data' => $payout,
        ]);
    }

    public function checkUserWithdrawalPayout(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        try {
            $result = $this->sepayPayouts->checkUserWithdrawal(
                $this->loadUserWithdrawal($id),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payload = [
            'message' => $result['message'] ?? ($result['completed'] ? 'Đã đối soát rút tiền người dùng thành công.' : 'Chưa tìm thấy giao dịch phù hợp.'),
            'completed' => (bool) ($result['completed'] ?? false),
            'transaction' => $result['transaction'] ?? null,
            'payout' => $result['payout'] ?? null,
        ];

        if ($payload['completed']) {
            $payload['data'] = $this->userWithdrawalPayload($this->loadUserWithdrawal($id));
        }

        return response()->json($payload);
    }

    public function updateWithdrawal(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');
        $withdrawal = OwnerWithdrawalRequest::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in(['admin', 'sepay_outbound', 'mock'])],
            'transfer_reference' => ['nullable', 'string', 'max:100', 'required_if:status,completed'],
        ]);
        $oldValues = $withdrawal->toArray();

        $source = $data['source'] ?? 'admin';
        if ($data['status'] === 'completed' && $source === 'admin') {
            return response()->json(['message' => 'Không thể hoàn tất thủ công. Vui lòng đối soát tự động qua SePay.'], 422);
        }

        try {
            $updated = $this->withdrawals->updateStatus($withdrawal, $data['status'], [
                'actor_id' => $request->user()->id,
                'reason' => $data['reason'] ?? 'Admin xử lý yêu cầu rút tiền.',
                'source' => $source,
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

    public function withdrawalPayoutQr(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        try {
            $payout = $this->sepayPayouts->withdrawalQr($this->loadWithdrawal($id));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã tạo QR chuyển khoản rút tiền.',
            'data' => $payout,
        ]);
    }

    public function checkWithdrawalPayout(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'withdrawal.manage');

        try {
            $result = $this->sepayPayouts->checkWithdrawal($this->loadWithdrawal($id), $request->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payload = [
            'message' => $result['message'] ?? ($result['completed'] ? 'Đã đối soát rút tiền thành công.' : 'Chưa tìm thấy giao dịch phù hợp.'),
            'completed' => (bool) ($result['completed'] ?? false),
            'transaction' => $result['transaction'] ?? null,
            'payout' => $result['payout'] ?? null,
        ];

        if ($payload['completed']) {
            $clusters = VenueCluster::query()->where('owner_id', $this->loadWithdrawal($id)->owner_id)->pluck('name')->all();
            $payload['data'] = $this->withdrawalPayload($this->loadWithdrawal($id), $clusters);
        }

        return response()->json($payload);
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

        $invalid = $withdrawals->count() !== count($data['ids'])
            || $withdrawals->contains(fn(OwnerWithdrawalRequest $item): bool => ! in_array($item->status, $this->withdrawalPayableStatuses(), true)
                || ! $item->bankAccount
                || $item->bankAccount->status !== 'active');

        if ($invalid) {
            return response()->json(['message' => 'Chỉ được export các yêu cầu rút tiền đang chờ chuyển khoản và có tài khoản nhận tiền hợp lệ.'], 422);
        }

        $batchCode = 'MBBULK-' . now()->format('YmdHis');

        foreach ($withdrawals as $withdrawal) {
            $withdrawal->update([
                'metadata' => array_merge($withdrawal->metadata ?? [], [
                    'mb_bulk_batch_code' => $batchCode,
                    'mb_bulk_exported_at' => now()->toIso8601String(),
                    'mb_bulk_exported_by' => $request->user()->id,
                ]),
            ]);
        }

        $rows = $withdrawals->values()->map(fn(OwnerWithdrawalRequest $withdrawal): array => [
            'account_number' => (string) $withdrawal->bankAccount?->account_number,
            'account_holder_name' => (string) $withdrawal->bankAccount?->account_holder_name,
            'bank_code' => (string) $withdrawal->bankAccount?->bank_code,
            'bank_name' => (string) $withdrawal->bankAccount?->bank_name,
            'amount' => (int) $withdrawal->amount,
            'content' => $this->sepayPayouts->ensureWithdrawalTransferCode($withdrawal),
        ]);

        return response($this->mbBulkExport->buildRows($rows), 200, [
            'Content-Disposition' => 'attachment; filename="' . $batchCode . '.xlsx"',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportRefunds(Request $request): Response|JsonResponse
    {
        $this->authorizePermission($request, 'refund.approve');

        return response()->json([
            'message' => 'Hoàn tiền được xử lý vào ví SportGo của khách nên không export chuyển khoản ngân hàng.',
        ], 422);
    }

    private function applyRefundFilters($query, array $data): void
    {
        $query
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%' . $keyword . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('reason', 'like', $keyword)
                        ->orWhere('gateway_refund_txn_id', 'like', $keyword)
                        ->orWhereHas('payment', fn($payment) => $payment
                            ->where('payment_code', 'like', $keyword)
                            ->orWhere('gateway_txn_id', 'like', $keyword))
                        ->orWhereHas('booking', fn($booking) => $booking->where('booking_code', 'like', $keyword))
                        ->orWhereHas('booking.customer', fn($customer) => $customer
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword))
                        ->orWhereHas('booking.venueCluster', fn($cluster) => $cluster->where('name', 'like', $keyword))
                        ->orWhereHas('payoutAccount', fn($account) => $account
                            ->where('bank_name', 'like', $keyword)
                            ->orWhere('bank_account_number', 'like', $keyword)
                            ->orWhere('bank_account_holder', 'like', $keyword));
                });
            })
            ->when($data['refund_destination'] ?? null, fn($query, string $destination) => $query->where('refund_destination', $destination))
            ->when($data['payment_method'] ?? null, fn($query, string $method) => $query->whereHas('payment', fn($payment) => $payment->where('method', $method)))
            ->when($data['payment_kind'] ?? null, fn($query, string $kind) => $query->whereHas('payment', fn($payment) => $payment->where('payment_kind', $kind)))
            ->when($data['venue_cluster_id'] ?? null, fn($query, string $id) => $query->whereHas('booking', fn($booking) => $booking->where('venue_cluster_id', $id)))
            ->when($data['customer_id'] ?? null, fn($query, string $id) => $query->where('customer_id', $id))
            ->when(($data['owner_confirmed'] ?? null) === 'yes', fn($query) => $query->whereNotNull('owner_confirmed_at'))
            ->when(($data['owner_confirmed'] ?? null) === 'no', fn($query) => $query->whereNull('owner_confirmed_at'))
            ->when($data['amount_min'] ?? null, fn($query, $amount) => $query->where('amount', '>=', $amount))
            ->when($data['amount_max'] ?? null, fn($query, $amount) => $query->where('amount', '<=', $amount))
            ->when($data['date_from'] ?? null, fn($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn($query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function applyWithdrawalFilters($query, array $data): void
    {
        $query
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%' . $keyword . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('request_code', 'like', $keyword)
                        ->orWhere('owner_note', 'like', $keyword)
                        ->orWhere('transfer_reference', 'like', $keyword)
                        ->orWhereHas('owner', fn($owner) => $owner
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword))
                        ->orWhereHas('bankAccount', fn($account) => $account
                            ->where('bank_name', 'like', $keyword)
                            ->orWhere('bank_code', 'like', $keyword)
                            ->orWhere('account_number', 'like', $keyword)
                            ->orWhere('account_holder_name', 'like', $keyword));
                });
            })
            ->when($data['owner_id'] ?? null, fn($query, string $id) => $query->where('owner_id', $id))
            ->when($data['bank_code'] ?? null, fn($query, string $code) => $query->whereHas('bankAccount', fn($account) => $account->where('bank_code', $code)))
            ->when($data['venue_cluster_id'] ?? null, fn($query, string $id) => $query->whereIn('owner_id', VenueCluster::query()->select('owner_id')->whereKey($id)))
            ->when($data['amount_min'] ?? null, fn($query, $amount) => $query->where('amount', '>=', $amount))
            ->when($data['amount_max'] ?? null, fn($query, $amount) => $query->where('amount', '<=', $amount))
            ->when($data['date_from'] ?? null, fn($query, string $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn($query, string $date) => $query->whereDate('requested_at', '<=', $date));
    }

    private function applyUserWithdrawalFilters($query, array $data): void
    {
        $query
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%' . $keyword . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('id', 'like', $keyword)
                        ->orWhere('rejected_reason', 'like', $keyword)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword))
                        ->orWhereHas('payoutAccount', fn ($account) => $account
                            ->where('bank_name', 'like', $keyword)
                            ->orWhere('bank_account_number', 'like', $keyword)
                            ->orWhere('bank_account_holder', 'like', $keyword));
                });
            })
            ->when($data['amount_min'] ?? null, fn ($query, $amount) => $query->where('amount', '>=', $amount))
            ->when($data['amount_max'] ?? null, fn ($query, $amount) => $query->where('amount', '<=', $amount))
            ->when($data['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('requested_at', '<=', $date));
    }

    private function loadRefund(string $id): Refund
    {
        return Refund::query()->with([
            'payment:id,payment_code,booking_id,amount,method,payment_kind,status,gateway_txn_id',
            'booking:id,booking_code,customer_id,venue_cluster_id,total_price,status,booking_date,start_time,end_time,cancelled_at,walk_in_name,walk_in_phone',
            'booking.customer:id,username,full_name,email,phone',
            'booking.venueCluster:id,name,owner_id',
            'payoutAccount',
            'ownerConfirmedBy:id,username,full_name',
            'adminConfirmedBy:id,username,full_name',
            'cashRefundedBy:id,username,full_name',
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

    private function loadUserWithdrawal(string $id): UserWithdrawalRequest
    {
        return UserWithdrawalRequest::query()->with([
            'user:id,username,full_name,email,phone',
            'wallet',
            'payoutAccount',
            'approvedBy:id,username,full_name',
            'paidBy:id,username,full_name',
            'receipt',
        ])->findOrFail($id);
    }

    private function refundPayload(Refund $refund): array
    {
        $destination = match ($refund->refund_destination) {
            'cash' => ['type' => 'cash', 'label' => 'Tiền mặt tại sân'],
            default => ['type' => 'user_wallet', 'label' => 'Ví SportGo'],
        };
        $customer = $this->refundCustomerPayload($refund);
        $policyEvaluation = $this->formatPolicyEvaluation($this->refundPolicies->evaluate($refund));
        $hasWalletRecipient = (bool) (
            $refund->customer_id
            ?: $refund->booking?->customer_id
            ?: $refund->booking?->walk_in_phone
        );
        $walletBlockedReason = $this->walletRefundBlockedReason($refund, $policyEvaluation, $hasWalletRecipient);

        return [
            'id' => $refund->id,
            'booking' => $refund->booking,
            'payment' => $refund->payment,
            'customer' => $customer,
            'venue_cluster' => $refund->booking?->venueCluster,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'status_reason' => $refund->status_reason,
            'refund_destination' => $destination,
            'owner_confirmation' => [
                'confirmed' => (bool) $refund->owner_confirmed_at,
                'decision' => match ($refund->status) {
                    'owner_rejected' => 'rejected',
                    'owner_confirmed', 'admin_processing', 'processing', 'completed', 'completed_cash', 'failed' => 'approved',
                    default => 'pending',
                },
                'confirmed_at' => $refund->owner_confirmed_at,
                'confirmed_by' => $refund->ownerConfirmedBy,
                'note' => $refund->owner_confirm_note,
            ],
            'gateway_refund_txn_id' => $refund->gateway_refund_txn_id,
            'payout_transfer_code' => $refund->payout_transfer_code,
            'processed_at' => $refund->processed_at,
            'created_at' => $refund->created_at,
            'receipt' => $this->receiptPayload($refund->receipt),
            'policy_evaluation' => $policyEvaluation,
            'can_pay_by_qr' => false,
            'can_complete_wallet_refund' => false,
            'can_complete_cash_refund' => false,
            'wallet_refund_blocked_reason' => $walletBlockedReason,
            'cash_refund' => [
                'refunded_by' => $refund->cashRefundedBy,
                'refunded_at' => $refund->cash_refunded_at,
                'note' => $refund->cash_refund_note,
            ],
            'allowed_statuses' => [],
        ];
    }

    private function walletRefundBlockedReason(Refund $refund, array $policyEvaluation, bool $hasWalletRecipient): ?string
    {
        if (! $hasWalletRecipient) {
            return 'Booking tại quầy chưa có tài khoản hoặc số điện thoại khách hàng để hoàn vào ví SportGo.';
        }

        if ((float) $refund->amount <= 0) {
            return 'Số tiền hoàn phải lớn hơn 0đ.';
        }

        if (($policyEvaluation['compliant'] ?? null) === false) {
            return $policyEvaluation['warning'] ?: 'Số tiền hoàn đang vượt mức chính sách hiện tại.';
        }

        $detail = $policyEvaluation['detail'] ?? null;
        if (is_array($detail)) {
            $refundPercent = $detail['refund_percent'] ?? null;
            $suggestedAmount = $detail['suggested_amount'] ?? null;

            if ($refundPercent !== null && (float) $refundPercent <= 0) {
                return 'Chính sách hiện tại cho hoàn 0đ.';
            }

            if ($suggestedAmount !== null && (float) $suggestedAmount <= 0) {
                return 'Chính sách hiện tại cho hoàn tối đa 0đ.';
            }
        }

        return null;
    }

    private function refundCustomerPayload(Refund $refund): ?array
    {
        $customer = $refund->booking?->customer;

        if ($customer) {
            return [
                'id' => $customer->id,
                'username' => $customer->username,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_walk_in' => false,
            ];
        }

        if ($refund->booking?->walk_in_name || $refund->booking?->walk_in_phone) {
            return [
                'id' => null,
                'username' => null,
                'full_name' => $refund->booking->walk_in_name ?: 'Khách tại quầy',
                'email' => null,
                'phone' => $refund->booking->walk_in_phone,
                'is_walk_in' => true,
            ];
        }

        return null;
    }

    private function resolveRefundPayoutAccount(Refund $refund, bool $persist = false): ?UserPayoutAccount
    {
        if (! in_array($refund->refund_destination, ['bank_account', 'original_payment'], true)) {
            return null;
        }

        $account = $refund->payoutAccount;

        if (! $account || $account->status !== 'active' || blank($account->bank_account_number)) {
            $customerId = $refund->customer_id ?: $refund->booking?->customer_id;

            if (! $customerId && ! $refund->relationLoaded('booking')) {
                $customerId = $refund->booking()->value('customer_id');
            }

            $account = $customerId ? UserPayoutAccount::query()
                ->where('user_id', $customerId)
                ->where('status', 'active')
                ->whereNotNull('bank_account_number')
                ->orderByDesc('is_default')
                ->latest()
                ->first() : null;
        }

        if (! $account || $account->status !== 'active' || blank($account->bank_account_number)) {
            return null;
        }

        if ($persist && ($refund->refund_destination !== 'bank_account' || $refund->user_payout_account_id !== $account->id)) {
            $refund->forceFill([
                'refund_destination' => 'bank_account',
                'user_payout_account_id' => $account->id,
            ])->save();
            $refund->setRelation('payoutAccount', $account);
        }

        return $account;
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
            'payout_transfer_code' => $withdrawal->payout_transfer_code,
            'metadata' => $withdrawal->metadata,
            'requested_at' => $withdrawal->requested_at,
            'reviewed_at' => $withdrawal->reviewed_at,
            'completed_at' => $withdrawal->completed_at,
            'receipt' => $this->receiptPayload($withdrawal->receipt),
            'can_pay_by_qr' => in_array($withdrawal->status, $this->withdrawalPayableStatuses(), true)
                && $withdrawal->bankAccount?->status === 'active'
                && filled($withdrawal->bankAccount?->account_number),
            'allowed_statuses' => [],
        ];
    }

    private function userWithdrawalPayload(UserWithdrawalRequest $withdrawal): array
    {
        $canPay = in_array($withdrawal->status, ['pending', 'approved'], true);
        $canPayBankTransfer = $canPay
            && $withdrawal->payoutAccount?->status === 'active'
            && filled($withdrawal->payoutAccount?->bank_account_number);

        return [
            'id' => $withdrawal->id,
            'request_code' => 'UWD-'.strtoupper(substr(hash('sha256', $withdrawal->id), 0, 10)),
            'requester_type' => 'user',
            'owner' => $withdrawal->user,
            'venue_clusters' => ['Ví người dùng'],
            'wallet' => [
                'available_balance' => $withdrawal->wallet?->balance,
                'pending_withdrawal_balance' => $withdrawal->wallet?->locked_balance,
                'total_earned' => null,
                'total_withdrawn' => null,
            ],
            'bank_account' => $withdrawal->payoutAccount ? [
                'id' => $withdrawal->payoutAccount->id,
                'bank_name' => $withdrawal->payoutAccount->bank_name,
                'bank_code' => null,
                'account_number' => $withdrawal->payoutAccount->bank_account_number,
                'account_holder_name' => $withdrawal->payoutAccount->bank_account_holder,
                'status' => $withdrawal->payoutAccount->status,
            ] : null,
            'amount' => $withdrawal->amount,
            'status' => $withdrawal->status,
            'owner_note' => '-',
            'review_note' => null,
            'status_reason' => $withdrawal->rejected_reason,
            'requested_at' => $withdrawal->requested_at,
            'completed_at' => $withdrawal->paid_at,
            'payment_method' => $withdrawal->payment_method,
            'transfer_reference' => $withdrawal->transfer_reference,
            'paid_note' => $withdrawal->paid_note,
            'payout_transfer_code' => $withdrawal->payout_transfer_code,
            'receipt' => $this->receiptPayload($withdrawal->receipt),
            'can_pay_by_qr' => false,
            'can_pay_cash' => false,
            'can_pay_bank_transfer' => $canPayBankTransfer,
            'allowed_statuses' => [],
        ];
    }

    private function refundPayableStatuses(): array
    {
        return [];
    }

    private function withdrawalPayableStatuses(): array
    {
        return ['pending', 'reviewing', 'approved'];
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

    private function formatPolicyEvaluation(array $raw): array
    {
        $result = array_merge($raw, [
            'evaluated' => (bool) ($raw['evaluated'] ?? false),
            'compliant' => $raw['compliant'] ?? null,
            'summary' => $raw['summary'] ?? null,
            'warning' => $raw['warning'] ?? null,
            'source' => $raw['source'] ?? null,
            'is_owner_fault_refund' => (bool) ($raw['is_owner_fault_refund'] ?? false),
            'detail' => null,
            'violations' => [],
        ]);

        if ($result['evaluated'] && $result['source'] !== 'no_rule') {
            $result['detail'] = [
                'refund_percent' => $raw['refund_percent'] ?? null,
                'suggested_amount' => $raw['suggested_amount'] ?? null,
                'requested_amount' => $raw['requested_amount'] ?? null,
                'paid_amount' => $raw['paid_amount'] ?? null,
                'hours_before_start' => $raw['hours_before_start'] ?? null,
                'requires_admin_review' => (bool) ($raw['requires_admin_review'] ?? false),
                'rule_name' => $raw['rule']['name'] ?? null,
                'rule_type' => $raw['rule']['type'] ?? null,
                'policy_title' => $raw['policy']['title'] ?? null,
                'source_label' => match ($raw['source'] ?? null) {
                    'venue_policy_rule' => 'Chính sách riêng của cụm sân',
                    'system_policy_rule' => 'Chính sách mặc định hệ thống',
                    'booking_config' => 'Cấu hình đặt sân',
                    'owner_fault_100' => 'Hoàn 100% do lỗi phía sân',
                    default => null,
                },
            ];
        }

        if (($raw['compliant'] ?? null) === false) {
            $suggested = number_format((float) ($raw['suggested_amount'] ?? 0), 0, ',', '.');
            $requested = number_format((float) ($raw['requested_amount'] ?? 0), 0, ',', '.');
            $result['violations'][] = [
                'code' => 'amount_exceeds_policy',
                'message' => "Số tiền yêu cầu ({$requested}đ) vượt mức chính sách cho phép ({$suggested}đ).",
            ];
        }

        if (($raw['requires_admin_review'] ?? false) && ($raw['refund_percent'] ?? 100) === 0) {
            $result['violations'][] = [
                'code' => 'zero_refund_policy',
                'message' => 'Chính sách hiện tại cho hoàn 0%. Admin cần xem xét đặc biệt.',
            ];
        }

        return $result;
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
