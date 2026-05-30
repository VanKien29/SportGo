<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table) {
            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'period_months')) {
                $table->unsignedSmallInteger('period_months')->default(1)->after('billing_cycle')->comment('Số tháng của kỳ phí: 1, 3, 6, 9 hoặc 12.');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'due_date')) {
                $table->date('due_date')->nullable()->after('period_end')->comment('Hạn cuối owner cần đóng phí.');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_media_id')) {
                $table->char('payment_proof_media_id', 36)->nullable()->after('amount_paid')->comment('File bằng chứng thanh toán gần nhất trong media.');
                $table->foreign('payment_proof_media_id', 'vpfl_payment_proof_media_foreign')->references('id')->on('media')->onDelete('set null');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_status')) {
                $table->enum('payment_proof_status', ['none', 'submitted', 'approved', 'rejected'])->default('none')->after('payment_proof_media_id')->comment('Trạng thái duyệt bằng chứng thanh toán.');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_note')) {
                $table->text('payment_proof_note')->nullable()->after('payment_proof_status')->comment('Ghi chú từ owner/admin về bằng chứng thanh toán.');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'payment_confirmed_by')) {
                $table->char('payment_confirmed_by', 36)->nullable()->after('paid_at')->comment('Admin xác nhận thanh toán.');
                $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmed_by')->comment('Thời điểm xác nhận thanh toán.');
                $table->foreign('payment_confirmed_by', 'vpfl_confirmed_by_foreign')->references('id')->on('users')->onDelete('set null');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'payment_rejected_by')) {
                $table->char('payment_rejected_by', 36)->nullable()->after('payment_confirmed_at')->comment('Admin từ chối bằng chứng thanh toán.');
                $table->timestamp('payment_rejected_at')->nullable()->after('payment_rejected_by')->comment('Thời điểm từ chối bằng chứng.');
                $table->text('payment_reject_reason')->nullable()->after('payment_rejected_at')->comment('Lý do từ chối bằng chứng thanh toán.');
                $table->foreign('payment_rejected_by', 'vpfl_rejected_by_foreign')->references('id')->on('users')->onDelete('set null');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'locked_venue_at')) {
                $table->timestamp('locked_venue_at')->nullable()->after('payment_reject_reason')->comment('Thời điểm hệ thống/admin khóa cụm sân vì quá hạn phí.');
            }

            if (! Schema::hasColumn('venue_platform_fee_ledgers', 'internal_receipt_id') && Schema::hasTable('internal_receipts')) {
                $table->char('internal_receipt_id', 36)->nullable()->after('locked_venue_at')->comment('Phiếu/hóa đơn nội bộ phát hành cho kỳ phí.');
                $table->foreign('internal_receipt_id', 'vpfl_internal_receipt_foreign')->references('id')->on('internal_receipts')->onDelete('set null');
            }
        });

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'period_months')) {
                $table->index('period_months', 'vpfl_period_months_index');
            }

            if (Schema::hasColumn('venue_platform_fee_ledgers', 'due_date')) {
                $table->index('due_date', 'vpfl_due_date_index');
            }

            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_status')) {
                $table->index('payment_proof_status', 'vpfl_payment_proof_status_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'period_months')) {
                $table->dropIndex('vpfl_period_months_index');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'due_date')) {
                $table->dropIndex('vpfl_due_date_index');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_status')) {
                $table->dropIndex('vpfl_payment_proof_status_index');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_media_id')) {
                $table->dropForeign('vpfl_payment_proof_media_foreign');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_confirmed_by')) {
                $table->dropForeign('vpfl_confirmed_by_foreign');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_rejected_by')) {
                $table->dropForeign('vpfl_rejected_by_foreign');
            }
            if (Schema::hasColumn('venue_platform_fee_ledgers', 'internal_receipt_id')) {
                $table->dropForeign('vpfl_internal_receipt_foreign');
            }
        });

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table) {
            $columns = [
                'period_months',
                'due_date',
                'payment_proof_media_id',
                'payment_proof_status',
                'payment_proof_note',
                'payment_confirmed_by',
                'payment_confirmed_at',
                'payment_rejected_by',
                'payment_rejected_at',
                'payment_reject_reason',
                'locked_venue_at',
                'internal_receipt_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('venue_platform_fee_ledgers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
