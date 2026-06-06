<?php

namespace Database\Seeders;

use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OwnerWalletLedgersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('owner_wallet_ledgers') || ! Schema::hasTable('owner_wallets')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $wallet = $owner ? OwnerWallet::query()->where('owner_id', $owner->id)->first() : null;
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $paidPayment = Payment::query()->where('payment_code', 'PMADMPAID1')->first();
        $withdrawals = OwnerWithdrawalRequest::query()
            ->whereIn('request_code', ['WRADMAPPR1', 'WRADMCOMP1'])
            ->get()
            ->keyBy('request_code');

        if (! $owner || ! $wallet) {
            return;
        }

        OwnerWalletLedger::query()
            ->where('owner_wallet_id', $wallet->id)
            ->where('type', 'hold')
            ->where('reference_code', 'WRADMPEND1')
            ->delete();

        $rows = [
            [
                'credit',
                'credit',
                120000,
                2380000,
                2500000,
                'PMADMPAID1',
                'payment',
                $paidPayment?->id,
                'OWC-SEED-PMADMPAID1',
                'Cộng tiền booking online đã thanh toán.',
                $paidPayment?->booking_id,
                $paidPayment?->id,
            ],
            [
                'hold',
                'debit',
                450000,
                2950000,
                2500000,
                'WRADMAPPR1',
                'withdrawal',
                $withdrawals->get('WRADMAPPR1')?->id,
                'OWH-SEED-WRADMAPPR1',
                'Giữ tiền cho yêu cầu rút tiền đã duyệt, chờ chuyển khoản.',
                null,
                null,
            ],
            [
                'debit',
                'debit',
                700000,
                3200000,
                2500000,
                'WRADMCOMP1',
                'withdrawal',
                $withdrawals->get('WRADMCOMP1')?->id,
                'OWX-SEED-WRADMCOMP1',
                'Trừ tiền sau khi hoàn tất rút tiền cho owner.',
                null,
                null,
            ],
        ];

        foreach ($rows as [$type, $direction, $amount, $before, $after, $referenceCode, $referenceType, $referenceId, $transactionCode, $description, $bookingId, $paymentId]) {
            OwnerWalletLedger::query()->updateOrCreate(
                [
                    'owner_wallet_id' => $wallet->id,
                    'type' => $type,
                    'reference_code' => $referenceCode,
                ],
                [
                    'owner_id' => $owner->id,
                    'venue_cluster_id' => $cluster?->id,
                    'booking_id' => $bookingId,
                    'payment_id' => $paymentId,
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'status' => 'completed',
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'transaction_code' => $transactionCode,
                    'description' => $description,
                    'note' => $description,
                    'metadata' => ['source' => 'seed', 'request_code' => $referenceCode],
                ]
            );
        }
    }
}
