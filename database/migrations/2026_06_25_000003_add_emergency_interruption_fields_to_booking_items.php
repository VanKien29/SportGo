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
            if (! Schema::hasColumn('booking_items', 'interrupted_at')) {
                $table->timestamp('interrupted_at')->nullable()->after('maintenance_lock_id');
            }
            if (! Schema::hasColumn('booking_items', 'played_minutes')) {
                $table->unsignedInteger('played_minutes')->nullable()->after('interrupted_at');
            }
            if (! Schema::hasColumn('booking_items', 'remaining_minutes')) {
                $table->unsignedInteger('remaining_minutes')->nullable()->after('played_minutes');
            }
            if (! Schema::hasColumn('booking_items', 'incident_refund_ratio')) {
                $table->decimal('incident_refund_ratio', 8, 6)->nullable()->after('remaining_minutes');
            }
            if (! Schema::hasColumn('booking_items', 'incident_resolution')) {
                $table->string('incident_resolution', 40)->nullable()->after('incident_refund_ratio');
            }
            if (! Schema::hasColumn('booking_items', 'incident_original_court_id')) {
                $table->char('incident_original_court_id', 36)->nullable()->after('incident_resolution');
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
                'incident_original_court_id',
                'incident_resolution',
                'incident_refund_ratio',
                'remaining_minutes',
                'played_minutes',
                'interrupted_at',
            ] as $column) {
                if (Schema::hasColumn('booking_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
