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

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'venue_court_id')) {
                $table->char('venue_court_id', 36)->nullable()->after('customer_id')->comment('Sân con thực tế được gán cho buổi chơi.');
                $table->foreign('venue_court_id', 'bookings_venue_court_id_foreign')->references('id')->on('venue_courts')->onDelete('restrict');
            }

            if (! Schema::hasColumn('bookings', 'requested_venue_court_id')) {
                $table->char('requested_venue_court_id', 36)->nullable()->after('venue_court_id')->comment('Sân con khách yêu cầu ban đầu.');
                $table->foreign('requested_venue_court_id', 'bookings_requested_venue_court_id_foreign')->references('id')->on('venue_courts')->onDelete('set null');
            }

            if (! Schema::hasColumn('bookings', 'start_time')) {
                $table->time('start_time')->nullable()->after('booking_date')->comment('Giờ bắt đầu booking.');
            }

            if (! Schema::hasColumn('bookings', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time')->comment('Giờ kết thúc booking.');
            }

            if (! Schema::hasColumn('bookings', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('end_time')->comment('Tổng thời lượng booking tính bằng phút.');
            }

            if (! Schema::hasColumn('bookings', 'court_changed_by')) {
                $table->char('court_changed_by', 36)->nullable()->after('created_by')->comment('Chủ sân/nhân viên đổi sân.');
                $table->timestamp('court_changed_at')->nullable()->after('court_changed_by')->comment('Thời điểm đổi sân con.');
                $table->text('court_changed_reason')->nullable()->after('court_changed_at')->comment('Lý do đổi sân.');
                $table->foreign('court_changed_by', 'bookings_court_changed_by_foreign')->references('id')->on('users')->onDelete('set null');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (
                Schema::hasColumn('bookings', 'venue_court_id')
                && ! Schema::hasIndex('bookings', 'bookings_court_date_time_index')
            ) {
                $table->index(['venue_court_id', 'booking_date', 'start_time', 'end_time'], 'bookings_court_date_time_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'venue_court_id')) {
                $table->dropIndex('bookings_court_date_time_index');
                $table->dropForeign('bookings_venue_court_id_foreign');
            }

            if (Schema::hasColumn('bookings', 'requested_venue_court_id')) {
                $table->dropForeign('bookings_requested_venue_court_id_foreign');
            }

            if (Schema::hasColumn('bookings', 'court_changed_by')) {
                $table->dropForeign('bookings_court_changed_by_foreign');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $columns = [
                'venue_court_id',
                'requested_venue_court_id',
                'start_time',
                'end_time',
                'duration_minutes',
                'court_changed_by',
                'court_changed_at',
                'court_changed_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
