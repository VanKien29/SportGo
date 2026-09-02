<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Services\VenueStaffAccessService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCourtChangeService
{
    private const ALLOWED_STATUSES = [
        'pending_approval',
        'pending_payment',
        'confirmed',
    ];

    public function __construct(
        private readonly BookingService $bookings,
        private readonly VenueStaffAccessService $venueStaffAccess,
    ) {}

    /**
     * Return only active, accessible and currently available courts of the
     * same type as the booking's current court.
     */
    public function availableCourts(Booking $booking, User $actor): Collection
    {
        $booking->loadMissing(['venueCourt', 'items.venueCourt']);
        $this->assertChangeable($booking);

        if ($booking->items->count() > 1) {
            $this->throwMultiItemError();
        }

        $currentCourt = $this->currentCourt($booking);
        if (! $currentCourt) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Booking chưa có sân hợp lệ để đổi.',
            ]);
        }

        return VenueCourt::query()
            ->with('courtType')
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->where('court_type_id', $currentCourt->court_type_id)
            ->whereKeyNot($currentCourt->id)
            ->orderBy('name')
            ->get()
            ->filter(function (VenueCourt $court) use ($booking, $actor): bool {
                $this->venueStaffAccess->assertCourtAccess($actor, $court);

                return $this->bookings->checkAvailability(
                    (string) $court->id,
                    $booking->booking_date->toDateString(),
                    $this->bookingStartTime($booking),
                    $this->bookingEndTime($booking),
                    (string) $booking->id,
                );
            })
            ->values();
    }

    public function change(Booking $booking, int $newCourtId, User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $newCourtId, $actor, $reason): Booking {
            $lockedBooking = Booking::query()
                ->with(['venueCourt.courtType', 'items.venueCourt.courtType', 'venueCluster'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertChangeable($lockedBooking);
            if ($lockedBooking->items->count() > 1) {
                $this->throwMultiItemError();
            }

            $oldCourt = $this->currentCourt($lockedBooking);
            if (! $oldCourt) {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Booking chưa có sân hợp lệ để đổi.',
                ]);
            }

            $newCourt = VenueCourt::query()
                ->with('courtType')
                ->whereKey($newCourtId)
                ->where('venue_cluster_id', $lockedBooking->venue_cluster_id)
                ->where('status', 'active')
                ->where('court_type_id', $oldCourt->court_type_id)
                ->lockForUpdate()
                ->first();

            if (! $newCourt) {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Sân mới phải đang hoạt động, cùng cụm và cùng loại với sân hiện tại.',
                ]);
            }

            if ((int) $newCourt->id === (int) $oldCourt->id) {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Vui lòng chọn sân khác sân hiện tại.',
                ]);
            }

            $this->venueStaffAccess->assertCourtAccess($actor, $newCourt);

            if (! $this->bookings->checkAvailability(
                (string) $newCourt->id,
                $lockedBooking->booking_date->toDateString(),
                $this->bookingStartTime($lockedBooking),
                $this->bookingEndTime($lockedBooking),
                (string) $lockedBooking->id,
            )) {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Sân mới không còn trống hoặc đang bị khóa trong khung giờ này.',
                ]);
            }

            $now = Carbon::now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
            $lockedBooking->forceFill([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $actor->id,
                'court_changed_at' => $now,
                'court_changed_reason' => trim($reason),
            ])->save();

            $item = $lockedBooking->items->first();
            if ($item) {
                $item->forceFill([
                    'venue_court_id' => $newCourt->id,
                    'court_changed_by' => $actor->id,
                    'court_changed_at' => $now,
                    'court_changed_reason' => trim($reason),
                ])->save();
            }

            // A pending booking may still own an auto lock. Move its court
            // lock together with the booking so the old slot is released.
            SlotLock::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('lock_scope', 'court')
                ->where('venue_court_id', $oldCourt->id)
                ->update(['venue_court_id' => $newCourt->id]);

            return $lockedBooking->fresh([
                'venueCluster',
                'venueCourt.courtType',
                'requestedVenueCourt',
                'items.venueCourt.courtType',
                'payments',
                'slotLocks',
            ]);
        });
    }

    private function assertChangeable(Booking $booking): void
    {
        if (! in_array($booking->status, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Booking này không còn được phép đổi sân.',
            ]);
        }

        $start = $booking->booking_date && $this->bookingStartTime($booking)
            ? $this->businessDateTime($booking->booking_date->toDateString(), $this->bookingStartTime($booking))
            : null;

        if ($start && Carbon::now($this->businessTimezone())->gte($start)) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Không thể đổi sân sau khi booking đã bắt đầu.',
            ]);
        }
    }

    private function currentCourt(Booking $booking): ?VenueCourt
    {
        return $booking->items->first()?->venueCourt ?: $booking->venueCourt;
    }

    private function bookingStartTime(Booking $booking): string
    {
        return (string) ($booking->items->first()?->start_time ?: $booking->start_time);
    }

    private function bookingEndTime(Booking $booking): string
    {
        return (string) ($booking->items->first()?->end_time ?: $booking->end_time);
    }

    private function throwMultiItemError(): never
    {
        throw ValidationException::withMessages([
            'venue_court_id' => 'Booking có nhiều khung giờ hoặc nhiều sân. Vui lòng xử lý đổi từng item riêng để không làm lệch lịch booking.',
        ]);
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
    }

    private function businessDateTime(string $date, string $time): Carbon
    {
        $normalized = substr($time, 0, 8);
        if ($normalized === '24:00:00') {
            return Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $this->businessTimezone())->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$normalized, $this->businessTimezone());
    }
}
