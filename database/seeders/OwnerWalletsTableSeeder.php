<?php

namespace Database\Seeders;

use App\Models\OwnerWallet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OwnerWalletsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('owner_wallets') || ! Schema::hasTable('users')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();

        if (! $owner) {
            return;
        }

        OwnerWallet::query()->updateOrCreate(
            ['owner_id' => $owner->id],
            [
                'available_balance' => 2500000,
                'pending_withdrawal_balance' => 450000,
                'total_earned' => 5000000,
                'total_withdrawn' => 700000,
            ]
        );
    }
}
