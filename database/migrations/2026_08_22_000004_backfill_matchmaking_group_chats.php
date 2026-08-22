<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_posts') || ! Schema::hasTable('conversations')) return;

        DB::table('player_posts as post')
            ->leftJoin('bookings as booking', 'booking.id', '=', 'post.booking_id')
            ->leftJoin('venue_clusters as venue', 'venue.id', '=', 'booking.venue_cluster_id')
            ->select(['post.id', 'post.author_id', 'venue.name as venue_name', 'booking.booking_date', 'booking.start_time', 'booking.end_time'])
            ->orderBy('post.id')
            ->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    $conversation = DB::table('conversations')
                        ->where('type', 'player_post')
                        ->where('reference_type', 'player_post')
                        ->where('reference_id', (string) $post->id)
                        ->first();

                    if (! $conversation) {
                        $now = now();
                        $date = $post->booking_date ? date('d/m/Y', strtotime($post->booking_date)) : '';
                        $time = $post->start_time && $post->end_time
                            ? substr((string) $post->start_time, 0, 5) . ' - ' . substr((string) $post->end_time, 0, 5)
                            : '';
                        $conversationId = DB::table('conversations')->insertGetId([
                            'type' => 'player_post',
                            'reference_type' => 'player_post',
                            'reference_id' => (string) $post->id,
                            'title' => 'Giao lưu · ' . ($post->venue_name ?: 'sân thể thao') . ($date ? ' · ' . $date : ''),
                            'created_by' => $post->author_id,
                            'last_message_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        DB::table('messages')->insert([
                            'conversation_id' => $conversationId,
                            'sender_id' => null,
                            'content' => 'Nhóm giao lưu đã được tạo. Thành viên được duyệt sẽ tự động được thêm vào nhóm.',
                            'is_system' => true,
                            'created_at' => $now,
                        ]);
                    } else {
                        $conversationId = $conversation->id;
                    }

                    $participant = DB::table('conversation_participants')
                        ->where('conversation_id', $conversationId)
                        ->where('user_id', $post->author_id)
                        ->first();
                    if ($participant) {
                        DB::table('conversation_participants')
                            ->where('id', $participant->id)
                            ->update(['left_at' => null, 'last_read_at' => $participant->last_read_at ?: now()]);
                    } else {
                        DB::table('conversation_participants')->insert([
                            'conversation_id' => $conversationId,
                            'user_id' => $post->author_id,
                            'joined_at' => now(),
                            'last_read_at' => now(),
                        ]);
                    }
                }
            }, 'post.id', 'id');
    }

    public function down(): void
    {
        // Keep conversations created by users; only new writes depend on the service.
    }
};
