<?php

namespace Database\Seeders;

use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
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

        if (! $owner || ! $wallet) {
            return;
        }

        $rows = [
            [
                'credit',
                120000,
                2380000,
                2500000,
                'PMADMPAID1',
                'Cộng tiền booking online đã thanh toán.',
                $paidPayment?->booking_id,
                $paidPayment?->id,
            ],
            [
                'hold',
                300000,
                2500000,
                2200000,
                'WRADMPEND1',
                'Giữ tiền cho yêu cầu rút tiền đang chờ xử lý.',
                null,
                null,
            ],
            [
                'debit',
                700000,
                3200000,
                2500000,
                'WRADMCOMP1',
                'Trừ tiền sau khi hoàn tất rút tiền cho owner.',
                null,
                null,
            ],
        ];

        foreach ($rows as [$type, $amount, $before, $after, $referenceCode, $description, $bookingId, $paymentId]) {
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
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'description' => $description,
                    'metadata' => ['source' => 'seed'],
                ]
            );
        }
    }
}
