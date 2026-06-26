<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                if (! Schema::hasColumn('vouchers', 'assigned_user_id')) {
                    $table->char('assigned_user_id', 36)->nullable()->after('subscription_id')
                        ->comment('User được gán riêng voucher VIP nếu có.');
                }
            });

            Schema::table('vouchers', function (Blueprint $table): void {
                if (Schema::hasColumn('vouchers', 'assigned_user_id')) {
                    $table->index('assigned_user_id', 'vouchers_assigned_user_index');
                    $table->foreign('assigned_user_id', 'vouchers_assigned_user_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('voucher_scopes') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE voucher_scopes MODIFY scope_type ENUM('all','venue_cluster','court_type','booking_type','membership_tier','vip_package') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                try {
                    $table->dropForeign('vouchers_assigned_user_foreign');
                } catch (Throwable) {
                }
                try {
                    $table->dropIndex('vouchers_assigned_user_index');
                } catch (Throwable) {
                }
            });

            Schema::table('vouchers', function (Blueprint $table): void {
                if (Schema::hasColumn('vouchers', 'assigned_user_id')) {
                    $table->dropColumn('assigned_user_id');
                }
            });
        }

        if (Schema::hasTable('voucher_scopes') && DB::connection()->getDriverName() === 'mysql') {
            DB::table('voucher_scopes')->where('scope_type', 'vip_package')->delete();
            DB::statement("ALTER TABLE voucher_scopes MODIFY scope_type ENUM('all','venue_cluster','court_type','booking_type','membership_tier') NOT NULL");
        }
    }
};
