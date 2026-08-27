<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PlayerPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileMatchmakingPosts extends Command
{
    protected $signature = 'matchmaking:reconcile-lifecycle';

    protected $description = 'Khóa bài giao lưu và hủy yêu cầu chờ khi booking bắt đầu/kết thúc';

    public function handle(): int
    {
        $closed = 0;
        $cancelled = 0;

        PlayerPost::query()
            ->with('booking')
            ->whereIn('status', ['open', 'full'])
            ->whereHas('booking')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$closed, &$cancelled): void {
                foreach ($posts as $post) {
                    if (! $post->booking || ! $this->bookingStarted($post->booking)) {
                        continue;
                    }

                    DB::transaction(function () use ($post, &$closed, &$cancelled): void {
                        $lockedPost = PlayerPost::with('booking')->lockForUpdate()->find($post->id);
                        if (! $lockedPost || ! $lockedPost->booking || ! $this->bookingStarted($lockedPost->booking)) {
                            return;
                        }

                        $now = now();
                        $pending = DB::table('player_post_participants')
                            ->where('post_id', $lockedPost->id)
                            ->where('status', 'pending')
                            ->get(['id', 'user_id']);

                        if ($pending->isNotEmpty()) {
                            DB::table('player_post_participants')
                                ->where('post_id', $lockedPost->id)
                                ->where('status', 'pending')
                                ->update([
                                    'status' => 'expired',
                                    'responded_at' => $now,
                                    'left_at' => $now,
                                    'updated_at' => $now,
                                ]);
                            $cancelled += $pending->count();

                            foreach ($pending as $participant) {
                                Notification::create([
                                    'user_id' => $participant->user_id,
                                    'type' => 'matchmaking_request_expired',
                                    'title' => 'Yêu cầu giao lưu đã tự hủy',
                                    'body' => $this->bookingEnded($lockedPost->booking)
                                        ? 'Buổi giao lưu đã kết thúc nên yêu cầu tham gia của bạn đã được tự động hủy.'
                                        : 'Đã đến giờ booking nên bài giao lưu đã khóa nhận thêm người và yêu cầu của bạn được tự động hủy.',
                                    'reference_type' => 'player_post',
                                    'reference_id' => $lockedPost->id,
                                    'data' => ['action_url' => '/matchmaking-requests/' . $participant->id],
                                ]);
                            }
                        }

                        if (in_array($lockedPost->status, ['open', 'full'], true)) {
                            $lockedPost->forceFill([
                                'status' => 'closed',
                                'status_reason' => $this->bookingEnded($lockedPost->booking)
                                    ? 'matchmaking_session_ended'
                                    : 'matchmaking_session_started',
                            ])->save();
                            $closed++;
                        }
                    });
                }
            });

        $this->info("Đã khóa {$closed} bài giao lưu và hủy {$cancelled} yêu cầu chờ.");
        return self::SUCCESS;
    }

    private function bookingStarted(object $booking): bool
    {
        $now = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;

        return $date < $now->toDateString()
            || ($date === $now->toDateString() && substr((string) $booking->start_time, 0, 8) <= $now->toTimeString());
    }

    private function bookingEnded(object $booking): bool
    {
        $now = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;

        return $date < $now->toDateString()
            || ($date === $now->toDateString() && substr((string) $booking->end_time, 0, 8) <= $now->toTimeString());
    }
}
