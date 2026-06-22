<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slot_locks')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE slot_locks MODIFY COLUMN lock_type ENUM('auto', 'manual', 'emergency') NOT NULL DEFAULT 'auto' COMMENT 'Loai lock.'");
        }

        Schema::table('slot_locks', function (Blueprint $table): void {
            if (! Schema::hasColumn('slot_locks', 'notified_booking_ids')) {
                $table->json('notified_booking_ids')->nullable()->after('reason')
                    ->comment('Danh sach booking_id da duoc thong bao ve lock dot xuat');
            }

            if (! Schema::hasColumn('slot_locks', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable()->after('notified_booking_ids')
                    ->comment('Thoi diem da gui thong bao cho booking bi anh huong');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('slot_locks')) {
            return;
        }

        Schema::table('slot_locks', function (Blueprint $table): void {
            foreach (['notification_sent_at', 'notified_booking_ids'] as $column) {
                if (Schema::hasColumn('slot_locks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('slot_locks')->where('lock_type', 'emergency')->update(['lock_type' => 'manual']);
            DB::statement("ALTER TABLE slot_locks MODIFY COLUMN lock_type ENUM('auto', 'manual') NOT NULL DEFAULT 'auto' COMMENT 'Loai lock.'");
        }
    }
};
