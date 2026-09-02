<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Booking;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Payments\PlatformFeeWalletService;
use App\Services\Partner\PartnerTerminationFlowService;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    private const TERMINATION_WITHDRAWAL_LIMIT_STATUSES = [
        PartnerTerminationFlowService::STATUS_IN_PROGRESS,
        PartnerTerminationFlowService::STATUS_FUTURE_BOOKINGS,
        PartnerTerminationFlowService::STATUS_WAITING_SETTLEMENT,
        PartnerTerminationFlowService::STATUS_WAITING_FINAL_SIGNATURE,
        PartnerTerminationFlowService::STATUS_TERMINATING,
    ];

    public function __construct(
        private readonly OwnerWalletService $wallets,
        private readonly AdminAuditService $audit,
        private readonly PartnerTerminationFlowService $terminations,
        private readonly PlatformFeeWalletService $platformFeeWallets,
    ) {}

    public function wallets(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;
        $bookingReceivables = $this->bookingReceivables($ownerId);
        $wallets = OwnerWallet::query()
            ->with('venueCluster:id,name,slug,address')
            ->where('owner_id', $ownerId)
            ->orderByDesc('available_balance')
            ->get();
        $wallets->each(function (OwnerWallet $wallet) use ($bookingReceivables): void {
            $wallet->setAttribute('platform_fee_held', $this->platformFeeWallets->activeHoldAmount($wallet));
            $wallet->setAttribute('withdrawable_balance', $this->platformFeeWallets->withdrawableAmount($wallet));
            $walletReceivables = $wallet->venue_cluster_id
                ? $bookingReceivables->where('venue_cluster_id', (int) $wallet->venue_cluster_id)
                : $bookingReceivables;
            $wallet->setAttribute('uncollected_booking_count', $walletReceivables->count());
            $wallet->setAttribute('uncollected_booking_amount', round($walletReceivables->sum('outstanding_amount'), 2));
        });

        $bankAccounts = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->get();
        $managedBankAccounts = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->orderByDesc('is_default')
            ->orderByDesc('status')
            ->orderBy('bank_name')
            ->get();

        $summary = [
            'available_balance' => (float) $wallets->sum(fn ($wallet) => $this->platformFeeWallets->withdrawableAmount($wallet)),
            'recorded_available_balance' => (float) $wallets->sum(fn ($wallet) => (float) $wallet->available_balance),
            'platform_fee_held' => (float) $wallets->sum(fn ($wallet) => $this->platformFeeWallets->activeHoldAmount($wallet)),
            'pending_withdrawal_balance' => (float) $wallets->sum(fn ($wallet) => (float) $wallet->pending_withdrawal_balance),
            'total_earned' => (float) $wallets->sum(fn ($wallet) => (float) $wallet->total_earned),
            'total_withdrawn' => (float) $wallets->sum(fn ($wallet) => (float) $wallet->total_withdrawn),
            'wallet_count' => $wallets->count(),
            'uncollected_booking_count' => $bookingReceivables->count(),
            'uncollected_booking_amount' => round($bookingReceivables->sum('outstanding_amount'), 2),
        ];
        $summary['total_balance'] = $summary['available_balance'] + $summary['pending_withdrawal_balance'];

        $businessTimezone = (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
        $storageTimezone = (string) config('app.timezone', 'UTC');
        $periodStart = Carbon::now($businessTimezone)->startOfMonth()->subMonths(5);
        $periodEnd = Carbon::now($businessTimezone)->endOfMonth();
        $storagePeriodStart = $periodStart->copy()->setTimezone($storageTimezone);
        $storagePeriodEnd = $periodEnd->copy()->setTimezone($storageTimezone);
        $cashflow = collect();

        for ($month = $periodStart->copy(); $month <= $periodEnd; $month->addMonth()) {
            $cashflow->push([
                'period' => $month->format('Y-m'),
                'label' => $month->format('m/Y'),
                'income' => 0,
                'outgoing' => 0,
                'held' => 0,
                'released' => 0,
                'net' => 0,
                'count' => 0,
            ]);
        }

        OwnerWalletLedger::query()
            ->where('owner_id', $ownerId)
            ->whereBetween('created_at', [$storagePeriodStart, $storagePeriodEnd])
            ->get(['type', 'amount', 'created_at'])
            ->each(function (OwnerWalletLedger $ledger) use ($cashflow, $businessTimezone): void {
                $period = Carbon::parse($ledger->created_at)->setTimezone($businessTimezone)->format('Y-m');
                $index = $cashflow->search(fn (array $item) => $item['period'] === $period);
                if ($index === false) {
                    return;
                }

                $amount = (float) $ledger->amount;
                $row = $cashflow->get($index);
                $row['count']++;

                if ($ledger->type === 'credit') {
                    $row['income'] += $amount;
                    $row['net'] += $amount;
                } elseif ($ledger->type === 'debit') {
                    $row['outgoing'] += $amount;
                    $row['net'] -= $amount;
                } elseif ($ledger->type === 'hold') {
                    $row['held'] += $amount;
                } elseif ($ledger->type === 'release') {
                    $row['released'] += $amount;
                    $row['net'] += $amount;
                }

                $cashflow->put($index, $row);
            });

        return response()->json([
            'data' => $wallets,
            // Keep the alias for older owner screens while the canonical payload is data.
            'wallets' => $wallets,
            'bank_accounts' => $bankAccounts,
            'managed_bank_accounts' => $managedBankAccounts,
            'summary' => $summary,
            'booking_receivables' => $bookingReceivables->values(),
            'cashflow' => $cashflow->values(),
        ]);
    }

    /**
     * Khoản phải thu chỉ gồm booking đã được xác nhận hoặc khách đã check-in.
     * Pending approval/pending payment chưa phải doanh thu cần thu của chủ sân.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function bookingReceivables(int|string $ownerId)
    {
        return Booking::query()
            ->with([
                'venueCluster:id,name',
                'venueCourt:id,name',
                'customer:id,username,full_name',
                'payments:id,booking_id,amount,status',
            ])
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereHas('venueCluster', fn ($query) => $query->where('owner_id', $ownerId))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Booking $booking): ?array {
                $paidAmount = (float) $booking->payments->where('status', 'paid')->sum('amount');
                $outstandingAmount = round(max((float) $booking->total_price - $paidAmount, 0), 2);
                if ($outstandingAmount <= 0.009) {
                    return null;
                }

                return [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'venue_cluster_id' => $booking->venue_cluster_id,
                    'venue_cluster_name' => $booking->venueCluster?->name,
                    'venue_court_name' => $booking->venueCourt?->name,
                    'booking_date' => $booking->booking_date?->toDateString(),
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'status' => $booking->status,
                    'payment_option' => $booking->payment_option,
                    'payment_option_label' => match ($booking->payment_option) {
                        'deposit' => 'Đặt cọc',
                        'no_prepay' => 'Trả sau',
                        default => 'Thanh toán chưa đủ',
                    },
                    'total_price' => (float) $booking->total_price,
                    'paid_amount' => round($paidAmount, 2),
                    'outstanding_amount' => $outstandingAmount,
                    'customer_name' => $booking->customer?->full_name
                        ?: $booking->customer?->username
                        ?: $booking->walk_in_name
                        ?: 'Khách đặt sân',
                ];
            })
            ->filter()
            ->values();
    }

    public function bankAccounts(Request $request): JsonResponse
    {
        $accounts = OwnerBankAccount::query()
            ->where('owner_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('status')
            ->orderBy('bank_name')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    public function storeBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_code' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $ownerId = $request->user()->id;
        $data = $this->normalizeBankAccountData($data);

        $duplicate = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->where('bank_code', $data['bank_code'])
            ->where('account_number', $data['account_number'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'account_number' => 'Tài khoản ngân hàng này đã được thêm trước đó.',
            ]);
        }

        $hasApplicationBankAccount = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('partner_application_id')
            ->exists();

        if ($hasApplicationBankAccount && (bool) ($data['is_default'] ?? false)) {
            throw ValidationException::withMessages([
                'is_default' => 'Tài khoản nhận tiền từ hồ sơ đăng ký đối tác là tài khoản mặc định cố định.',
            ]);
        }

        $account = DB::transaction(function () use ($ownerId, $data, $hasApplicationBankAccount): OwnerBankAccount {
            $hasAccounts = OwnerBankAccount::query()
                ->where('owner_id', $ownerId)
                ->exists();
            $isDefault = ! $hasApplicationBankAccount
                && ((bool) ($data['is_default'] ?? false) || ! $hasAccounts);

            if ($isDefault) {
                OwnerBankAccount::query()
                    ->where('owner_id', $ownerId)
                    ->update(['is_default' => false]);
            }

            $account = OwnerBankAccount::query()->create([
                'owner_id' => $ownerId,
                'bank_name' => $data['bank_name'],
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'branch_name' => $data['branch_name'] ?? null,
                'status' => 'active',
                'is_default' => $isDefault,
            ]);

            $this->ensureDefaultBankAccount($ownerId);

            return $account->fresh();
        });

        return response()->json([
            'message' => 'Đã thêm tài khoản nhận tiền.',
            'data' => $account,
        ], 201);
    }

    public function updateBankAccount(Request $request, string $id): JsonResponse
    {
        $account = OwnerBankAccount::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);

        if ($account->partner_application_id !== null) {
            throw ValidationException::withMessages([
                'account' => 'Tài khoản nhận tiền từ hồ sơ đăng ký đối tác là tài khoản mặc định cố định và không thể thay đổi hoặc xóa.',
            ]);
        }

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_code' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data = $this->normalizeBankAccountData($data);
        $duplicate = OwnerBankAccount::query()
            ->where('owner_id', $request->user()->id)
            ->where('bank_code', $data['bank_code'])
            ->where('account_number', $data['account_number'])
            ->whereKeyNot($account->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'account_number' => 'Tài khoản ngân hàng này đã được thêm trước đó.',
            ]);
        }

        $updated = DB::transaction(function () use ($request, $account, $data): OwnerBankAccount {
            $isDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : (bool) $account->is_default;

            if ($isDefault) {
                OwnerBankAccount::query()
                    ->where('owner_id', $request->user()->id)
                    ->whereKeyNot($account->id)
                    ->update(['is_default' => false]);
            }

            $account->update([
                'bank_name' => $data['bank_name'],
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
                'account_holder_name' => $data['account_holder_name'],
                'branch_name' => $data['branch_name'] ?? null,
                'status' => 'active',
                'is_default' => $isDefault,
                'rejected_reason' => null,
            ]);

            $this->ensureDefaultBankAccount($request->user()->id);

            return $account->fresh();
        });

        return response()->json([
            'message' => 'Đã cập nhật tài khoản nhận tiền.',
            'data' => $updated,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function normalizeBankAccountData(array $data): array
    {
        $data['bank_name'] = trim((string) $data['bank_name']);
        $data['bank_code'] = Str::upper(trim((string) $data['bank_code']));
        $data['account_number'] = trim((string) $data['account_number']);
        $data['account_holder_name'] = Str::upper(trim((string) $data['account_holder_name']));
        $data['branch_name'] = trim((string) ($data['branch_name'] ?? '')) ?: null;

        return $data;
    }

    private function ensureDefaultBankAccount(int|string $ownerId): void
    {
        $applicationAccount = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('partner_application_id')
            ->orderByDesc('id')
            ->first();

        if ($applicationAccount) {
            OwnerBankAccount::query()
                ->where('owner_id', $ownerId)
                ->whereKeyNot($applicationAccount->id)
                ->update(['is_default' => false]);
            $applicationAccount->update(['is_default' => true]);

            return;
        }

        $hasDefault = OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->where('is_default', true)
            ->exists();

        if ($hasDefault) {
            return;
        }

        OwnerBankAccount::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first()?->update(['is_default' => true]);
    }

    public function ledgers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wallet_id' => ['nullable', 'integer'],
            'venue_cluster_id' => ['nullable', 'integer'],
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
            'wallet_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'completed', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $withdrawals = OwnerWithdrawalRequest::query()
            ->with([
                'wallet.venueCluster:id,name',
                'bankAccount:id,bank_name,bank_code,account_number,account_holder_name,branch_name,status',
                'reviewedBy:id,username,full_name',
                'completedBy:id,username,full_name',
                'receipt',
            ])
            ->where('owner_id', $request->user()->id)
            ->when($data['wallet_id'] ?? null, fn ($query, string $id) => $query->where('owner_wallet_id', $id))
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('requested_at')
            ->paginate(15);

        $withdrawals->getCollection()->transform(function (OwnerWithdrawalRequest $withdrawal): array {
            $this->expireStalePayoutQr($withdrawal);

            return $this->withdrawalPayload($withdrawal);
        });

        return response()->json($withdrawals);
    }

    public function storeWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_wallet_id' => ['required', 'integer'],
            'owner_bank_account_id' => ['required', 'integer'],
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
            $withdrawableBalance = $this->platformFeeWallets->withdrawableAmount($wallet);
            if ($amount > $withdrawableBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền rút vượt quá số dư khả dụng sau khi trừ khoản phí nền tảng đang tạm giữ.',
                ]);
            }

            $activeTermination = PartnerTerminationRequest::query()
                ->where('owner_id', $request->user()->id)
                ->where('venue_cluster_id', $wallet->venue_cluster_id)
                ->whereIn('status', self::TERMINATION_WITHDRAWAL_LIMIT_STATUSES)
                ->latest()
                ->first();

            if ($activeTermination) {
                $activeTermination = $this->terminations->refreshAmounts($activeTermination);
                $allowed = min($withdrawableBalance, (float) $activeTermination->withdrawable_amount);
                if ($amount > $allowed + 0.01) {
                    throw ValidationException::withMessages([
                        'amount' => 'So tien rut vuot qua phan duoc phep rut trong ho so cham dut hop dong.',
                    ]);
                }
            }

            $withdrawal = OwnerWithdrawalRequest::query()->create([
                'request_code' => $this->nextRequestCode(),
                'source' => $activeTermination ? 'partner_termination_settlement' : 'manual',
                'partner_termination_request_id' => $activeTermination?->id,
                'owner_id' => $request->user()->id,
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => 'pending',
                'owner_note' => trim((string) ($data['owner_note'] ?? '')) ?: null,
                'metadata' => [
                    'balance_before_request' => (float) $wallet->available_balance,
                    'source_balance' => 'online_revenue',
                    'partner_termination_request_id' => $activeTermination?->id,
                    'withdrawable_amount_at_request' => $activeTermination ? (float) $activeTermination->withdrawable_amount : null,
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

            $this->expireStalePayoutQr($withdrawal);

            if (! in_array($withdrawal->status, ['pending', 'approved'], true)) {
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

    private function expireStalePayoutQr(OwnerWithdrawalRequest $withdrawal): void
    {
        if (
            ! $withdrawal->payout_transfer_code
            || ! $withdrawal->payout_qr_created_at
            || $withdrawal->payout_qr_created_at->gt(now()->subHours(24))
            || ! in_array($withdrawal->status, ['pending', 'approved'], true)
        ) {
            return;
        }

        $metadata = $withdrawal->metadata ?? [];
        if (! empty($metadata['mb_bulk_exported_at'])) {
            return;
        }

        $withdrawal->forceFill([
            'payout_transfer_code' => null,
            'payout_qr_created_at' => null,
            'metadata' => array_merge(is_array($metadata) ? $metadata : [], [
                'expired_payout_transfer_code' => $withdrawal->payout_transfer_code,
                'payout_expired_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    private function withdrawalPayload(OwnerWithdrawalRequest $withdrawal): array
    {
        return array_merge($withdrawal->toArray(), [
            'receipt' => $this->receiptPayload($withdrawal->receipt),
        ]);
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
            'view_url' => URL::temporarySignedRoute(
                'invoices.show',
                now()->addDays(30),
                ['receipt' => $receipt->id],
            ),
        ];
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
