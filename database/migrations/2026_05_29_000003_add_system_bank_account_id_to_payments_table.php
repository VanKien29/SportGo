<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->char('system_bank_account_id', 36)->nullable()->after('booking_id')->comment('Tài khoản hệ thống nhận tiền cho payment này.');
            $table->index('system_bank_account_id', 'payments_system_bank_account_id_index');
            $table->foreign('system_bank_account_id')->references('id')->on('system_bank_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['system_bank_account_id']);
            $table->dropIndex('payments_system_bank_account_id_index');
            $table->dropColumn('system_bank_account_id');
        });
    }
};
