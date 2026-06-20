<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            $table->time('fixed_open_time')->default('08:00:00')->after('min_advance_booking_minutes');
            $table->time('fixed_close_time')->default('22:00:00')->after('fixed_open_time');
        });

        DB::table('booking_configs')
            ->select(['venue_cluster_id', 'weekly_operating_hours'])
            ->orderBy('venue_cluster_id')
            ->get()
            ->each(function (object $config): void {
                $weeklyHours = json_decode($config->weekly_operating_hours ?? '[]', true);
                $firstOpenDay = collect($weeklyHours)->first(
                    fn (array $hours): bool => (bool) ($hours['is_open'] ?? false)
                );

                if (! $firstOpenDay) {
                    return;
                }

                DB::table('booking_configs')
                    ->where('venue_cluster_id', $config->venue_cluster_id)
                    ->update([
                        'fixed_open_time' => $firstOpenDay['open_time'],
                        'fixed_close_time' => $firstOpenDay['close_time'],
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            $table->dropColumn(['fixed_open_time', 'fixed_close_time']);
        });
    }
};
