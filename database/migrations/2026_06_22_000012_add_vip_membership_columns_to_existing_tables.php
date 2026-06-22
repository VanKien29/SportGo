<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                if (! Schema::hasColumn('vouchers', 'source')) {
                    $table->enum('source', ['manual', 'vip_subscription'])->default('manual')->after('status')
                        ->comment('Nguon phat hanh voucher');
                }

                if (! Schema::hasColumn('vouchers', 'subscription_id')) {
                    $table->char('subscription_id', 36)->nullable()->after('source')
                        ->comment('FK den user_subscriptions neu source=vip_subscription');
                }
            });

            Schema::table('vouchers', function (Blueprint $table): void {
                if (Schema::hasColumn('vouchers', 'subscription_id') && ! Schema::hasIndex('vouchers', 'vouchers_subscription_id_index')) {
                    $table->index('subscription_id', 'vouchers_subscription_id_index');
                    $table->foreign('subscription_id', 'vouchers_subscription_id_foreign')
                        ->references('id')->on('user_subscriptions')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table): void {
                if (! Schema::hasColumn('complaints', 'is_vip_priority')) {
                    $table->boolean('is_vip_priority')->default(false)->after('complaint_type')
                        ->comment('Khieu nai tu user VIP, uu tien hien thi');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (! Schema::hasColumn('bookings', 'membership_tier_discount_amount')) {
                    $table->decimal('membership_tier_discount_amount', 12, 2)->default(0)->after('discount_amount')
                        ->comment('So tien giam tu hang thanh vien san');
                }

                if (! Schema::hasColumn('bookings', 'membership_tier')) {
                    $table->enum('membership_tier', ['standard', 'silver', 'gold', 'diamond'])->nullable()
                        ->after('membership_tier_discount_amount')
                        ->comment('Hang thanh vien ap dung tai thoi diem dat');
                }

                if (! Schema::hasColumn('bookings', 'cashback_amount')) {
                    $table->decimal('cashback_amount', 12, 2)->default(0)->after('membership_tier')
                        ->comment('Cashback VIP da hoan vao vi sau booking');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                foreach (['cashback_amount', 'membership_tier', 'membership_tier_discount_amount'] as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table): void {
                if (Schema::hasColumn('complaints', 'is_vip_priority')) {
                    $table->dropColumn('is_vip_priority');
                }
            });
        }

        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                if (Schema::hasIndex('vouchers', 'vouchers_subscription_id_index')) {
                    $table->dropForeign('vouchers_subscription_id_foreign');
                    $table->dropIndex('vouchers_subscription_id_index');
                }
            });

            Schema::table('vouchers', function (Blueprint $table): void {
                foreach (['subscription_id', 'source'] as $column) {
                    if (Schema::hasColumn('vouchers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
