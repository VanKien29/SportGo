<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_post_participants') || ! Schema::hasTable('conversation_participants')) return;

        DB::table('player_post_participants as member')
            ->join('conversations as conversation', function ($join): void {
                $join->on('conversation.reference_id', '=', DB::raw('CAST(member.post_id AS CHAR)'))
                    ->where('conversation.type', 'player_post')
                    ->where('conversation.reference_type', 'player_post');
            })
            ->where('member.status', 'approved')
            ->select(['member.id', 'member.post_id', 'member.user_id', 'conversation.id as conversation_id'])
            ->orderBy('member.id')
            ->chunkById(100, function ($members): void {
                foreach ($members as $member) {
                    $exists = DB::table('conversation_participants')
                        ->where('conversation_id', $member->conversation_id)
                        ->where('user_id', $member->user_id)
                        ->exists();
                    if ($exists) continue;

                    $now = now();
                    DB::table('conversation_participants')->insert([
                        'conversation_id' => $member->conversation_id,
                        'user_id' => $member->user_id,
                        'joined_at' => $now,
                        'last_read_at' => $now,
                    ]);
                    DB::table('messages')->insert([
                        'conversation_id' => $member->conversation_id,
                        'sender_id' => null,
                        'content' => 'Thành viên đã được thêm vào nhóm giao lưu.',
                        'is_system' => true,
                        'created_at' => $now,
                    ]);
                }
            }, 'member.id', 'id');
    }

    public function down(): void
    {
        // Keep membership history; it is part of the audit trail.
    }
};
