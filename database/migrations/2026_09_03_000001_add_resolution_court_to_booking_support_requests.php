<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_support_requests') || Schema::hasColumn('booking_support_requests', 'resolution_venue_court_id')) {
            return;
        }

        Schema::table('booking_support_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('resolution_venue_court_id')->nullable()->after('resolution_note');
            $table->foreign('resolution_venue_court_id', 'booking_support_resolution_court_fk')
                ->references('id')
                ->on('venue_courts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_support_requests') || ! Schema::hasColumn('booking_support_requests', 'resolution_venue_court_id')) {
            return;
        }

        Schema::table('booking_support_requests', function (Blueprint $table): void {
            $table->dropForeign('booking_support_resolution_court_fk');
            $table->dropColumn('resolution_venue_court_id');
        });
    }
};
