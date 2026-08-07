<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\UserPayoutAccount;
use App\Models\UserWithdrawalRequest;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $request->validate([
            'ledger_type' => ['nullable', 'in:deposit,payment,refund,withdrawal,adjustment'],
            'ledger_status' => ['nullable', 'in:pending,completed,failed,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $wallet = UserWallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'locked_balance' => 0, 'status' => 'active'],
        );

        $wallet->load(['ledgers' => function ($query) use ($request): void {
            $query->when($request->filled('ledger_type'), fn ($builder) => $builder->where('type', $request->query('ledger_type')))
                ->when($request->filled('ledger_status'), fn ($builder) => $builder->where('status', $request->query('ledger_status')))
                ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('created_at', '>=', $request->query('date_from')))
                ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('created_at', '<=', $request->query('date_to')))
                ->latest('created_at')->limit(50);
        }]);

        return response()->json([
            'wallet' => [
                'id' => $wallet->id,
                'balance' => (float) $wallet->balance,
                'locked_balance' => (float) $wallet->locked_balance,
                'status' => $wallet->status,
            ],
            'ledgers' => $wallet->ledgers->map(fn ($ledger) => [
                'id' => $ledger->id,
                'transaction_code' => $ledger->transaction_code,
                'type' => $ledger->type,
                'direction' => $ledger->direction,
                'amount' => (float) $ledger->amount,
                'balance_after' => (float) $ledger->balance_after,
                'reference_type' => $ledger->reference_type,
                'reference_id' => $ledger->reference_id,
                'status' => $ledger->status,
                'note' => $ledger->note,
                'created_at' => $ledger->created_at,
            ])->values(),
            'payout_accounts' => $this->accountPayloads($request->user()->id),
            'withdrawals' => $this->withdrawalQuery($request->user()->id)->limit(30)->get()->map(fn ($withdrawal) => $this->withdrawalPayload($withdrawal))->values(),
        ]);
    }

    public function payoutAccounts(Request $request)
    {
        return response()->json([
            'data' => $this->accountPayloads($request->user()->id),
        ]);
    }

    public function storePayoutAccount(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'bank_name' => ['required', 'string', 'max:120'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'max:150'],
            'bank_branch' => ['nullable', 'string', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $account = $data['id']
            ? UserPayoutAccount::query()->where('user_id', $request->user()->id)->findOrFail($data['id'])
            : new UserPayoutAccount(['user_id' => $request->user()->id]);

        $account->fill(collect($data)->except('id')->all());
        $account->status = 'active';

        DB::transaction(function () use ($account, $request): void {
            if ($account->is_default) {
                UserPayoutAccount::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $account->id ?: 0)
                    ->update(['is_default' => false]);
            }
            $account->save();
        });

        return response()->json([
            'message' => 'Đã lưu tài khoản nhận tiền.',
            'data' => $this->accountPayload($account->fresh()),
        ], $data['id'] ? 200 : 201);
    }

    public function deletePayoutAccount(Request $request, string $id)
    {
        $account = UserPayoutAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        if (UserWithdrawalRequest::query()->where('payout_account_id', $account->id)->whereIn('status', ['pending', 'approved'])->exists()) {
            return response()->json(['message' => 'Không thể xóa tài khoản đang gắn với yêu cầu rút tiền đang xử lý.'], 422);
        }

        DB::transaction(function () use ($account, $request): void {
            $wasDefault = (bool) $account->is_default;
            $account->forceFill(['status' => 'inactive', 'is_default' => false])->save();
            if ($wasDefault) {
                $replacement = UserPayoutAccount::query()
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'active')
                    ->latest('updated_at')
                    ->first();
                if ($replacement) {
                    $replacement->forceFill(['is_default' => true])->save();
                }
            }
        });

        return response()->json(['message' => 'Đã vô hiệu hóa tài khoản nhận tiền.']);
    }

    public function requestWithdrawal(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000'],
            'payout_account_id' => ['required', 'integer'],
        ], [
            'amount.min' => 'Số tiền rút tối thiểu là 10.000đ.',
        ]);

        $account = UserPayoutAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($data['payout_account_id']);

        $withdrawal = DB::transaction(function () use ($request, $data, $account) {
            $wallet = UserWallet::query()->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $data['amount'], 2);

            if ($wallet->status !== 'active') {
                abort(422, 'Ví SportGo đang bị khóa hoặc tạm ngưng.');
            }
            if ((float) $wallet->balance < $amount) {
                abort(422, 'Số dư khả dụng không đủ cho yêu cầu rút tiền này.');
            }

            $wallet->forceFill([
                'balance' => round((float) $wallet->balance - $amount, 2),
                'locked_balance' => round((float) $wallet->locked_balance + $amount, 2),
            ])->save();

            return UserWithdrawalRequest::query()->create([
                'user_wallet_id' => $wallet->id,
                'user_id' => $request->user()->id,
                'payout_account_id' => $account->id,
                'amount' => $amount,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Đã gửi yêu cầu rút tiền. SportGo sẽ kiểm tra và thông báo khi có kết quả.',
            'data' => $this->withdrawalPayload($withdrawal->load('payoutAccount')),
        ], 201);
    }

    public function cancelWithdrawal(Request $request, string $id)
    {
        $withdrawal = DB::transaction(function () use ($request, $id) {
            $item = UserWithdrawalRequest::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->status !== 'pending') {
                abort(422, 'Chỉ có thể hủy yêu cầu rút tiền đang chờ duyệt.');
            }

            $wallet = UserWallet::query()->whereKey($item->user_wallet_id)->lockForUpdate()->firstOrFail();
            $wallet->forceFill([
                'balance' => round((float) $wallet->balance + (float) $item->amount, 2),
                'locked_balance' => max(0, round((float) $wallet->locked_balance - (float) $item->amount, 2)),
            ])->save();

            $item->forceFill(['status' => 'cancelled'])->save();
            return $item->load('payoutAccount');
        });

        return response()->json([
            'message' => 'Đã hủy yêu cầu rút tiền.',
            'data' => $this->withdrawalPayload($withdrawal),
        ]);
    }

    private function accountPayloads(int $userId)
    {
        return UserPayoutAccount::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->latest('updated_at')
            ->get()
            ->map(fn ($account) => $this->accountPayload($account))
            ->values();
    }

    private function accountPayload(UserPayoutAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'bank_account_holder' => $account->bank_account_holder,
            'bank_account_number' => $account->bank_account_number,
            'bank_account_masked' => $this->maskAccount($account->bank_account_number),
            'bank_branch' => $account->bank_branch,
            'is_default' => (bool) $account->is_default,
            'status' => $account->status,
        ];
    }

    private function withdrawalQuery(int $userId)
    {
        return UserWithdrawalRequest::query()
            ->with('payoutAccount')
            ->where('user_id', $userId)
            ->latest('requested_at');
    }

    private function withdrawalPayload(UserWithdrawalRequest $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'status' => $withdrawal->status,
            'status_label' => [
                'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối',
                'paid' => 'Đã chuyển khoản', 'cancelled' => 'Đã hủy',
            ][$withdrawal->status] ?? $withdrawal->status,
            'rejected_reason' => $withdrawal->rejected_reason,
            'requested_at' => optional($withdrawal->requested_at)->toISOString(),
            'paid_at' => optional($withdrawal->paid_at)->toISOString(),
            'payout_account' => $withdrawal->payoutAccount ? $this->accountPayload($withdrawal->payoutAccount) : null,
        ];
    }

    private function maskAccount(?string $account): string
    {
        $value = preg_replace('/\s+/', '', (string) $account);
        if (strlen($value) <= 4) return $value;
        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
