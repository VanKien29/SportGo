<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\UserWallet;
use App\Models\UserPayoutAccount;
use App\Models\UserWithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $wallet = UserWallet::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'locked_balance' => 0, 'status' => 'active'],
        );

        $wallet->load(['ledgers' => fn ($query) => $query->limit(30)]);

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
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:10000'],
        ], [
            'bank_name.required' => 'Vui lòng nhập tên ngân hàng.',
            'bank_account_number.required' => 'Vui lòng nhập số tài khoản.',
            'bank_account_name.required' => 'Vui lòng nhập tên chủ tài khoản.',
            'amount.required' => 'Vui lòng nhập số tiền muốn rút.',
            'amount.min' => 'Số tiền rút tối thiểu là 10.000 đ.',
        ]);

        try {
            $withdrawal = DB::transaction(function () use ($request, $data): UserWithdrawalRequest {
            $wallet = UserWallet::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new \RuntimeException('Không tìm thấy ví người dùng.');
            }

            if ($wallet->status !== 'active') {
                throw new \RuntimeException('Ví người dùng đang bị khóa hoặc tạm ngưng.');
            }

            $amount = round((float) $data['amount'], 2);
            if ((float) $wallet->balance < $amount) {
                throw new \RuntimeException('Số dư trong ví không đủ để thực hiện giao dịch này.');
            }

            $payoutAccount = UserPayoutAccount::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'bank_account_number' => trim($data['bank_account_number']),
                ],
                [
                    'bank_name' => trim($data['bank_name']),
                    'bank_account_holder' => trim($data['bank_account_name']),
                    'status' => 'active',
                    'is_default' => true,
                ],
            );

            UserPayoutAccount::query()
                ->where('user_id', $request->user()->id)
                ->whereKeyNot($payoutAccount->id)
                ->update(['is_default' => false]);

            $wallet->forceFill([
                'locked_balance' => round((float) $wallet->locked_balance + $amount, 2),
            ])->save();

            return UserWithdrawalRequest::query()->create([
                'user_wallet_id' => $wallet->id,
                'user_id' => $request->user()->id,
                'payout_account_id' => $payoutAccount->id,
                'amount' => $amount,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
            });
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Yêu cầu rút tiền đã được gửi, vui lòng chờ SportGo xử lý.',
            'new_balance' => (float) $withdrawal->wallet()->value('balance'),
            'locked_balance' => (float) $withdrawal->wallet()->value('locked_balance'),
            'withdrawal_id' => $withdrawal->id,
        ]);
    }
}
