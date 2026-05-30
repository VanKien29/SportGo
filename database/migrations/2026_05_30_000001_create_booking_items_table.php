<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // --- Liên kết ---
            $table->char('booking_id', 36)
                ->comment('Đơn đặt sân cha.');
            $table->char('venue_court_id', 36)
                ->comment('Sân con thực tế được gán.');
            $table->char('requested_venue_court_id', 36)->nullable()
                ->comment('Sân con khách yêu cầu ban đầu.');

            // --- Thời gian ---
            $table->time('start_time')
                ->comment('Giờ bắt đầu của item.');
            $table->time('end_time')
                ->comment('Giờ kết thúc của item.');
            $table->unsignedInteger('duration_minutes')
                ->comment('Thời lượng tính bằng phút.');

            // --- Giá ---
            $table->decimal('unit_price', 12, 2)->default(0.00)
                ->comment('Đơn giá trung bình/giờ tại thời điểm đặt.');
            $table->decimal('subtotal', 12, 2)->default(0.00)
                ->comment('Thành tiền = (duration/60) * unit_price.');

            // --- Đổi sân ---
            $table->char('court_changed_by', 36)->nullable()
                ->comment('Người đổi sân.');
            $table->timestamp('court_changed_at')->nullable()
                ->comment('Thời điểm đổi sân.');
            $table->text('court_changed_reason')->nullable()
                ->comment('Lý do đổi sân.');

            // --- Sắp xếp ---
            $table->unsignedInteger('sort_order')->default(0)
                ->comment('Thứ tự hiển thị.');

            $table->timestamps();

            // --- Foreign Keys ---
            $table->foreign('booking_id')
                ->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('venue_court_id')
                ->references('id')->on('venue_courts')->onDelete('restrict');
            $table->foreign('requested_venue_court_id')
                ->references('id')->on('venue_courts')->onDelete('set null');
            $table->foreign('court_changed_by')
                ->references('id')->on('users')->onDelete('set null');

            // --- Indexes ---
            $table->index(['booking_id', 'sort_order'], 'booking_items_booking_sort_index');
            $table->index(['venue_court_id', 'start_time', 'end_time'], 'booking_items_court_time_index');
        });

        // --- Thêm booking_item_id vào slot_locks ---
        Schema::table('slot_locks', function (Blueprint $table) {
            $table->char('booking_item_id', 36)->nullable()->after('booking_id')
                ->comment('Item cụ thể được lock.');
            $table->index('booking_item_id', 'slot_locks_booking_item_id_index');
            $table->foreign('booking_item_id')
                ->references('id')->on('booking_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('slot_locks', function (Blueprint $table) {
            $table->dropForeign(['booking_item_id']);
            $table->dropIndex('slot_locks_booking_item_id_index');
            $table->dropColumn('booking_item_id');
        });

        Schema::dropIfExists('booking_items');
    }
};
