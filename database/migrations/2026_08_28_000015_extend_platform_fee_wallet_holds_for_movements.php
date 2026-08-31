<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_wallet_holds', function (Blueprint $table): void {
            $table->decimal('original_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('remaining_amount', 14, 2)->default(0)->after('original_amount');
            $table->decimal('consumed_amount', 14, 2)->default(0)->after('remaining_amount');
            $table->timestamp('consumed_at')->nullable()->after('released_at');
            $table->unsignedBigInteger('consumed_by')->nullable()->after('released_by');
            $table->string('movement_reference', 100)->nullable()->after('consumed_by');

            $table->foreign('consumed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('platform_fee_wallet_holds')->update([
            'original_amount' => DB::raw('amount'),
            'remaining_amount' => DB::raw("CASE WHEN status = 'active' THEN amount ELSE 0 END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_fee_wallet_holds', function (Blueprint $table): void {
            $table->dropForeign(['consumed_by']);
            $table->dropColumn([
                'original_amount',
                'remaining_amount',
                'consumed_amount',
                'consumed_at',
                'consumed_by',
                'movement_reference',
            ]);
        });
    }
};
