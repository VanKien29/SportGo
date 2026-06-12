<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\OwnerBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function getWallet(Request $request): JsonResponse
    {
        $wallet = OwnerWallet::firstOrCreate(
            ['owner_id' => $request->user()->id],
            [
                'available_balance' => 0.0,
                'pending_withdrawal_balance' => 0.0,
                'total_earned' => 0.0,
                'total_withdrawn' => 0.0,
            ]
        );

        $pendingAmount = (float) OwnerWithdrawalRequest::where('owner_id', $request->user()->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->sum('amount');

        $wallet->available_balance = (float) $wallet->available_balance - $pendingAmount;
        $wallet->pending_withdrawal_balance = (float) $wallet->pending_withdrawal_balance + $pendingAmount;

        $bankAccounts = OwnerBankAccount::where('owner_id', $request->user()->id)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'wallet' => $wallet,
            'bank_accounts' => $bankAccounts,
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:50000'],
            'owner_bank_account_id' => ['required', 'string'],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = $request->user()->id;
        $amount = (float) $request->input('amount');
        $bankAccountId = $request->input('owner_bank_account_id');

        // Verify bank account belongs to owner and is active
        $bankAccount = OwnerBankAccount::where('owner_id', $userId)
            ->where('status', 'active')
            ->whereKey($bankAccountId)
            ->first();

        if (!$bankAccount) {
            return response()->json(['message' => 'Tài khoản ngân hàng không hợp lệ hoặc chưa được kích hoạt.'], 422);
        }

        return DB::transaction(function () use ($userId, $amount, $bankAccountId, $request) {
            $wallet = OwnerWallet::where('owner_id', $userId)->lockForUpdate()->first();

            if (!$wallet) {
                return response()->json(['message' => 'Không tìm thấy thông tin ví của bạn.'], 422);
            }

            // Calculate active pending withdrawals
            $pendingAmount = OwnerWithdrawalRequest::where('owner_id', $userId)
                ->whereIn('status', ['pending', 'reviewing'])
                ->sum('amount');

            $effectiveBalance = (float) $wallet->available_balance - (float) $pendingAmount;

            if ($amount > $effectiveBalance) {
                return response()->json(['message' => 'Số dư khả dụng không đủ (sau khi trừ các yêu cầu rút tiền đang chờ duyệt khác).'], 422);
            }

            // Generate unique request code
            do {
                $code = 'WR' . strtoupper(Str::random(10));
            } while (OwnerWithdrawalRequest::where('request_code', $code)->exists());

            $withdrawal = OwnerWithdrawalRequest::create([
                'request_code' => $code,
                'source' => 'manual',
                'owner_id' => $userId,
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'status' => 'pending',
                'owner_note' => $request->input('owner_note'),
                'requested_at' => now(),
            ]);

            return response()->json([
                'message' => 'Tạo yêu cầu rút tiền thành công. Vui lòng chờ admin phê duyệt.',
                'data' => $withdrawal,
            ]);
        });
    }

    public function getWithdrawals(Request $request): JsonResponse
    {
        $withdrawals = OwnerWithdrawalRequest::where('owner_id', $request->user()->id)
            ->with('bankAccount')
            ->latest('requested_at')
            ->paginate(15);

        return response()->json($withdrawals);
    }
}
