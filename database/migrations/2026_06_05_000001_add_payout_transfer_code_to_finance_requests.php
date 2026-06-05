<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refunds') && ! Schema::hasColumn('refunds', 'payout_transfer_code')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->string('payout_transfer_code', 40)->nullable()->after('gateway_refund_txn_id')
                    ->comment('Mã nội dung chuyển khoản admin dùng khi hoàn tiền bằng QR.');
                $table->timestamp('payout_qr_created_at')->nullable()->after('payout_transfer_code')
                    ->comment('Thời điểm tạo QR chuyển tiền hoàn tiền gần nhất.');
                $table->unique('payout_transfer_code', 'refunds_payout_transfer_code_unique');
            });
        }

        if (Schema::hasTable('owner_withdrawal_requests') && ! Schema::hasColumn('owner_withdrawal_requests', 'payout_transfer_code')) {
            Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
                $table->string('payout_transfer_code', 40)->nullable()->after('transfer_reference')
                    ->comment('Mã nội dung chuyển khoản admin dùng khi chi trả rút tiền bằng QR.');
                $table->timestamp('payout_qr_created_at')->nullable()->after('payout_transfer_code')
                    ->comment('Thời điểm tạo QR chuyển tiền rút tiền gần nhất.');
                $table->unique('payout_transfer_code', 'owner_withdrawals_payout_transfer_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('owner_withdrawal_requests') && Schema::hasColumn('owner_withdrawal_requests', 'payout_transfer_code')) {
            Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
                $table->dropUnique('owner_withdrawals_payout_transfer_code_unique');
                $table->dropColumn(['payout_transfer_code', 'payout_qr_created_at']);
            });
        }

        if (Schema::hasTable('refunds') && Schema::hasColumn('refunds', 'payout_transfer_code')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->dropUnique('refunds_payout_transfer_code_unique');
                $table->dropColumn(['payout_transfer_code', 'payout_qr_created_at']);
            });
        }
    }
};
