<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_posts') || ! Schema::hasTable('bookings')) {
            return;
        }

        // Fix any player_posts referencing non-existent bookings
        $orphanedPosts = DB::table('player_posts')
            ->whereNotIn('booking_id', DB::table('bookings')->pluck('id'))
            ->get();

        foreach ($orphanedPosts as $post) {
            $validBooking = DB::table('bookings')
                ->where('customer_id', $post->author_id)
                ->orWhere('created_by', $post->author_id)
                ->orderBy('id', 'asc')
                ->first();

            if ($validBooking) {
                DB::table('player_posts')->where('id', $post->id)->update(['booking_id' => $validBooking->id]);
            }
        }
    }

    public function down(): void
    {
    }
};
