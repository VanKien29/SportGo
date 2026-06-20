<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public function __construct(
        private readonly OwnerWalletService $wallets,
        private readonly AdminAuditService $audit,
    ) {}

    public function wallets(Request $request): JsonResponse
    {
        $wallets = OwnerWallet::query()
            ->with('venueCluster:id,name,slug,address')
            ->where('owner_id', $request->user()->id)
            ->orderByDesc('available_balance')
            ->get();

        $bankAccounts = OwnerBankAccount::query()
            ->where('owner_id', $request->user()->id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->get();

        return response()->json([
            'data' => $wallets,
            'bank_accounts' => $bankAccounts,
        ]);
    }

    public function ledgers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wallet_id' => ['nullable', 'uuid'],
            'venue_cluster_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $ledgers = OwnerWalletLedger::query()
            ->with([
                'booking:id,booking_code,total_price',
                'payment:id,payment_code,method,gateway_txn_id',
                'venueCluster:id,name',
            ])
            ->where('owner_id', $request->user()->id)
            ->when($data['wallet_id'] ?? null, fn ($query, string $id) => $query->where('owner_wallet_id', $id))
            ->when($data['venue_cluster_id'] ?? null, fn ($query, string $id) => $query->where('venue_cluster_id', $id))
            ->latest()
            ->paginate(20);

        return response()->json($ledgers);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wallet_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $withdrawals = OwnerWithdrawalRequest::query()
            ->with([
                'wallet.venueCluster:id,name',
                'bankAccount:id,bank_name,bank_code,account_number,account_holder_name,branch_name,status',
                'reviewedBy:id,username,full_name',
                'completedBy:id,username,full_name',
            ])
            ->where('owner_id', $request->user()->id)
            ->when($data['wallet_id'] ?? null, fn ($query, string $id) => $query->where('owner_wallet_id', $id))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('requested_at')
            ->paginate(15);

        return response()->json($withdrawals);
    }

    public function storeWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_wallet_id' => ['required', 'uuid'],
            'owner_bank_account_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:50000'],
            'owner_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = DB::transaction(function () use ($request, $data): OwnerWithdrawalRequest {
            $wallet = OwnerWallet::query()
                ->whereKey($data['owner_wallet_id'])
                ->where('owner_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw ValidationException::withMessages([
                    'owner_wallet_id' => 'Ví không hợp lệ hoặc không thuộc quyền sở hữu của bạn.',
                ]);
            }

            $bankAccount = OwnerBankAccount::query()
                ->whereKey($data['owner_bank_account_id'])
                ->where('owner_id', $request->user()->id)
                ->where('status', 'active')
                ->first();

            if (! $bankAccount) {
                throw ValidationException::withMessages([
                    'owner_bank_account_id' => 'Tài khoản ngân hàng không hợp lệ hoặc chưa được kích hoạt.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount > (float) $wallet->available_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền rút vượt quá doanh thu online khả dụng.',
                ]);
            }

            $withdrawal = OwnerWithdrawalRequest::query()->create([
                'request_code' => $this->nextRequestCode(),
                'source' => 'manual',
                'owner_id' => $request->user()->id,
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => 'pending',
                'owner_note' => trim((string) ($data['owner_note'] ?? '')) ?: null,
                'metadata' => [
                    'balance_before_request' => (float) $wallet->available_balance,
                    'source_balance' => 'online_revenue',
                ],
                'requested_at' => now(),
            ]);

            $this->wallets->holdWithdrawal($withdrawal, [
                'source' => 'owner_request',
                'owner_id' => $request->user()->id,
            ]);

            return $withdrawal->fresh(['wallet.venueCluster', 'bankAccount']);
        });

        $this->audit->log(
            $request,
            'withdrawal',
            'withdrawal.owner_requested',
            'owner_withdrawal_requests',
            $withdrawal->id,
            [],
            $withdrawal->toArray(),
            [
                'context' => 'owner',
                'reason' => $withdrawal->owner_note,
                'metadata' => [
                    'wallet_id' => $withdrawal->owner_wallet_id,
                    'bank_account_id' => $withdrawal->owner_bank_account_id,
                    'amount' => (float) $withdrawal->amount,
                ],
            ],
        );
        $this->notifyAdmins($withdrawal);

        return response()->json([
            'message' => 'Đã gửi yêu cầu rút tiền. Số tiền được tạm giữ, SportGo sẽ chuyển khoản và đối soát.',
            'data' => $withdrawal,
        ], 201);
    }

    public function cancelWithdrawal(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = DB::transaction(function () use ($request, $id, $data): OwnerWithdrawalRequest {
            $withdrawal = OwnerWithdrawalRequest::query()
                ->whereKey($id)
                ->where('owner_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($withdrawal->status, ['pending', 'reviewing', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể hủy yêu cầu rút tiền đang chờ chuyển khoản.',
                ]);
            }

            $metadata = $withdrawal->metadata ?? [];
            if ($withdrawal->payout_qr_created_at || ! empty($metadata['mb_bulk_exported_at'])) {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu đã được admin bắt đầu chuyển khoản, không thể hủy từ phía chủ sân.',
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? '')) ?: 'Chủ sân hủy yêu cầu rút tiền.';

            if ($this->wallets->hasWithdrawalHold($withdrawal)) {
                $this->wallets->releaseWithdrawal($withdrawal, [
                    'reason' => $reason,
                    'owner_id' => $request->user()->id,
                    'source' => 'owner_cancelled',
                ]);
            }

            $withdrawal->forceFill([
                'status' => 'cancelled',
                'status_reason' => $reason,
                'metadata' => array_merge($metadata, [
                    'cancelled_at' => now()->toIso8601String(),
                    'cancelled_by' => $request->user()->id,
                    'cancelled_source' => 'owner',
                ]),
            ])->save();

            return $withdrawal->fresh(['wallet.venueCluster', 'bankAccount']);
        });

        $this->audit->log(
            $request,
            'withdrawal',
            'withdrawal.owner_cancelled',
            'owner_withdrawal_requests',
            $withdrawal->id,
            [],
            $withdrawal->toArray(),
            [
                'context' => 'owner',
                'reason' => $withdrawal->status_reason,
                'metadata' => [
                    'wallet_id' => $withdrawal->owner_wallet_id,
                    'amount' => (float) $withdrawal->amount,
                ],
            ],
        );

        return response()->json([
            'message' => 'Đã hủy yêu cầu rút tiền và hoàn lại số dư tạm giữ.',
            'data' => $withdrawal,
        ]);
    }

    private function nextRequestCode(): string
    {
        do {
            $code = 'WR-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (OwnerWithdrawalRequest::query()->where('request_code', $code)->exists());

        return $code;
    }

    private function notifyAdmins(OwnerWithdrawalRequest $withdrawal): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', [
                'super_admin',
                'admin',
                'finance_operator',
                'system_staff',
            ]))
            ->pluck('id')
            ->each(function (string $userId) use ($withdrawal): void {
                Notification::query()->create([
                    'user_id' => $userId,
                    'type' => 'owner_withdrawal_requested',
                    'title' => 'Có yêu cầu rút tiền mới',
                    'body' => sprintf(
                        '%s yêu cầu rút %sđ.',
                        $withdrawal->request_code,
                        number_format((float) $withdrawal->amount, 0, ',', '.')
                    ),
                    'reference_type' => 'owner_withdrawal_request',
                    'reference_id' => $withdrawal->id,
                    'data' => [
                        'owner_id' => $withdrawal->owner_id,
                        'wallet_id' => $withdrawal->owner_wallet_id,
                        'amount' => (float) $withdrawal->amount,
                    ],
                ]);
            });
    }
}
