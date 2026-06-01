<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_wallets')) {
            Schema::create('user_wallets', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36)->unique()->comment('User sở hữu ví.');
                $table->decimal('balance', 14, 2)->default(0.00)->comment('Số dư có thể sử dụng.');
                $table->decimal('locked_balance', 14, 2)->default(0.00)->comment('Số dư đang bị giữ/chờ xử lý.');
                $table->enum('status', ['active', 'locked', 'suspended'])->default('active')
                    ->comment('Trạng thái ví user.');
                $table->timestamps();

                $table->index('status', 'user_wallets_status_index');
                $table->foreign('user_id', 'user_wallets_user_foreign')
                    ->references('id')->on('users')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('user_wallet_ledgers')) {
            Schema::create('user_wallet_ledgers', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_wallet_id', 36)->comment('Ví user được ghi nhận biến động.');
                $table->string('transaction_code', 50)->unique()->comment('Mã giao dịch ví nội bộ.');
                $table->enum('type', ['deposit', 'payment', 'refund', 'withdrawal', 'adjustment'])
                    ->comment('Loại biến động ví user.');
                $table->enum('direction', ['credit', 'debit'])->comment('Chiều biến động số dư.');
                $table->decimal('amount', 14, 2)->comment('Số tiền biến động.');
                $table->decimal('balance_before', 14, 2)->comment('Số dư trước biến động.');
                $table->decimal('balance_after', 14, 2)->comment('Số dư sau biến động.');
                $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng tham chiếu như booking, payment, refund.');
                $table->string('reference_id', 100)->nullable()->comment('ID đối tượng tham chiếu.');
                $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed')
                    ->comment('Trạng thái giao dịch ví.');
                $table->text('note')->nullable()->comment('Ghi chú nghiệp vụ.');
                $table->char('created_by', 36)->nullable()->comment('User/admin tạo biến động; nullable nếu system.');
                $table->timestamps();

                $table->index(['user_wallet_id', 'created_at'], 'user_wallet_ledgers_wallet_created_index');
                $table->index(['reference_type', 'reference_id'], 'user_wallet_ledgers_reference_index');
                $table->index(['type', 'status'], 'user_wallet_ledgers_type_status_index');
                $table->foreign('user_wallet_id', 'user_wallet_ledgers_wallet_foreign')
                    ->references('id')->on('user_wallets')->onDelete('restrict');
                $table->foreign('created_by', 'user_wallet_ledgers_created_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('user_payout_accounts')) {
            Schema::create('user_payout_accounts', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36)->comment('User sở hữu tài khoản nhận tiền.');
                $table->string('bank_name', 100)->comment('Tên ngân hàng.');
                $table->string('bank_account_number', 50)->comment('Số tài khoản nhận tiền.');
                $table->string('bank_account_holder', 150)->comment('Tên chủ tài khoản.');
                $table->string('bank_branch', 150)->nullable()->comment('Chi nhánh ngân hàng nếu có.');
                $table->boolean('is_default')->default(false)->comment('Tài khoản mặc định.');
                $table->enum('status', ['active', 'inactive'])->default('active')->comment('Trạng thái tài khoản nhận tiền.');
                $table->timestamps();

                $table->unique(['user_id', 'bank_account_number'], 'user_payout_accounts_user_account_unique');
                $table->index(['user_id', 'status'], 'user_payout_accounts_user_status_index');
                $table->index(['status', 'is_default'], 'user_payout_accounts_status_default_index');
                $table->foreign('user_id', 'user_payout_accounts_user_foreign')
                    ->references('id')->on('users')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('user_withdrawal_requests')) {
            Schema::create('user_withdrawal_requests', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_wallet_id', 36)->comment('Ví user bị giữ/trừ tiền.');
                $table->char('user_id', 36)->comment('User yêu cầu rút tiền.');
                $table->char('payout_account_id', 36)->comment('Tài khoản nhận tiền user chọn.');
                $table->decimal('amount', 14, 2)->comment('Số tiền user yêu cầu rút.');
                $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending')
                    ->comment('Trạng thái yêu cầu rút tiền user.');
                $table->text('rejected_reason')->nullable()->comment('Lý do từ chối.');
                $table->char('approved_by', 36)->nullable()->comment('Admin duyệt yêu cầu.');
                $table->char('paid_by', 36)->nullable()->comment('Admin xác nhận đã chi trả.');
                $table->timestamp('requested_at')->useCurrent()->comment('Thời điểm user gửi yêu cầu.');
                $table->timestamp('approved_at')->nullable()->comment('Thời điểm duyệt.');
                $table->timestamp('paid_at')->nullable()->comment('Thời điểm chi trả.');
                $table->timestamps();

                $table->index(['user_id', 'status'], 'user_withdrawal_requests_user_status_index');
                $table->index(['status', 'requested_at'], 'user_withdrawal_requests_status_requested_index');
                $table->foreign('user_wallet_id', 'user_withdrawal_requests_wallet_foreign')
                    ->references('id')->on('user_wallets')->onDelete('restrict');
                $table->foreign('user_id', 'user_withdrawal_requests_user_foreign')
                    ->references('id')->on('users')->onDelete('restrict');
                $table->foreign('payout_account_id', 'user_withdrawal_requests_payout_foreign')
                    ->references('id')->on('user_payout_accounts')->onDelete('restrict');
                $table->foreign('approved_by', 'user_withdrawal_requests_approved_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
                $table->foreign('paid_by', 'user_withdrawal_requests_paid_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }

        if (Schema::hasTable('owner_wallet_ledgers')) {
            Schema::table('owner_wallet_ledgers', function (Blueprint $table) {
                if (! Schema::hasColumn('owner_wallet_ledgers', 'direction')) {
                    $table->enum('direction', ['credit', 'debit'])->nullable()->after('type')
                        ->comment('Chiều biến động số dư, bổ sung để đối soát rõ hơn.');
                }

                if (! Schema::hasColumn('owner_wallet_ledgers', 'status')) {
                    $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed')->after('balance_after')
                        ->comment('Trạng thái giao dịch ví chủ sân.');
                }

                if (! Schema::hasColumn('owner_wallet_ledgers', 'reference_type')) {
                    $table->string('reference_type', 100)->nullable()->after('reference_code')
                        ->comment('Loại đối tượng tham chiếu như booking, payment, refund, withdrawal.');
                }

                if (! Schema::hasColumn('owner_wallet_ledgers', 'reference_id')) {
                    $table->string('reference_id', 100)->nullable()->after('reference_type')
                        ->comment('ID đối tượng tham chiếu.');
                }

                if (! Schema::hasColumn('owner_wallet_ledgers', 'transaction_code')) {
                    $table->string('transaction_code', 50)->nullable()->after('reference_id')
                        ->comment('Mã giao dịch ví nội bộ.');
                    $table->unique('transaction_code', 'owner_wallet_ledgers_transaction_code_unique');
                }

                if (! Schema::hasColumn('owner_wallet_ledgers', 'note')) {
                    $table->text('note')->nullable()->after('description')
                        ->comment('Ghi chú nghiệp vụ ngắn.');
                }
            });

            Schema::table('owner_wallet_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('owner_wallet_ledgers', 'direction')) {
                    $table->index('direction', 'owner_wallet_ledgers_direction_index');
                }
                if (Schema::hasColumn('owner_wallet_ledgers', 'status')) {
                    $table->index('status', 'owner_wallet_ledgers_status_index');
                }
                if (Schema::hasColumn('owner_wallet_ledgers', 'reference_type') && Schema::hasColumn('owner_wallet_ledgers', 'reference_id')) {
                    $table->index(['reference_type', 'reference_id'], 'owner_wallet_ledgers_reference_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('owner_wallet_ledgers')) {
            Schema::table('owner_wallet_ledgers', function (Blueprint $table) {
                foreach ([
                    'owner_wallet_ledgers_direction_index',
                    'owner_wallet_ledgers_status_index',
                    'owner_wallet_ledgers_reference_index',
                    'owner_wallet_ledgers_transaction_code_unique',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::table('owner_wallet_ledgers', function (Blueprint $table) {
                foreach (['note', 'transaction_code', 'reference_id', 'reference_type', 'status', 'direction'] as $column) {
                    if (Schema::hasColumn('owner_wallet_ledgers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('user_withdrawal_requests');
        Schema::dropIfExists('user_payout_accounts');
        Schema::dropIfExists('user_wallet_ledgers');
        Schema::dropIfExists('user_wallets');
    }
};
