<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE payments MODIFY method ENUM('sepay','bank_transfer','cash','wallet','mixed','vnpay','momo','zalopay') NOT NULL DEFAULT 'sepay' COMMENT 'Phương thức thanh toán/ghi nhận tiền.'");
            }

            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'wallet_amount')) {
                    $table->decimal('wallet_amount', 12, 2)->default(0.00)->after('amount')
                        ->comment('Phần tiền thanh toán bằng ví user.');
                }

                if (! Schema::hasColumn('payments', 'gateway_amount')) {
                    $table->decimal('gateway_amount', 12, 2)->default(0.00)->after('wallet_amount')
                        ->comment('Phần tiền thanh toán qua gateway/chuyển khoản.');
                }

                if (! Schema::hasColumn('payments', 'user_wallet_id')) {
                    $table->char('user_wallet_id', 36)->nullable()->after('system_bank_account_id')
                        ->comment('Ví user dùng trong payment nếu thanh toán bằng ví hoặc mixed.');
                }

                if (! Schema::hasColumn('payments', 'user_wallet_ledger_id')) {
                    $table->char('user_wallet_ledger_id', 36)->nullable()->after('user_wallet_id')
                        ->comment('Ledger debit ví user liên quan payment này.');
                }
            });

            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'user_wallet_id')) {
                    $table->index('user_wallet_id', 'payments_user_wallet_id_index');
                    $table->foreign('user_wallet_id', 'payments_user_wallet_foreign')
                        ->references('id')->on('user_wallets')->onDelete('set null');
                }

                if (Schema::hasColumn('payments', 'user_wallet_ledger_id')) {
                    $table->index('user_wallet_ledger_id', 'payments_user_wallet_ledger_id_index');
                    $table->foreign('user_wallet_ledger_id', 'payments_user_wallet_ledger_foreign')
                        ->references('id')->on('user_wallet_ledgers')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                if (! Schema::hasColumn('refunds', 'customer_id')) {
                    $table->char('customer_id', 36)->nullable()->after('booking_id')
                        ->comment('User nhận hoàn tiền, denormalized từ booking.');
                }

                if (! Schema::hasColumn('refunds', 'refund_destination')) {
                    $table->enum('refund_destination', ['original_payment', 'user_wallet', 'bank_account'])
                        ->default('original_payment')->after('amount')
                        ->comment('Đích hoàn tiền: payment gốc, ví user hoặc tài khoản ngân hàng.');
                }

                if (! Schema::hasColumn('refunds', 'user_wallet_id')) {
                    $table->char('user_wallet_id', 36)->nullable()->after('refund_destination')
                        ->comment('Ví user nhận tiền hoàn nếu refund_destination=user_wallet.');
                }

                if (! Schema::hasColumn('refunds', 'user_wallet_ledger_id')) {
                    $table->char('user_wallet_ledger_id', 36)->nullable()->after('user_wallet_id')
                        ->comment('Ledger credit ví user khi hoàn tiền vào ví.');
                }

                if (! Schema::hasColumn('refunds', 'user_payout_account_id')) {
                    $table->char('user_payout_account_id', 36)->nullable()->after('user_wallet_ledger_id')
                        ->comment('Tài khoản ngân hàng user nhận tiền nếu hoàn về bank.');
                }

                if (! Schema::hasColumn('refunds', 'owner_wallet_ledger_id')) {
                    $table->char('owner_wallet_ledger_id', 36)->nullable()->after('user_payout_account_id')
                        ->comment('Ledger debit ví owner nếu refund làm giảm doanh thu chủ sân.');
                }

                if (! Schema::hasColumn('refunds', 'owner_confirmed_by')) {
                    $table->char('owner_confirmed_by', 36)->nullable()->after('status_reason')
                        ->comment('Owner/nhân viên sân xác nhận hoàn tiền.');
                    $table->timestamp('owner_confirmed_at')->nullable()->after('owner_confirmed_by')
                        ->comment('Thời điểm owner xác nhận hoàn tiền.');
                    $table->text('owner_confirm_note')->nullable()->after('owner_confirmed_at')
                        ->comment('Ghi chú xác nhận hoàn tiền của owner.');
                }

                if (! Schema::hasColumn('refunds', 'admin_confirmed_by')) {
                    $table->char('admin_confirmed_by', 36)->nullable()->after('processed_at')
                        ->comment('Admin xác nhận refund hoàn tất sau khi API/giao dịch thành công.');
                    $table->timestamp('admin_confirmed_at')->nullable()->after('admin_confirmed_by')
                        ->comment('Thời điểm admin xác nhận refund hoàn tất.');
                }

                if (! Schema::hasColumn('refunds', 'gateway_refund_txn_id')) {
                    $table->string('gateway_refund_txn_id', 100)->nullable()->after('admin_confirmed_at')
                        ->comment('Mã giao dịch hoàn tiền từ gateway nếu có.');
                }
            });

            Schema::table('refunds', function (Blueprint $table) {
                if (Schema::hasColumn('refunds', 'customer_id')) {
                    $table->index('customer_id', 'refunds_customer_id_index');
                    $table->foreign('customer_id', 'refunds_customer_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'refund_destination')) {
                    $table->index('refund_destination', 'refunds_refund_destination_index');
                }

                if (Schema::hasColumn('refunds', 'user_wallet_id')) {
                    $table->index('user_wallet_id', 'refunds_user_wallet_id_index');
                    $table->foreign('user_wallet_id', 'refunds_user_wallet_foreign')
                        ->references('id')->on('user_wallets')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'user_wallet_ledger_id')) {
                    $table->index('user_wallet_ledger_id', 'refunds_user_wallet_ledger_id_index');
                    $table->foreign('user_wallet_ledger_id', 'refunds_user_wallet_ledger_foreign')
                        ->references('id')->on('user_wallet_ledgers')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'user_payout_account_id')) {
                    $table->index('user_payout_account_id', 'refunds_user_payout_account_id_index');
                    $table->foreign('user_payout_account_id', 'refunds_user_payout_account_foreign')
                        ->references('id')->on('user_payout_accounts')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'owner_wallet_ledger_id')) {
                    $table->index('owner_wallet_ledger_id', 'refunds_owner_wallet_ledger_id_index');
                    $table->foreign('owner_wallet_ledger_id', 'refunds_owner_wallet_ledger_foreign')
                        ->references('id')->on('owner_wallet_ledgers')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'owner_confirmed_by')) {
                    $table->index('owner_confirmed_by', 'refunds_owner_confirmed_by_index');
                    $table->foreign('owner_confirmed_by', 'refunds_owner_confirmed_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'admin_confirmed_by')) {
                    $table->index('admin_confirmed_by', 'refunds_admin_confirmed_by_index');
                    $table->foreign('admin_confirmed_by', 'refunds_admin_confirmed_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }

                if (Schema::hasColumn('refunds', 'gateway_refund_txn_id')) {
                    $table->index('gateway_refund_txn_id', 'refunds_gateway_refund_txn_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                foreach ([
                    'refunds_customer_foreign',
                    'refunds_user_wallet_foreign',
                    'refunds_user_wallet_ledger_foreign',
                    'refunds_user_payout_account_foreign',
                    'refunds_owner_wallet_ledger_foreign',
                    'refunds_owner_confirmed_by_foreign',
                    'refunds_admin_confirmed_by_foreign',
                ] as $foreign) {
                    try {
                        $table->dropForeign($foreign);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'refunds_customer_id_index',
                    'refunds_refund_destination_index',
                    'refunds_user_wallet_id_index',
                    'refunds_user_wallet_ledger_id_index',
                    'refunds_user_payout_account_id_index',
                    'refunds_owner_wallet_ledger_id_index',
                    'refunds_owner_confirmed_by_index',
                    'refunds_admin_confirmed_by_index',
                    'refunds_gateway_refund_txn_id_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::table('refunds', function (Blueprint $table) {
                foreach ([
                    'gateway_refund_txn_id',
                    'admin_confirmed_at',
                    'admin_confirmed_by',
                    'owner_confirm_note',
                    'owner_confirmed_at',
                    'owner_confirmed_by',
                    'owner_wallet_ledger_id',
                    'user_payout_account_id',
                    'user_wallet_ledger_id',
                    'user_wallet_id',
                    'refund_destination',
                    'customer_id',
                ] as $column) {
                    if (Schema::hasColumn('refunds', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                foreach ([
                    'payments_user_wallet_foreign',
                    'payments_user_wallet_ledger_foreign',
                ] as $foreign) {
                    try {
                        $table->dropForeign($foreign);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'payments_user_wallet_id_index',
                    'payments_user_wallet_ledger_id_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::table('payments', function (Blueprint $table) {
                foreach (['user_wallet_ledger_id', 'user_wallet_id', 'gateway_amount', 'wallet_amount'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
