<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            $table->decimal('bank_balance', 18, 2)
                ->default(0)
                ->after('current_balance')
                ->comment('So du ATM doc tu SePay, chi dung de doi soat.');
            $table->timestamp('bank_synced_at')
                ->nullable()
                ->after('last_synced_at')
                ->comment('Thoi diem dong bo so du ATM gan nhat.');
        });

        DB::table('system_wallet_balances')->update([
            'bank_balance' => DB::raw('current_balance'),
            'current_balance' => 0,
            'bank_synced_at' => DB::raw('last_synced_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('system_wallet_balances')->update([
            'current_balance' => DB::raw('bank_balance'),
            'last_synced_at' => DB::raw('bank_synced_at'),
        ]);

        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            $table->dropColumn(['bank_balance', 'bank_synced_at']);
        });
    }
};
