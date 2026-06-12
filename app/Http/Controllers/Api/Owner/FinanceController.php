<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function wallets(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $wallets = OwnerWallet::query()
            ->with(['venueCluster' => function($q) {
                $q->select('id', 'name', 'slug', 'address');
            }])
            ->where('owner_id', $userId)
            ->get();

        return response()->json(['data' => $wallets]);
    }

    public function ledgers(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $walletId = $request->query('wallet_id');
        $venueClusterId = $request->query('venue_cluster_id');
        
        $query = OwnerWalletLedger::query()
            ->with(['booking' => function($q) {
                $q->select('id', 'booking_code', 'total_amount');
            }, 'payment' => function($q) {
                $q->select('id', 'payment_code', 'method', 'gateway_txn_id');
            }])
            ->where('owner_id', $userId);

        if ($walletId) {
            $query->where('owner_wallet_id', $walletId);
        }

        if ($venueClusterId) {
            $query->where('venue_cluster_id', $venueClusterId);
        }

        $ledgers = $query->latest()->paginate(20);

        return response()->json($ledgers);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $walletId = $request->query('wallet_id');
        
        $query = OwnerWithdrawalRequest::query()
            ->with(['ownerWallet.venueCluster:id,name', 'approvedBy:id,full_name', 'completedBy:id,full_name'])
            ->where('owner_id', $userId);

        if ($walletId) {
            $query->where('owner_wallet_id', $walletId);
        }

        $withdrawals = $query->latest()->paginate(15);

        return response()->json($withdrawals);
    }

    public function storeWithdrawal(Request $request, OwnerWalletService $walletService): JsonResponse
    {
        $userId = $request->user()->id;
        
        $validated = $request->validate([
            'owner_wallet_id' => 'required|uuid|exists:owner_wallets,id',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $wallet = OwnerWallet::query()
            ->where('id', $validated['owner_wallet_id'])
            ->where('owner_id', $userId)
            ->firstOrFail();

        if ((float) $validated['amount'] <= 0 || (float) $validated['amount'] > (float) $wallet->available_balance) {
            return response()->json([
                'message' => 'Sá»‘ tiá» n rÃºt khÃ´ng há»£p lá»‡ hoáº·c vÆ°á»£t quÃ¡ sá»‘ dÆ° kháº£ dá»¥ng.',
            ], 422);
        }

        $withdrawal = OwnerWithdrawalRequest::create([
            'owner_id' => $userId,
            'owner_wallet_id' => $wallet->id,
            'request_code' => 'WR-' . strtoupper(Str::random(10)),
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
        ]);

        try {
            $walletService->holdWithdrawal($withdrawal);
        } catch (\Exception $e) {
            // Rollback the withdrawal request if hold fails
            $withdrawal->delete();
            return response()->json([
                'message' => $e->getMessage() ?: 'CÃ³ lá»—i xáº£y ra khi yÃªu cáº§u rÃºt tiá» n.',
            ], 422);
        }

        return response()->json([
            'message' => 'YÃªu cáº§u rÃºt tiá» n Ä‘Ã£ Ä‘Æ°á»£c gá»i thÃ nh cÃ´ng.',
            'data' => $withdrawal
        ], 201);
    }
}
