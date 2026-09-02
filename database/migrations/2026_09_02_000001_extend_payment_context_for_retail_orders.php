<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'payment_context')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_context ENUM('booking','vip_subscription','retail_order') NOT NULL DEFAULT 'booking'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'payment_context')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::table('payments')
                ->where('payment_context', 'retail_order')
                ->update(['payment_context' => 'booking']);

            DB::statement("ALTER TABLE payments MODIFY payment_context ENUM('booking','vip_subscription') NOT NULL DEFAULT 'booking'");
        }
    }
};
