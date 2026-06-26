<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'cancellation_initiator')) {
                $table->enum('cancellation_initiator', ['customer', 'owner', 'system', 'admin'])->nullable()->after('cancelled_by')
                    ->comment('Ben khoi tao huy booking');
            }

            if (! Schema::hasColumn('bookings', 'cancellation_reason_type')) {
                $table->enum('cancellation_reason_type', [
                    'customer_request',
                    'owner_maintenance',
                    'owner_emergency',
                    'venue_locked',
                    'system_auto',
                    'admin_action',
                    'no_payment',
                    'expired',
                ])->nullable()->after('cancellation_initiator')
                    ->comment('Phan loai ly do huy de tinh chinh sach hoan tien');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (
                Schema::hasColumn('bookings', 'cancellation_initiator')
                && Schema::hasColumn('bookings', 'cancellation_reason_type')
                && ! Schema::hasIndex('bookings', 'bookings_cancellation_reason_index')
            ) {
                $table->index(['cancellation_initiator', 'cancellation_reason_type'], 'bookings_cancellation_reason_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasIndex('bookings', 'bookings_cancellation_reason_index')) {
                $table->dropIndex('bookings_cancellation_reason_index');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['cancellation_reason_type', 'cancellation_initiator'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
