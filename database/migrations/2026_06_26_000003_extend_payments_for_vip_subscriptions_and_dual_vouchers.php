<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_subscriptions') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_subscriptions MODIFY status ENUM('pending_payment','active','expired','cancelled') NOT NULL DEFAULT 'pending_payment'");
        }

        if (Schema::hasTable('payments')) {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE payments MODIFY booking_id CHAR(36) NULL');
            }

            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'payment_context')) {
                    $table->enum('payment_context', ['booking', 'vip_subscription'])
                        ->default('booking')
                        ->after('payment_code')
                        ->comment('Nghiep vu thanh toan.');
                }

                if (! Schema::hasColumn('payments', 'subscription_id')) {
                    $table->char('subscription_id', 36)
                        ->nullable()
                        ->after('booking_id')
                        ->comment('Subscription VIP duoc thanh toan neu payment_context=vip_subscription.');
                }
            });

            Schema::table('payments', function (Blueprint $table): void {
                if (Schema::hasColumn('payments', 'payment_context')) {
                    $table->index(['payment_context', 'status'], 'payments_context_status_index');
                }

                if (Schema::hasColumn('payments', 'subscription_id')) {
                    $table->index(['subscription_id', 'status'], 'payments_subscription_status_index');
                    $table->foreign('subscription_id', 'payments_subscription_id_foreign')
                        ->references('id')->on('user_subscriptions')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (! Schema::hasColumn('bookings', 'venue_voucher_id')) {
                    $table->char('venue_voucher_id', 36)->nullable()->after('voucher_code_snapshot');
                }

                if (! Schema::hasColumn('bookings', 'venue_voucher_code_snapshot')) {
                    $table->string('venue_voucher_code_snapshot', 50)->nullable()->after('venue_voucher_id');
                }

                if (! Schema::hasColumn('bookings', 'vip_voucher_id')) {
                    $table->char('vip_voucher_id', 36)->nullable()->after('venue_voucher_code_snapshot');
                }

                if (! Schema::hasColumn('bookings', 'vip_voucher_code_snapshot')) {
                    $table->string('vip_voucher_code_snapshot', 50)->nullable()->after('vip_voucher_id');
                }
            });

            Schema::table('bookings', function (Blueprint $table): void {
                if (Schema::hasColumn('bookings', 'venue_voucher_id')) {
                    $table->index('venue_voucher_id', 'bookings_venue_voucher_id_index');
                    $table->foreign('venue_voucher_id', 'bookings_venue_voucher_id_foreign')
                        ->references('id')->on('vouchers')->onDelete('set null');
                }

                if (Schema::hasColumn('bookings', 'vip_voucher_id')) {
                    $table->index('vip_voucher_id', 'bookings_vip_voucher_id_index');
                    $table->foreign('vip_voucher_id', 'bookings_vip_voucher_id_foreign')
                        ->references('id')->on('vouchers')->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                foreach ([
                    'bookings_venue_voucher_id_foreign',
                    'bookings_vip_voucher_id_foreign',
                ] as $foreign) {
                    try {
                        $table->dropForeign($foreign);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'bookings_venue_voucher_id_index',
                    'bookings_vip_voucher_id_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::table('bookings', function (Blueprint $table): void {
                foreach ([
                    'vip_voucher_code_snapshot',
                    'vip_voucher_id',
                    'venue_voucher_code_snapshot',
                    'venue_voucher_id',
                ] as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                try {
                    $table->dropForeign('payments_subscription_id_foreign');
                } catch (Throwable) {
                }
                try {
                    $table->dropIndex('payments_subscription_status_index');
                } catch (Throwable) {
                }
                try {
                    $table->dropIndex('payments_context_status_index');
                } catch (Throwable) {
                }
            });

            Schema::table('payments', function (Blueprint $table): void {
                foreach (['subscription_id', 'payment_context'] as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE payments MODIFY booking_id CHAR(36) NOT NULL');
            }
        }

        if (Schema::hasTable('user_subscriptions') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_subscriptions MODIFY status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active'");
        }
    }
};
