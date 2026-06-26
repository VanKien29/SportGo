<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_withdrawal_requests')) {
            return;
        }

        Schema::table('user_withdrawal_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_withdrawal_requests', 'payout_transfer_code')) {
                $table->string('payout_transfer_code', 30)->nullable()->unique()->after('paid_note')
                    ->comment('Nội dung chuyển khoản dùng để đối soát SePay.');
            }

            if (! Schema::hasColumn('user_withdrawal_requests', 'payout_qr_created_at')) {
                $table->timestamp('payout_qr_created_at')->nullable()->after('payout_transfer_code')
                    ->comment('Thời điểm tạo QR chi trả.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_withdrawal_requests')) {
            return;
        }

        Schema::table('user_withdrawal_requests', function (Blueprint $table): void {
            foreach (['payout_qr_created_at', 'payout_transfer_code'] as $column) {
                if (Schema::hasColumn('user_withdrawal_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
