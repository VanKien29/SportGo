<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('code', 50)->unique()->comment('Mã voucher user nhập.');
                $table->string('name', 255)->comment('Tên voucher hiển thị.');
                $table->text('description')->nullable()->comment('Mô tả voucher.');
                $table->enum('owner_type', ['system', 'venue'])->comment('Voucher hệ thống hay voucher sân.');
                $table->char('owner_id', 36)->nullable()->comment('ID owner/cụm sân sở hữu voucher nếu owner_type=venue.');
                $table->enum('funded_by', ['system', 'venue'])->comment('Bên chịu tiền giảm.');
                $table->enum('stacking_rule', ['exclusive', 'allow_with_system', 'allow_with_venue'])->default('exclusive')
                    ->comment('Quy tắc dùng chung với voucher khác.');
                $table->enum('discount_type', ['percent', 'fixed'])->comment('Kiểu giảm giá.');
                $table->decimal('discount_value', 12, 2)->comment('Giá trị giảm theo percent hoặc fixed.');
                $table->decimal('max_discount_amount', 12, 2)->nullable()->comment('Mức giảm tối đa.');
                $table->decimal('min_order_amount', 12, 2)->default(0.00)->comment('Giá trị đơn tối thiểu.');
                $table->unsignedInteger('total_quantity')->nullable()->comment('Tổng số lượt phát hành/sử dụng.');
                $table->unsignedInteger('used_quantity')->default(0)->comment('Số lượt đã dùng.');
                $table->unsignedInteger('per_user_limit')->nullable()->comment('Số lượt tối đa mỗi user.');
                $table->dateTime('valid_from')->nullable()->comment('Thời điểm bắt đầu hiệu lực.');
                $table->dateTime('valid_to')->nullable()->comment('Thời điểm hết hiệu lực.');
                $table->enum('status', ['draft', 'active', 'inactive', 'expired'])->default('draft')
                    ->comment('Trạng thái voucher.');
                $table->char('created_by', 36)->nullable()->comment('Admin/owner tạo voucher.');
                $table->timestamps();

                $table->index(['owner_type', 'owner_id'], 'vouchers_owner_index');
                $table->index(['status', 'valid_from', 'valid_to'], 'vouchers_status_valid_index');
                $table->index('funded_by', 'vouchers_funded_by_index');
                $table->foreign('created_by', 'vouchers_created_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('voucher_scopes')) {
            Schema::create('voucher_scopes', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('voucher_id', 36)->comment('Voucher được giới hạn phạm vi.');
                $table->enum('scope_type', ['all', 'venue_cluster', 'court_type', 'booking_type'])
                    ->comment('Loại phạm vi áp dụng.');
                $table->string('scope_id', 100)->nullable()->comment('ID/mã phạm vi; nullable khi scope_type=all.');
                $table->string('scope_key', 120)->default('__all__')
                    ->comment('Khóa ổn định để unique scope kể cả khi scope_id null.');
                $table->timestamps();

                $table->index(['scope_type', 'scope_id'], 'voucher_scopes_scope_index');
                $table->unique(['voucher_id', 'scope_type', 'scope_key'], 'voucher_scopes_voucher_scope_unique');
                $table->foreign('voucher_id', 'voucher_scopes_voucher_foreign')
                    ->references('id')->on('vouchers')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('voucher_usages')) {
            Schema::create('voucher_usages', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('voucher_id', 36)->comment('Voucher đã dùng.');
                $table->char('user_id', 36)->comment('User dùng voucher.');
                $table->char('booking_id', 36)->comment('Booking áp dụng voucher.');
                $table->char('payment_id', 36)->nullable()->comment('Payment liên quan nếu đã thanh toán.');
                $table->decimal('discount_amount', 12, 2)->default(0.00)->comment('Số tiền giảm thực tế.');
                $table->timestamp('used_at')->nullable()->comment('Thời điểm áp dụng voucher.');
                $table->enum('status', ['applied', 'cancelled', 'refunded'])->default('applied')
                    ->comment('Trạng thái usage khi booking hủy/refund.');
                $table->timestamps();

                $table->index(['voucher_id', 'status'], 'voucher_usages_voucher_status_index');
                $table->index(['user_id', 'voucher_id'], 'voucher_usages_user_voucher_index');
                $table->index('booking_id', 'voucher_usages_booking_index');
                $table->unique(['voucher_id', 'user_id', 'booking_id'], 'voucher_usages_voucher_user_booking_unique');
                $table->foreign('voucher_id', 'voucher_usages_voucher_foreign')
                    ->references('id')->on('vouchers')->onDelete('restrict');
                $table->foreign('user_id', 'voucher_usages_user_foreign')
                    ->references('id')->on('users')->onDelete('restrict');
                $table->foreign('booking_id', 'voucher_usages_booking_foreign')
                    ->references('id')->on('bookings')->onDelete('restrict');
                $table->foreign('payment_id', 'voucher_usages_payment_foreign')
                    ->references('id')->on('payments')->onDelete('set null');
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'original_amount')) {
                    $table->decimal('original_amount', 12, 2)->nullable()->after('total_price')
                        ->comment('Tổng tiền trước khi áp voucher/discount.');
                }

                if (! Schema::hasColumn('bookings', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0.00)->after('original_amount')
                        ->comment('Tổng tiền được giảm.');
                }

                if (! Schema::hasColumn('bookings', 'system_discount_amount')) {
                    $table->decimal('system_discount_amount', 12, 2)->default(0.00)->after('discount_amount')
                        ->comment('Phần giảm do nền tảng chịu.');
                }

                if (! Schema::hasColumn('bookings', 'venue_discount_amount')) {
                    $table->decimal('venue_discount_amount', 12, 2)->default(0.00)->after('system_discount_amount')
                        ->comment('Phần giảm do chủ sân/cụm sân chịu.');
                }

                if (! Schema::hasColumn('bookings', 'final_amount')) {
                    $table->decimal('final_amount', 12, 2)->nullable()->after('venue_discount_amount')
                        ->comment('Số tiền cuối cùng sau voucher/discount.');
                }

                if (! Schema::hasColumn('bookings', 'voucher_id')) {
                    $table->char('voucher_id', 36)->nullable()->after('final_amount')
                        ->comment('Voucher chính áp dụng nếu chỉ cho một voucher/booking.');
                }

                if (! Schema::hasColumn('bookings', 'voucher_code_snapshot')) {
                    $table->string('voucher_code_snapshot', 50)->nullable()->after('voucher_id')
                        ->comment('Snapshot mã voucher tại thời điểm đặt.');
                }
            });

            Schema::table('bookings', function (Blueprint $table) {
                if (Schema::hasColumn('bookings', 'voucher_id')) {
                    $table->index('voucher_id', 'bookings_voucher_id_index');
                    $table->foreign('voucher_id', 'bookings_voucher_foreign')
                        ->references('id')->on('vouchers')->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                try {
                    $table->dropForeign('bookings_voucher_foreign');
                } catch (Throwable) {
                }
                try {
                    $table->dropIndex('bookings_voucher_id_index');
                } catch (Throwable) {
                }
            });

            Schema::table('bookings', function (Blueprint $table) {
                foreach ([
                    'voucher_code_snapshot',
                    'voucher_id',
                    'final_amount',
                    'venue_discount_amount',
                    'system_discount_amount',
                    'discount_amount',
                    'original_amount',
                ] as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('voucher_scopes');
        Schema::dropIfExists('vouchers');
    }
};
