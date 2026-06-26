<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Admin\AdminAuditService;
use App\Services\Payments\AdminPaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly AdminPaymentService $payments,
        private readonly AdminAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'payment.view');

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'payment_kind' => ['nullable', Rule::in(['full', 'deposit', 'partial'])],
            'method' => ['nullable', 'string', 'max:50'],
            'booking_status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'cancelled', 'completed', 'expired'])],
            'booking_source' => ['nullable', 'string', 'max:50'],
            'venue_cluster_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'gte:amount_min'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'paid_from' => ['nullable', 'date'],
            'paid_to' => ['nullable', 'date', 'after_or_equal:paid_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Payment::query()
            ->with([
                'booking:id,booking_code,customer_id,venue_cluster_id,total_price,payment_option,status,walk_in_name,walk_in_phone',
                'booking.customer:id,username,full_name,email,phone',
                'booking.venueCluster:id,name,owner_id',
                'subscription:id,user_id,package_id,billing_cycle,status,started_at,expires_at,paid_amount',
                'subscription.user:id,username,full_name,email,phone',
                'subscription.membershipPackage:id,name,type,badge_name',
            ])
            ->withCount('logs');

        $this->applyFilters($query, $data);

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'pending' => (clone $summaryQuery)->where('payments.status', 'pending')->count(),
            'paid' => (clone $summaryQuery)->where('payments.status', 'paid')->count(),
            'failed' => (clone $summaryQuery)->where('payments.status', 'failed')->count(),
            'refunded' => (clone $summaryQuery)->where('payments.status', 'refunded')->count(),
            'collected_amount' => (float) (clone $summaryQuery)->where('payments.status', 'paid')->sum('payments.amount'),
        ];

        $payments = $query
            ->latest('payments.created_at')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->through(fn(Payment $payment): array => $this->paymentPayload($payment));

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'payment.view');

        $payment = Payment::query()
            ->with([
                'booking.customer:id,username,full_name,email,phone',
                'booking.venueCluster:id,name,owner_id',
                'booking.venueCluster.owner:id,username,full_name,email,phone',
                'subscription.user:id,username,full_name,email,phone',
                'subscription.membershipPackage:id,name,type,badge_name',
                'systemBankAccount',
                'logs',
                'ownerWalletLedgers',
            ])
            ->findOrFail($id);

        $ownerWallet = null;
        if ($payment->booking && $payment->booking->venueCluster) {
            $ownerWallet = \App\Models\OwnerWallet::query()
                ->where('owner_id', $payment->booking->venueCluster->owner_id)
                ->where('venue_cluster_id', $payment->booking->venue_cluster_id)
                ->first();
        }

        return response()->json([
            'data' => [
                'payment' => $this->paymentPayload($payment),
                'owner_wallet' => $ownerWallet ? [
                    'id' => $ownerWallet->id,
                    'available_balance' => $ownerWallet->available_balance,
                    'pending_withdrawal_balance' => $ownerWallet->pending_withdrawal_balance,
                    'total_earned' => $ownerWallet->total_earned,
                    'total_withdrawn' => $ownerWallet->total_withdrawn,
                ] : null,
                'logs' => $payment->logs->map(fn($log): array => [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'request_payload' => $log->request_payload,
                    'response_payload' => $log->response_payload,
                    'status_before' => $log->status_before,
                    'status_after' => $log->status_after,
                    'gateway_txn_id' => $log->gateway_txn_id,
                    'error_code' => $log->error_code,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at,
                ])->values(),
                'owner_wallet_ledgers' => $payment->ownerWalletLedgers->map(fn($ledger): array => [
                    'id' => $ledger->id,
                    'type' => $ledger->type,
                    'direction' => $ledger->direction,
                    'amount' => $ledger->amount,
                    'balance_before' => $ledger->balance_before,
                    'balance_after' => $ledger->balance_after,
                    'status' => $ledger->status,
                    'transaction_code' => $ledger->transaction_code,
                    'reference_code' => $ledger->reference_code,
                    'description' => $ledger->description,
                    'created_at' => $ledger->created_at,
                ])->values(),
            ],
        ]);
    }

    public function retry(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'payment.manage');

        return response()->json([
            'message' => 'Thao tác này chỉ dành cho người dùng và chủ sân.',
        ], 403);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'payment.manage');

        return response()->json([
            'message' => 'Thao tác này chỉ dành cho người dùng và chủ sân.',
        ], 403);
    }

    private function applyFilters($query, array $data): void
    {
        $query
            ->when($data['keyword'] ?? null, function ($query, string $keyword): void {
                $keyword = '%' . $keyword . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('payments.payment_code', 'like', $keyword)
                        ->orWhere('payments.gateway_txn_id', 'like', $keyword)
                        ->orWhereHas('booking', fn($booking) => $booking
                            ->where('booking_code', 'like', $keyword)
                            ->orWhere('walk_in_name', 'like', $keyword)
                            ->orWhere('walk_in_phone', 'like', $keyword))
                        ->orWhereHas('booking.customer', fn($customer) => $customer
                            ->where('username', 'like', $keyword)
                            ->orWhere('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword))
                        ->orWhereHas('booking.venueCluster', fn($cluster) => $cluster->where('name', 'like', $keyword));
                });
            })
            ->when($data['status'] ?? null, fn($query, string $status) => $query->where('payments.status', $status))
            ->when($data['payment_kind'] ?? null, fn($query, string $kind) => $query->where('payments.payment_kind', $kind))
            ->when($data['method'] ?? null, fn($query, string $method) => $query->where('payments.method', $method))
            ->when($data['amount_min'] ?? null, fn($query, $amount) => $query->where('payments.amount', '>=', $amount))
            ->when($data['amount_max'] ?? null, fn($query, $amount) => $query->where('payments.amount', '<=', $amount))
            ->when($data['booking_status'] ?? null, fn($query, string $status) => $query->whereHas('booking', fn($booking) => $booking->where('status', $status)))
            ->when($data['booking_source'] ?? null, fn($query, string $source) => $query->whereHas('booking', fn($booking) => $booking->where('source', $source)))
            ->when($data['venue_cluster_id'] ?? null, fn($query, string $id) => $query->whereHas('booking', fn($booking) => $booking->where('venue_cluster_id', $id)))
            ->when($data['customer_id'] ?? null, fn($query, string $id) => $query->whereHas('booking', fn($booking) => $booking->where('customer_id', $id)))
            ->when($data['date_from'] ?? null, fn($query, string $date) => $query->whereDate('payments.created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn($query, string $date) => $query->whereDate('payments.created_at', '<=', $date))
            ->when($data['paid_from'] ?? null, fn($query, string $date) => $query->whereDate('payments.paid_at', '>=', $date))
            ->when($data['paid_to'] ?? null, fn($query, string $date) => $query->whereDate('payments.paid_at', '<=', $date));
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'payment_context' => $payment->payment_context ?? 'booking',
            'booking_id' => $payment->booking_id,
            'subscription_id' => $payment->subscription_id,
            'booking' => $payment->booking ? [
                'id' => $payment->booking->id,
                'booking_code' => $payment->booking->booking_code,
                'status' => $payment->booking->status,
                'total_price' => $payment->booking->total_price,
                'payment_option' => $payment->booking->payment_option,
            ] : null,
            'subscription' => $payment->subscription ? [
                'id' => $payment->subscription->id,
                'billing_cycle' => $payment->subscription->billing_cycle,
                'status' => $payment->subscription->status,
                'started_at' => $payment->subscription->started_at,
                'expires_at' => $payment->subscription->expires_at,
                'paid_amount' => $payment->subscription->paid_amount,
                'package' => $payment->subscription->membershipPackage,
            ] : null,
            'customer' => $this->customerPayload($payment),
            'venue_cluster' => $payment->booking?->venueCluster,
            'amount' => $payment->amount,
            'wallet_amount' => $payment->wallet_amount,
            'gateway_amount' => $payment->gateway_amount,
            'payment_kind' => $payment->payment_kind,
            'method' => $payment->method,
            'status' => $payment->status,
            'gateway_txn_id' => $payment->gateway_txn_id,
            'gateway_response' => $payment->gateway_response,
            'paid_at' => $payment->paid_at,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'logs_count' => (int) ($payment->logs_count ?? $payment->logs?->count() ?? 0),
            'can_retry' => false,
            'allowed_statuses' => [],
            'system_bank_account' => $payment->systemBankAccount ? [
                'id' => $payment->systemBankAccount->id,
                'bank_name' => $payment->systemBankAccount->bank_name,
                'account_number' => $payment->systemBankAccount->account_number,
                'account_holder' => $payment->systemBankAccount->account_holder,
            ] : null,
        ];
    }

    private function customerPayload(Payment $payment): ?array
    {
        $customer = $payment->booking?->customer;

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

        $subscriptionUser = $payment->subscription?->user;
        if ($subscriptionUser) {
            return [
                'id' => $subscriptionUser->id,
                'username' => $subscriptionUser->username,
                'full_name' => $subscriptionUser->full_name,
                'email' => $subscriptionUser->email,
                'phone' => $subscriptionUser->phone,
                'is_walk_in' => false,
            ];
        }

        if (! $payment->booking?->walk_in_name && ! $payment->booking?->walk_in_phone) {
            return null;
        }

        return [
            'id' => null,
            'username' => null,
            'full_name' => $payment->booking->walk_in_name ?: 'Khách tại quầy',
            'email' => null,
            'phone' => $payment->booking->walk_in_phone,
            'is_walk_in' => true,
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
