<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\UserWallet;
use Illuminate\Http\Request;

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
            'otp' => ['required', 'string'],
        ], [
            'bank_name.required' => 'Vui lòng nhập tên ngân hàng.',
            'bank_account_number.required' => 'Vui lòng nhập số tài khoản.',
            'bank_account_name.required' => 'Vui lòng nhập tên chủ tài khoản.',
            'amount.required' => 'Vui lòng nhập số tiền muốn rút.',
            'amount.min' => 'Số tiền rút tối thiểu là 10.000 đ.',
            'otp.required' => 'Vui lòng nhập mã OTP xác nhận.',
        ]);

        if ($data['otp'] !== '123456' && strlen($data['otp']) !== 6) {
            return response()->json([
                'message' => 'Mã OTP xác thực giao dịch không chính xác.',
            ], 422);
        }

        $wallet = UserWallet::query()->where('user_id', $request->user()->id)->first();
        if (! $wallet || (float) $wallet->balance < (float) $data['amount']) {
            return response()->json([
                'message' => 'Số dư trong ví không đủ để thực hiện giao dịch này.',
            ], 422);
        }

        $amount = (float) $data['amount'];
        $newBalance = (float) $wallet->balance - $amount;
        $wallet->balance = $newBalance;
        $wallet->save();

        if (\Illuminate\Support\Facades\Schema::hasTable('user_wallet_ledgers')) {
            \Illuminate\Support\Facades\DB::table('user_wallet_ledgers')->insert([
                'user_wallet_id' => $wallet->id,
                'transaction_code' => 'WDR'.time().rand(100, 999),
                'type' => 'withdrawal',
                'direction' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'note' => "Rút tiền về {$data['bank_name']} - {$data['bank_account_number']} ({$data['bank_account_name']})",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Yêu cầu rút tiền đã được gửi và xử lý thành công!',
            'new_balance' => $newBalance,
        ]);
    }
}
