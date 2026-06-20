<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function getWallet(Request $request): JsonResponse
    {
        $wallet = OwnerWallet::firstOrCreate(
            ['owner_id' => $request->user()->id, 'venue_cluster_id' => null],
            [
                'available_balance' => 0.0,
                'pending_withdrawal_balance' => 0.0,
                'total_earned' => 0.0,
                'total_withdrawn' => 0.0,
            ]
        );

        $bankAccounts = OwnerBankAccount::where('owner_id', $request->user()->id)
            ->where('status', 'active')
            ->get();

        $legacyPendingAmount = (float) OwnerWithdrawalRequest::query()
            ->where('owner_id', $request->user()->id)
            ->where('owner_wallet_id', $wallet->id)
            ->whereIn('status', ['pending', 'reviewing', 'approved'])
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('owner_wallet_ledgers')
                    ->whereColumn('owner_wallet_ledgers.reference_id', 'owner_withdrawal_requests.id')
                    ->where('owner_wallet_ledgers.reference_type', 'withdrawal')
                    ->where('owner_wallet_ledgers.type', 'hold');
            })
            ->sum('amount');

        if ($legacyPendingAmount > 0) {
            $wallet->available_balance = max(0, (float) $wallet->available_balance - $legacyPendingAmount);
            $wallet->pending_withdrawal_balance = (float) $wallet->pending_withdrawal_balance + $legacyPendingAmount;
        }

        return response()->json([
            'wallet' => $wallet,
            'bank_accounts' => $bankAccounts,
        ]);
    }

    public function withdraw(Request $request, OwnerWalletService $walletService): JsonResponse
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

        if (! $bankAccount) {
            return response()->json(['message' => 'Tài khoản ngân hàng không hợp lệ hoặc chưa được kích hoạt.'], 422);
        }

        return DB::transaction(function () use ($userId, $amount, $bankAccountId, $request, $walletService) {
            $wallet = OwnerWallet::where('owner_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                return response()->json(['message' => 'Không tìm thấy thông tin ví của bạn.'], 422);
            }

            if ($amount > (float) $wallet->available_balance) {
                return response()->json(['message' => 'Số dư khả dụng không đủ (sau khi trừ các yêu cầu rút tiền đang chờ chuyển khoản khác).'], 422);
            }

            // Generate unique request code
            do {
                $code = 'WR'.strtoupper(Str::random(10));
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

            $walletService->holdWithdrawal($withdrawal, [
                'source' => 'legacy_owner_wallet',
                'owner_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Tạo yêu cầu rút tiền thành công. Vui lòng chờ SportGo chuyển khoản.',
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
