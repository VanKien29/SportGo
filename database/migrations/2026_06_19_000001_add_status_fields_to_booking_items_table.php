<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_items')) {
            return;
        }

        Schema::table('booking_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_items', 'status')) {
                $table->string('status', 40)->default('active')->after('subtotal');
            }

            if (! Schema::hasColumn('booking_items', 'status_reason')) {
                $table->text('status_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('booking_items', 'cancelled_by')) {
                $table->char('cancelled_by', 36)->nullable()->after('status_reason');
            }

            if (! Schema::hasColumn('booking_items', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }

            if (! Schema::hasColumn('booking_items', 'maintenance_lock_id')) {
                $table->char('maintenance_lock_id', 36)->nullable()->after('cancelled_at');
            }
        });

        Schema::table('booking_items', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_items', 'status')) {
                $table->index(['status', 'venue_court_id', 'start_time'], 'booking_items_status_court_time_index');
            }

            if (Schema::hasColumn('booking_items', 'maintenance_lock_id')) {
                $table->index('maintenance_lock_id', 'booking_items_maintenance_lock_id_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_items')) {
            return;
        }

        Schema::table('booking_items', function (Blueprint $table): void {
            foreach ([
                'booking_items_status_court_time_index',
                'booking_items_maintenance_lock_id_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    //
                }
            }
        });

        Schema::table('booking_items', function (Blueprint $table): void {
            foreach ([
                'maintenance_lock_id',
                'cancelled_at',
                'cancelled_by',
                'status_reason',
                'status',
            ] as $column) {
                if (Schema::hasColumn('booking_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
