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
}
