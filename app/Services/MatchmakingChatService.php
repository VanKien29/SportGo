<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\PlayerPost;
use Illuminate\Support\Facades\DB;

/**
 * Owns the lifecycle of the private chat attached to a matchmaking post.
 * The conversation deliberately uses the existing `player_post` enum value;
 * older generic group creation used an unsupported `group` value.
 */
class MatchmakingChatService
{
    public function conversationFor(PlayerPost|int $post): ?Conversation
    {
        $postId = $post instanceof PlayerPost ? $post->getKey() : $post;

        return Conversation::query()
            ->where('type', 'player_post')
            ->where('reference_type', 'player_post')
            ->where('reference_id', (string) $postId)
            ->first();
    }

    public function ensureGroup(PlayerPost $post): Conversation
    {
        if (! $post->relationLoaded('booking') || ! $post->booking?->relationLoaded('venueCourt')) {
            $post->loadMissing(['booking.venueCluster', 'booking.venueCourt.courtType.parent']);
        }

        $booking = $post->booking;
        $courtType = $booking?->venueCourt?->courtType;
        $sportName = $courtType?->parent?->name ?? $courtType?->name ?? 'Giao lưu';
        $venueName = $booking?->venueCluster?->name ?: 'sân thể thao';

        $startTime = $booking ? substr((string) $booking->start_time, 0, 5) : '';
        $date = $booking?->booking_date?->format('d/m') ?: '';
        $timeDateStr = trim(($startTime ? $startTime . ', ' : '') . $date);

        $title = 'Kèo ' . $sportName . ' - ' . $venueName . ($timeDateStr ? ' (' . $timeDateStr . ')' : '');

        $conversation = Conversation::query()->firstOrCreate(
            [
                'type' => 'player_post',
                'reference_type' => 'player_post',
                'reference_id' => (string) $post->id,
            ],
            [
                'title' => $title,
                'created_by' => $post->author_id,
                'last_message_at' => now(),
            ],
        );

        // Cập nhật lại title nếu title trước đó chưa theo format mới
        if ($conversation->title !== $title) {
            $conversation->forceFill(['title' => $title])->save();
        }

        $creator = ConversationParticipant::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $post->author_id,
        ]);
        $wasNew = ! $creator->exists;
        $creator->joined_at = $creator->joined_at ?: now();
        $creator->left_at = null;
        $creator->last_read_at = $creator->last_read_at ?: now();
        $creator->save();

        if ($wasNew) {
            $this->systemMessage($conversation, 'Nhóm giao lưu đã được tạo. Thành viên được duyệt sẽ tự động được thêm vào nhóm.');
        }

        return $conversation->fresh();
    }

    public function addMember(PlayerPost $post, int|string $userId, ?string $displayName = null): Conversation
    {
        $conversation = $this->ensureGroup($post);
        $participant = ConversationParticipant::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
        ]);
        $wasInactive = $participant->exists && $participant->left_at !== null;
        $wasNew = ! $participant->exists;
        $participant->joined_at = now();
        $participant->left_at = null;
        $participant->save();

        if ($wasNew || $wasInactive) {
            $this->systemMessage($conversation, ($displayName ?: 'Một người chơi') . ' đã tham gia nhóm lúc ' . $this->businessNow()->format('d/m/Y H:i') . '.');
        }

        return $conversation->fresh();
    }

    public function markLeft(PlayerPost $post, int|string $userId, ?string $displayName = null): ?Conversation
    {
        $conversation = $this->conversationFor($post);
        if (! $conversation) return null;

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();
        if (! $participant || $participant->left_at) return $conversation;

        $leftAt = now();
        $participant->forceFill(['left_at' => $leftAt])->save();
        $this->systemMessage($conversation, ($displayName ?: 'Một người chơi') . ' đã rời nhóm lúc ' . $leftAt->copy()->setTimezone($this->businessTimezone())->format('d/m/Y H:i') . '.');

        return $conversation->fresh();
    }

    public function dissolve(PlayerPost $post): void
    {
        $conversation = $this->conversationFor($post);
        if (! $conversation) return;

        // Mark all participants as left (BR-17: never delete the group chat)
        $now = now();
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->update(['left_at' => $now]);

        $this->systemMessage($conversation, 'Nhóm giao lưu đã được giải tán bởi chủ bài lúc ' . $now->copy()->setTimezone($this->businessTimezone())->format('d/m/Y H:i') . '. Bạn vẫn có thể xem lại lịch sử trò chuyện.');
    }

    private function systemMessage(Conversation $conversation, string $content): void
    {
        $now = now();
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'content' => $content,
            'is_system' => true,
            'created_at' => $now,
        ]);
        $conversation->forceFill(['last_message_at' => $now])->save();

        try {
            broadcast(new MessageSent((string) $conversation->id, [
                'id' => $message->id,
                'conversation_id' => (string) $conversation->id,
                'sender_id' => null,
                'sender_name' => 'Hệ thống',
                'sender_avatar' => null,
                'content' => $content,
                'message_type' => 'text',
                'is_system' => true,
                'created_at' => $now->toISOString(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
    }

    private function businessNow(): \Carbon\Carbon
    {
        return now($this->businessTimezone());
    }
}
