<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('slot_locks', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân bị giữ/khóa; denormalized.');
            $table->char('venue_court_id', 36)->nullable()->comment('Sân con bị giữ/khóa; nullable khi khóa cả cụm.');
            $table->enum('lock_scope', ['court', 'cluster'])->default('court')->comment('Phạm vi khóa.');
            $table->date('booking_date')->comment('Ngày bị giữ/khóa.');
            $table->time('start_time')->comment('Giờ bắt đầu.');
            $table->time('end_time')->comment('Giờ kết thúc.');
            $table->string('locked_by', 100)->comment('Định danh người/session tạo lock.');
            $table->char('booking_id', 36)->nullable()->comment('Booking liên quan.');
            $table->enum('lock_type', ['auto', 'manual'])->default('auto')->comment('Loại lock.');
            $table->timestamp('expires_at')->comment('Thời điểm lock hết hạn.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo lock.');
            $table->index(['venue_court_id', 'booking_date', 'start_time', 'end_time'], 'slot_locks_court_time_index');
            $table->index('venue_cluster_id', 'slot_locks_venue_cluster_id_index');
            $table->index('booking_date', 'slot_locks_booking_date_index');
            $table->index('start_time', 'slot_locks_start_time_index');
            $table->index('end_time', 'slot_locks_end_time_index');
            $table->index('lock_scope', 'slot_locks_lock_scope_index');
            $table->index('locked_by', 'slot_locks_locked_by_index');
            $table->index('expires_at', 'slot_locks_expires_at_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('venue_court_id')->references('id')->on('venue_courts')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('slot_locks'); }
};
