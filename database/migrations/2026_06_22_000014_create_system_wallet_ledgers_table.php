<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_wallet_ledgers', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('system_bank_account_id', 36)->comment('Tai khoan ngan hang he thong.');
            $table->string('transaction_ref', 100)->nullable()->comment('Ma giao dich tu ngan hang.');
            $table->enum('direction', ['in', 'out'])->comment('Chieu tien vao hoac ra.');
            $table->decimal('amount', 18, 2)->comment('So tien giao dich.');
            $table->decimal('balance_before', 18, 2)->comment('So du truoc giao dich.');
            $table->decimal('balance_after', 18, 2)->comment('So du sau giao dich.');
            $table->enum('transaction_type', [
                'booking_payment',
                'withdrawal_to_owner',
                'refund_to_customer',
                'platform_fee_received',
                'adjustment',
                'other',
            ])->default('other')->comment('Phan loai nghiep vu.');
            $table->string('reference_type', 100)->nullable()->comment('Loai doi tuong nghiep vu lien quan.');
            $table->string('reference_id', 100)->nullable()->comment('ID doi tuong lien quan.');
            $table->text('description')->nullable()->comment('Mo ta giao dich.');
            $table->timestamp('transacted_at')->comment('Thoi diem giao dich thuc te.');
            $table->timestamp('synced_at')->comment('Thoi diem he thong ghi nhan.');
            $table->timestamp('created_at')->nullable();

            $table->index(['system_bank_account_id', 'transacted_at'], 'system_wallet_ledgers_account_time_index');
            $table->index(['transaction_type', 'transacted_at'], 'system_wallet_ledgers_type_time_index');
            $table->index(['reference_type', 'reference_id'], 'system_wallet_ledgers_reference_index');
            $table->index(['direction', 'transacted_at'], 'system_wallet_ledgers_direction_time_index');
            $table->unique(['system_bank_account_id', 'transaction_ref'], 'system_wallet_ledgers_account_ref_unique');
            $table->foreign('system_bank_account_id')->references('id')->on('system_bank_accounts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_wallet_ledgers');
    }
};
