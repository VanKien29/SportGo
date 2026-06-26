<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            $table->decimal('refund_reserved_balance', 18, 2)
                ->default(0)
                ->after('current_balance')
                ->comment('Tien da cam ket hoan vao vi khach nhung chua rut khoi ATM.');
            $table->decimal('voucher_reserved_balance', 18, 2)
                ->default(0)
                ->after('refund_reserved_balance')
                ->comment('Chi phi voucher he thong da cam ket cho chu san.');
        });

        Schema::table('system_wallet_ledgers', function (Blueprint $table): void {
            $table->string('entry_kind', 40)
                ->default('actual')
                ->after('direction')
                ->comment('actual, refund_reserve, voucher_reserve, reserve_release.');
            $table->decimal('refund_reserved_before', 18, 2)->default(0)->after('balance_after');
            $table->decimal('refund_reserved_after', 18, 2)->default(0)->after('refund_reserved_before');
            $table->decimal('voucher_reserved_before', 18, 2)->default(0)->after('refund_reserved_after');
            $table->decimal('voucher_reserved_after', 18, 2)->default(0)->after('voucher_reserved_before');
            $table->json('metadata')->nullable()->after('description');
            $table->index(['entry_kind', 'transacted_at'], 'system_wallet_ledgers_kind_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('system_wallet_ledgers', function (Blueprint $table): void {
            $table->dropIndex('system_wallet_ledgers_kind_time_index');
            $table->dropColumn([
                'entry_kind',
                'refund_reserved_before',
                'refund_reserved_after',
                'voucher_reserved_before',
                'voucher_reserved_after',
                'metadata',
            ]);
        });

        Schema::table('system_wallet_balances', function (Blueprint $table): void {
            $table->dropColumn(['refund_reserved_balance', 'voucher_reserved_balance']);
        });
    }
};
