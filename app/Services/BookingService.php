<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use App\Models\VenueCluster;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    private const BLOCKING_BOOKING_STATUSES = ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed'];

    /**
     * Kiểm tra xem sân con có trống trong khung giờ yêu cầu không.
     */
    public function checkAvailability(string $venueCourtId, string $bookingDate, string $startTime, string $endTime): bool
    {
        $court = VenueCourt::findOrFail($venueCourtId);
        $venueClusterId = $court->venue_cluster_id;

        // 1. Kiểm tra xem có booking nào đã được xác nhận hoặc đang chờ duyệt/chờ thanh toán trùng giờ không
        $hasOverlapBooking = Booking::where('venue_court_id', $venueCourtId)
            ->where('booking_date', $bookingDate)
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasOverlapBooking) {
            return false;
        }

        // 2. Kiểm tra các slot lock (khoá tạm thời) còn hiệu lực tại sân con này
        $hasOverlapCourtLock = SlotLock::where('venue_court_id', $venueCourtId)
            ->where('booking_date', $bookingDate)
            ->where('expires_at', '>', Carbon::now())
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasOverlapCourtLock) {
            return false;
        }

        // 3. Kiểm tra các slot lock còn hiệu lực ở cấp độ cả cụm sân (lock_scope = 'cluster')
        $hasOverlapClusterLock = SlotLock::where('venue_cluster_id', $venueClusterId)
            ->where('lock_scope', 'cluster')
            ->where('booking_date', $bookingDate)
            ->where('expires_at', '>', Carbon::now())
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasOverlapClusterLock) {
            return false;
        }

        return true;
    }

    /**
     * Tạo đơn đặt sân mới.
     */
    public function createBooking(array $data, string $customerId): Booking
    {
        return DB::transaction(function () use ($data, $customerId) {
            $venueCourtId = $data['venue_court_id'];
            $bookingDate = $data['booking_date'];
            $startTime = $data['start_time'];
            $endTime = $data['end_time'];
            $paymentOption = $data['payment_option'];

            $court = VenueCourt::query()->whereKey($venueCourtId)->lockForUpdate()->firstOrFail();
            $venueClusterId = $court->venue_cluster_id;

            if ($court->status !== 'active') {
                throw new Exception('Sân này hiện không hoạt động.');
            }

            // 1. Kiểm tra tính trống của sân
            if (!$this->checkAvailability($venueCourtId, $bookingDate, $startTime, $endTime)) {
                throw new Exception('Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.');
            }

            // 2. Tính toán thời lượng đặt sân (phút)
            $startMinutes = $this->timeToMinutes($startTime);
            $endMinutes = $this->timeToMinutes($endTime);
            if ($endMinutes <= $startMinutes) {
                throw new Exception('Giờ kết thúc phải lớn hơn giờ bắt đầu.');
            }
            $durationMinutes = $endMinutes - $startMinutes;

            // 3. Kiểm tra cấu hình thời lượng từ BookingConfig
            $config = BookingConfig::find($venueClusterId);
            $minDuration = $config ? $config->min_duration_minutes : 30;
            $maxDuration = $config ? $config->max_duration_minutes : null;

            if ($durationMinutes < $minDuration) {
                throw new Exception("Thời lượng đặt tối thiểu là {$minDuration} phút.");
            }
            if ($maxDuration && $durationMinutes > $maxDuration) {
                throw new Exception("Thời lượng đặt tối đa là {$maxDuration} phút.");
            }

            // 4. Validate tùy chọn thanh toán
            $allowFull = $config ? $config->allow_full_payment : true;
            $allowDeposit = $config ? $config->allow_deposit : true;
            $allowNoPrepay = $config ? $config->allow_no_prepay : true;

            if ($paymentOption === 'full_payment' && !$allowFull) {
                throw new Exception('Hình thức thanh toán hết không được cụm sân này hỗ trợ.');
            }
            if ($paymentOption === 'deposit' && !$allowDeposit) {
                throw new Exception('Hình thức đặt cọc không được cụm sân này hỗ trợ.');
            }
            if ($paymentOption === 'no_prepay' && !$allowNoPrepay) {
                throw new Exception('Hình thức không trả trước không được cụm sân này hỗ trợ.');
            }

            // 5. Tính giá tiền đặt sân theo từng ô 30 phút để đúng khi booking đi qua nhiều khung giá.
            $totalPrice = $this->calculateTotalPrice($court, $bookingDate, $startTime, $endTime, 'single');

            // 6. Tính số tiền tối thiểu cần thanh toán
            $requiredPaymentAmount = 0.00;
            if ($paymentOption === 'full_payment') {
                $requiredPaymentAmount = $totalPrice;
            } elseif ($paymentOption === 'deposit') {
                $depositPercent = $config ? $config->deposit_percent : 30.00;
                $requiredPaymentAmount = $totalPrice * ($depositPercent / 100);
            }

            // 7. Xác định trạng thái ban đầu
            $status = 'pending_payment';
            if ($paymentOption === 'no_prepay') {
                $status = 'pending_approval'; // Mặc định chờ chủ sân duyệt ở Sprint 2
            }

            // 8. Tạo bản ghi Booking
            $bookingCode = 'BK' . strtoupper(Str::random(8));
            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'customer_id' => $customerId,
                'venue_court_id' => $venueCourtId,
                'requested_venue_court_id' => $venueCourtId,
                'venue_cluster_id' => $venueClusterId,
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'total_price' => $totalPrice,
                'payment_option' => $paymentOption,
                'required_payment_amount' => $requiredPaymentAmount,
                'source' => 'online',
                'booking_type' => 'single',
                'status' => $status,
                'created_by' => $customerId,
            ]);

            // 9. Nếu cần thanh toán trước, tự động giữ slot 20 phút
            if ($status === 'pending_payment') {
                $slotHoldMinutes = $config ? $config->slot_hold_minutes : 20;
                SlotLock::create([
                    'venue_cluster_id' => $venueClusterId,
                    'venue_court_id' => $venueCourtId,
                    'lock_scope' => 'court',
                    'booking_date' => $bookingDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'locked_by' => $customerId,
                    'booking_id' => $booking->id,
                    'lock_type' => 'auto',
                    'expires_at' => Carbon::now()->addMinutes($slotHoldMinutes),
                ]);
            }

            return $booking;
        });
    }

    public function getAvailabilitySchedule(string $venueClusterId, string $bookingDate, ?int $courtTypeId = null, string $bookingType = 'single'): array
    {
        $cluster = VenueCluster::query()->whereKey($venueClusterId)->where('status', 'active')->firstOrFail();

        $courtsQuery = VenueCourt::query()
            ->with('courtType:id,name')
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($courtTypeId) {
            $courtsQuery->where('court_type_id', $courtTypeId);
        }

        $courts = $courtsQuery->get(['id', 'venue_cluster_id', 'court_type_id', 'name', 'status', 'sort_order']);
        $courtIds = $courts->pluck('id');
        $timeSlots = $this->buildTimeSlots();
        $busyIntervals = $this->busyIntervals($cluster->id, $courtIds, $bookingDate);
        $slotStatuses = [];

        foreach ($courts as $court) {
            foreach ($timeSlots as $slot) {
                $isAvailable = ! $this->intervalsOverlapSlot($busyIntervals, $court->id, $slot['start_time'], $slot['end_time']);
                $price = $this->resolveHourlyRate($cluster->id, $court->court_type_id, $bookingDate, $slot['start_time'], $slot['end_time'], $bookingType);

                $slotStatuses[] = [
                    'venue_court_id' => $court->id,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_available' => $isAvailable,
                    'hourly_rate' => $price['hourly_rate'],
                    'price' => round($price['hourly_rate'] / 2, 2),
                    'price_source' => $price['source'],
                ];
            }
        }

        $courtTypes = $courts
            ->pluck('courtType')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
            ]);

        return [
            'time_slots' => $timeSlots,
            'courts' => $courts,
            'court_types' => $courtTypes,
            'busy_intervals' => $busyIntervals->values(),
            'slot_statuses' => $slotStatuses,
        ];
    }

    public function calculateTotalPrice(VenueCourt $court, string $bookingDate, string $startTime, string $endTime, string $bookingType = 'single'): float
    {
        $total = 0.0;

        for ($minutes = $this->timeToMinutes($startTime); $minutes < $this->timeToMinutes($endTime); $minutes += 30) {
            $slotStart = $this->minutesToTime($minutes);
            $slotEnd = $this->minutesToTime(min($minutes + 30, $this->timeToMinutes($endTime)));
            $durationHours = ($this->timeToMinutes($slotEnd) - $this->timeToMinutes($slotStart)) / 60;
            $rate = $this->resolveHourlyRate($court->venue_cluster_id, $court->court_type_id, $bookingDate, $slotStart, $slotEnd, $bookingType)['hourly_rate'];
            $total += $rate * $durationHours;
        }

        return round($total, 2);
    }

    public function resolveHourlyRate(string $venueClusterId, int $courtTypeId, string $bookingDate, string $startTime, string $endTime, string $bookingType = 'single'): array
    {
        $holidayPrice = HolidayPrice::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->where('court_type_id', $courtTypeId)
            ->where('holiday_date', $bookingDate)
            ->whereIn('booking_type', ['all', $bookingType])
            ->where('is_active', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->orderByRaw("CASE WHEN booking_type = ? THEN 0 ELSE 1 END", [$bookingType])
            ->first();

        if ($holidayPrice) {
            return [
                'hourly_rate' => (float) $holidayPrice->price,
                'source' => 'holiday_price',
            ];
        }

        $dayOfWeek = Carbon::parse($bookingDate)->dayOfWeekIso;
        $legacySunday = $dayOfWeek === 7 ? 0 : $dayOfWeek;
        $priceSlot = PriceSlot::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->where('court_type_id', $courtTypeId)
            ->whereIn('booking_type', ['all', $bookingType])
            ->where('is_active', true)
            ->where(function ($query) use ($dayOfWeek, $legacySunday) {
                $query->whereJsonContains('apply_to_days', $dayOfWeek)
                    ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek)
                    ->orWhereJsonContains('apply_to_days', $legacySunday)
                    ->orWhereJsonContains('apply_to_days', (string) $legacySunday);
            })
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->orderByRaw("CASE WHEN booking_type = ? THEN 0 ELSE 1 END", [$bookingType])
            ->first();

        return [
            'hourly_rate' => (float) ($priceSlot?->price ?? 100000.00),
            'source' => $priceSlot ? 'price_slot' : 'default',
        ];
    }

    private function busyIntervals(string $venueClusterId, Collection $courtIds, string $bookingDate): Collection
    {
        $bookings = Booking::query()
            ->whereIn('venue_court_id', $courtIds)
            ->where('booking_date', $bookingDate)
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->get(['id', 'venue_court_id', 'start_time', 'end_time', 'status'])
            ->map(fn (Booking $booking) => [
                'venue_court_id' => $booking->venue_court_id,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'source' => 'booking',
                'status' => $booking->status,
            ]);

        $slotLocks = SlotLock::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->where('booking_date', $bookingDate)
            ->where('expires_at', '>', Carbon::now())
            ->where(function ($query) use ($courtIds) {
                $query->where('lock_scope', 'cluster')
                    ->orWhereIn('venue_court_id', $courtIds);
            })
            ->get(['venue_court_id', 'lock_scope', 'start_time', 'end_time', 'lock_type'])
            ->flatMap(function (SlotLock $lock) use ($courtIds) {
                $targetCourtIds = $lock->lock_scope === 'cluster' ? $courtIds : collect([$lock->venue_court_id]);

                return $targetCourtIds->map(fn ($courtId) => [
                    'venue_court_id' => $courtId,
                    'start_time' => $lock->start_time,
                    'end_time' => $lock->end_time,
                    'source' => 'slot_lock',
                    'status' => $lock->lock_type,
                ]);
            });

        return $bookings->merge($slotLocks)->values();
    }

    private function intervalsOverlapSlot(Collection $intervals, string $venueCourtId, string $startTime, string $endTime): bool
    {
        $slotStart = $this->timeToMinutes($startTime);
        $slotEnd = $this->timeToMinutes($endTime);

        return $intervals->contains(function (array $interval) use ($venueCourtId, $slotStart, $slotEnd) {
            return $interval['venue_court_id'] === $venueCourtId
                && $this->timeToMinutes($interval['start_time']) < $slotEnd
                && $this->timeToMinutes($interval['end_time']) > $slotStart;
        });
    }

    private function buildTimeSlots(): array
    {
        $slots = [];

        for ($minutes = 0; $minutes < 1440; $minutes += 30) {
            $slots[] = [
                'start_time' => $this->minutesToTime($minutes),
                'end_time' => $this->minutesToTime($minutes + 30),
                'label' => substr($this->minutesToTime($minutes), 0, 5),
            ];
        }

        return $slots;
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function minutesToTime(int $minutes): string
    {
        if ($minutes >= 1440) {
            return '24:00:00';
        }

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
