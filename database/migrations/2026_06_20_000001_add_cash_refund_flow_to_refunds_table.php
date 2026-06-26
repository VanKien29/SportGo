<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refunds')) {
            DB::statement("ALTER TABLE refunds MODIFY refund_destination ENUM('original_payment','user_wallet','bank_account','cash') NOT NULL DEFAULT 'user_wallet'");
            DB::statement("ALTER TABLE refunds MODIFY status ENUM('pending_confirmation','processing','completed','completed_cash','failed','rejected','pending_owner_confirmation','owner_confirmed','owner_rejected','admin_processing','cancelled') NOT NULL DEFAULT 'pending_owner_confirmation'");

            Schema::table('refunds', function (Blueprint $table): void {
                if (! Schema::hasColumn('refunds', 'cash_refunded_by')) {
                    $table->char('cash_refunded_by', 36)->nullable()->after('completed_at');
                }
                if (! Schema::hasColumn('refunds', 'cash_refunded_at')) {
                    $table->timestamp('cash_refunded_at')->nullable()->after('cash_refunded_by');
                }
                if (! Schema::hasColumn('refunds', 'cash_refund_note')) {
                    $table->text('cash_refund_note')->nullable()->after('cash_refunded_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        DB::table('refunds')
            ->where('status', 'completed_cash')
            ->update(['status' => 'completed']);

        DB::table('refunds')
            ->where('refund_destination', 'cash')
            ->update(['refund_destination' => 'user_wallet']);

        Schema::table('refunds', function (Blueprint $table): void {
            foreach (['cash_refund_note', 'cash_refunded_at', 'cash_refunded_by'] as $column) {
                if (Schema::hasColumn('refunds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::statement("ALTER TABLE refunds MODIFY refund_destination ENUM('original_payment','user_wallet','bank_account') NOT NULL DEFAULT 'original_payment'");
        DB::statement("ALTER TABLE refunds MODIFY status ENUM('pending_confirmation','processing','completed','failed','rejected','pending_owner_confirmation','owner_confirmed','owner_rejected','admin_processing','cancelled') NOT NULL DEFAULT 'pending_owner_confirmation'");
    }
};
