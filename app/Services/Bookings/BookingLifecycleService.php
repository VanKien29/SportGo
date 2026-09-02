<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Notification;
use App\Models\PlayerPost;
use App\Models\User;
use App\Events\MatchmakingUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BookingLifecycleService
{
    private const STATUS_COPY = [
        'confirmed' => ['booking_confirmed', 'Booking đã được xác nhận', 'Chủ sân đã duyệt booking của bạn.'],
        'rejected' => ['booking_rejected', 'Booking bị từ chối', 'Chủ sân không thể nhận booking này.'],
        'cancelled' => ['booking_cancelled', 'Booking đã bị hủy', 'Booking của bạn đã được hủy theo chính sách.'],
        'expired' => ['booking_expired', 'Booking đã hết hiệu lực', 'Booking không còn hiệu lực do quá thời hạn xử lý.'],
        'checked_in' => ['booking_checked_in', 'Check-in thành công', 'Booking đã được ghi nhận đang sử dụng sân.'],
        'completed' => ['booking_completed', 'Booking đã hoàn thành', 'Buổi chơi đã kết thúc và booking được tự động hoàn tất.'],
        'no_show' => ['booking_no_show', 'Booking được ghi nhận no-show', 'Booking đã quá giờ cho phép nhưng chưa có check-in.'],
    ];

    public function transition(Booking $booking, string $toStatus, string $reasonCode, ?string $reason = null, ?User $actor = null, array $metadata = []): Booking
    {
        return DB::transaction(function () use ($booking, $toStatus, $reasonCode, $reason, $actor, $metadata): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $toStatus) {
                return $locked->fresh(['customer', 'venueCluster.owner', 'payments']);
            }

            $fromStatus = $locked->status;
            $locked->forceFill(['status' => $toStatus, 'status_reason' => $reason])->save();

            return $locked->fresh(['customer', 'venueCluster.owner', 'payments']);
        });
    }

    public function recordHistory(Booking $booking, ?string $fromStatus, string $toStatus, ?string $reasonCode = null, ?string $reason = null, ?User $actor = null, array $metadata = []): void
    {
        if (! class_exists(BookingStatusHistory::class)) return;

        BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'actor_id' => $actor?->id,
            'metadata' => $metadata,
        ]);
    }

    public function notifyStatusChanged(Booking $booking, string $status, ?string $reasonCode = null, ?string $reason = null, array $metadata = []): void
    {
        $booking->loadMissing(['customer', 'venueCluster.owner']);
        [$type, $title, $defaultBody] = self::STATUS_COPY[$status]
            ?? ['booking_status_changed', 'Booking được cập nhật', 'Trạng thái booking của bạn đã được cập nhật.'];
        $eventKey = sprintf('booking:%s:%s:%s', $booking->id, $status, $reasonCode ?: 'status');
        $recipients = [];
        if ($booking->customer_id) $recipients[(int) $booking->customer_id] = true;
        if (in_array($status, ['expired', 'no_show'], true) && $booking->venueCluster?->owner_id) {
            $recipients[(int) $booking->venueCluster->owner_id] = true;
        }

        foreach (array_keys($recipients) as $userId) {
            Notification::query()->firstOrCreate(
                ['user_id' => $userId, 'type' => $type, 'reference_type' => 'booking', 'reference_id' => (string) $booking->id],
                [
                    'title' => $title,
                    'body' => $reason ?: $defaultBody,
                    'data' => [
                        'booking_id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'status' => $status,
                        'reason_code' => $reasonCode,
                        'event_key' => $eventKey,
                        'action_url' => '/booking/' . $booking->id,
                        ...$metadata,
                    ],
                    'is_read' => false,
                ],
            );
        }
    }

    /** Notify only the author and approved members of the booking's meetup post. */
    public function notifyMatchmakingBookingChanged(Booking $booking, string $eventKey, string $title, string $body, array $metadata = []): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $post = PlayerPost::query()
            ->with(['booking.venueCluster', 'booking.venueCourt', 'booking.items.venueCourt'])
            ->where('booking_id', $booking->id)
            ->first();
        if (! $post) {
            return;
        }

        $approvedUserIds = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('status', 'approved')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $recipients = array_values(array_unique([(int) $post->author_id, ...$approvedUserIds]));
        $payload = [
            'event_key' => $eventKey,
            'player_post_id' => $post->id,
            'player_post_title' => $post->title,
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            ...$metadata,
        ];

        foreach ($recipients as $userId) {
            $exists = Notification::query()
                ->where('user_id', $userId)
                ->where('type', 'matchmaking_booking_changed')
                ->where('reference_type', 'player_post')
                ->where('reference_id', (string) $post->id)
                ->where('data->event_key', $eventKey)
                ->exists();
            if ($exists) {
                continue;
            }

            Notification::query()->create([
                'user_id' => $userId,
                'type' => 'matchmaking_booking_changed',
                'title' => $title,
                'body' => $body,
                'reference_type' => 'player_post',
                'reference_id' => (string) $post->id,
                'data' => $payload,
                'is_read' => false,
            ]);
        }

        try {
            broadcast(new MatchmakingUpdated((int) $post->id, 'booking_changed', $payload));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
