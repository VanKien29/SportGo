<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\BookingItem;
use App\Models\HolidayPrice;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\VenueBasePrice;
use App\Models\VenueCluster;
use App\Services\Customers\WalkInCustomerService;
use App\Services\Memberships\SystemVipService;
use App\Services\Memberships\VenueMembershipService;
use App\Services\Wallets\SystemWalletService;
use App\Models\VenueCourt;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private const BLOCKING_BOOKING_STATUSES = ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed'];

    public function __construct(
        private readonly WalkInCustomerService $walkInCustomers,
        private readonly SystemWalletService $systemWallets,
        private readonly VenueMembershipService $venueMemberships,
        private readonly SystemVipService $systemVip,
    ) {}

    /**
     * Kiểm tra xem sân con có trống trong khung giờ yêu cầu không.
     */
    public function checkAvailability(string $venueCourtId, string $bookingDate, string $startTime, string $endTime, ?string $ignoreBookingId = null): bool
    {
        $court = VenueCourt::findOrFail($venueCourtId);
        $venueClusterId = $court->venue_cluster_id;

        if (! $this->isWithinOperatingHours($venueClusterId, $bookingDate, $startTime, $endTime)) {
            return false;
        }

        // 1. Kiểm tra xem có booking nào đã được xác nhận hoặc đang chờ duyệt/chờ thanh toán trùng giờ không
        $hasOverlapBooking = Booking::query()
            ->where('booking_date', $bookingDate)
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->where(function ($query) use ($venueCourtId, $startTime, $endTime) {
                $query->whereHas('items', function ($itemQuery) use ($venueCourtId, $startTime, $endTime) {
                    $itemQuery->where('venue_court_id', $venueCourtId)
                        ->where(fn ($activeItemQuery) => $this->activeBookingItemConstraint($activeItemQuery))
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })->orWhere(function ($fallbackQuery) use ($venueCourtId, $startTime, $endTime) {
                    $fallbackQuery->doesntHave('items')
                        ->where('venue_court_id', $venueCourtId)
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($hasOverlapBooking) {
            return false;
        }

        // 2. Kiểm tra các slot lock (khoá tạm thời) còn hiệu lực tại sân con này
        $hasOverlapCourtLock = SlotLock::where('venue_court_id', $venueCourtId)
            ->where('booking_date', $bookingDate)
            ->where(fn ($query) => $this->activeSlotLockConstraint($query))
            ->when($ignoreBookingId, fn ($query) => $query->where(function ($lockQuery) use ($ignoreBookingId) {
                $lockQuery->whereNull('booking_id')->orWhere('booking_id', '!=', $ignoreBookingId);
            }))
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
            ->where(fn ($query) => $this->activeSlotLockConstraint($query))
            ->when($ignoreBookingId, fn ($query) => $query->where(function ($lockQuery) use ($ignoreBookingId) {
                $lockQuery->whereNull('booking_id')->orWhere('booking_id', '!=', $ignoreBookingId);
            }))
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

            $this->assertWithinOperatingHours($venueClusterId, $bookingDate, $startTime, $endTime);
            $this->assertMinimumAdvanceNotice($venueClusterId, $bookingDate, $startTime);

            // 1. Kiểm tra tính trống của sân
            if (! $this->checkAvailability($venueCourtId, $bookingDate, $startTime, $endTime)) {
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
            $config = $this->bookingConfigForCluster($venueClusterId);
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

            if ($paymentOption === 'full_payment' && ! $allowFull) {
                throw new Exception('Hình thức thanh toán hết không được cụm sân này hỗ trợ.');
            }
            if ($paymentOption === 'deposit' && ! $allowDeposit) {
                throw new Exception('Hình thức đặt cọc không được cụm sân này hỗ trợ.');
            }
            if ($paymentOption === 'no_prepay' && ! $allowNoPrepay) {
                throw new Exception('Hình thức không trả trước không được cụm sân này hỗ trợ.');
            }

            // 5. Tính giá tiền đặt sân theo từng ô 30 phút để đúng khi booking đi qua nhiều khung giá.
            $originalAmount = $this->calculateTotalPrice($court, $bookingDate, $startTime, $endTime, 'single');
            $membership = $this->venueMemberships->discountForBooking($customerId, $venueClusterId, $originalAmount);
            $membershipDiscountAmount = (float) $membership['discount_amount'];
            $amountAfterMembership = round(max($originalAmount - $membershipDiscountAmount, 0), 2);
            $vouchers = $this->resolveVouchersForBooking(
                $data,
                $customerId,
                $venueClusterId,
                (string) $court->court_type_id,
                'single',
                $amountAfterMembership,
            );
            $venueVoucher = $vouchers['venue'];
            $vipVoucher = $vouchers['vip'];
            $venueVoucherDiscountAmount = (float) ($venueVoucher['discount_amount'] ?? 0);
            $vipVoucherDiscountAmount = (float) ($vipVoucher['discount_amount'] ?? 0);
            $voucherDiscountAmount = round($venueVoucherDiscountAmount + $vipVoucherDiscountAmount, 2);
            $discountAmount = round($membershipDiscountAmount + $voucherDiscountAmount, 2);
            $totalPrice = round(max($amountAfterMembership - $voucherDiscountAmount, 0), 2);

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
            $bookingCode = 'BK'.strtoupper(Str::random(8));
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
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'membership_tier_discount_amount' => $membershipDiscountAmount,
                'membership_tier' => $membership['tier'] ?? 'standard',
                'system_discount_amount' => $vipVoucherDiscountAmount,
                'venue_discount_amount' => $venueVoucherDiscountAmount,
                'final_amount' => $totalPrice,
                'voucher_id' => $venueVoucher['id'] ?? $vipVoucher['id'] ?? null,
                'voucher_code_snapshot' => $venueVoucher['code'] ?? $vipVoucher['code'] ?? null,
                'venue_voucher_id' => $venueVoucher['id'] ?? null,
                'venue_voucher_code_snapshot' => $venueVoucher['code'] ?? null,
                'vip_voucher_id' => $vipVoucher['id'] ?? null,
                'vip_voucher_code_snapshot' => $vipVoucher['code'] ?? null,
                'payment_option' => $paymentOption,
                'required_payment_amount' => $requiredPaymentAmount,
                'source' => 'online',
                'booking_type' => 'single',
                'status' => $status,
                'created_by' => $customerId,
            ]);

            foreach (array_filter([$venueVoucher, $vipVoucher]) as $voucher) {
                $this->recordVoucherUsage($voucher, $booking, $customerId);
            }

            // 9. Nếu cần thanh toán trước, tự động giữ slot theo cấu hình cụm sân.
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

    public function createCounterBooking(array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($data, $actor): Booking {
            $court = $this->lockActiveCourt($data['venue_court_id']);
            $timeRanges = $this->normalizeTimeRanges($data, $court->id);
            $rangeCourts = $this->courtsForTimeRanges($timeRanges, $court);
            $this->validateTimeRanges($timeRanges);
            $this->ensureRangesAreNotInPast($data['booking_date'], $timeRanges, 'start_time');
            $this->validateRangeDurationsAndPayment($court->venue_cluster_id, $timeRanges, $data['payment_option']);

            foreach ($timeRanges as $range) {
                $this->assertWithinOperatingHours(
                    $court->venue_cluster_id,
                    $data['booking_date'],
                    $range['start_time'],
                    $range['end_time'],
                );

                if (! $this->checkAvailability($range['venue_court_id'], $data['booking_date'], $range['start_time'], $range['end_time'])) {
                    $errorKey = isset($data['time_ranges']) ? 'time_ranges' : 'start_time';

                    throw ValidationException::withMessages([
                        $errorKey => 'Một hoặc nhiều khung giờ đã có booking hoặc đang được giữ chỗ.',
                    ]);
                }
            }

            $booking = $this->createOperationalBooking(
                $court,
                array_merge($data, [
                    'start_time' => $timeRanges[0]['start_time'],
                    'end_time' => $timeRanges[array_key_last($timeRanges)]['end_time'],
                    'time_ranges' => $timeRanges,
                    'range_courts' => $rangeCourts,
                ]),
                $actor,
                $data['booking_date'],
                'single',
            );

            return $booking->fresh(['venueCourt.courtType', 'customer', 'payments.logs']);
        });
    }

    public function createRecurringBookings(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $court = $this->lockActiveCourt($data['venue_court_id']);
            if (($data['payment_option'] ?? null) === 'deposit') {
                throw ValidationException::withMessages([
                    'payment_option' => 'Lịch cố định chỉ hỗ trợ thanh toán đủ hoặc trả sau.',
                ]);
            }

            $dates = $this->recurringDates($data);
            $this->validateRecurringDates($dates);
            $rangesByDate = $this->recurringRangesByDate($data, $dates, $court);

            $rangesByDate->each(function (array $timeRanges, string $dateString) use ($court, $data): void {
                $this->courtsForTimeRanges($timeRanges, $court);
                $this->validateTimeRanges($timeRanges);
                $this->validateRangeDurationsAndPayment($court->venue_cluster_id, $timeRanges, $data['payment_option']);
                $this->ensureRangesAreNotInPast($dateString, $timeRanges, 'recurring_start_date');
            });

            $conflicts = $this->recurringConflictPayloadForDateRanges($rangesByDate);
            $resolution = $data['conflict_resolution'] ?? 'abort';
            $overrides = collect($data['conflict_overrides'] ?? [])->keyBy('date');
            $switchedCourtsByDate = collect();
            $skippedDates = collect();

            if ($conflicts->isNotEmpty() && $resolution === 'abort') {
                throw ValidationException::withMessages([
                    'recurring_start_date' => 'Một số buổi trong lịch cố định đã bị trùng: '.$conflicts->pluck('date')->take(8)->implode(', ').($conflicts->count() > 8 ? '...' : ''),
                ]);
            }

            foreach ($conflicts as $conflict) {
                if ($resolution === 'skip') {
                    $skippedDates->push($conflict['date']);
                    continue;
                }

                $override = $overrides->get($conflict['date']);

                if ($resolution !== 'mixed' || ! $override) {
                    throw ValidationException::withMessages([
                        'conflict_resolution' => 'Vui lòng chọn cách xử lý cho từng ngày bị trùng lịch.',
                    ]);
                }

                if (($override['action'] ?? null) === 'skip') {
                    $skippedDates->push($conflict['date']);
                    continue;
                }

                if (($override['action'] ?? null) !== 'switch' || empty($override['venue_court_id'])) {
                    throw ValidationException::withMessages([
                        'conflict_overrides' => 'Ngày '.$conflict['date'].' chưa có sân thay thế hợp lệ.',
                    ]);
                }

                $candidate = collect($conflict['alternatives'])->firstWhere('id', $override['venue_court_id']);

                if (! $candidate) {
                    throw ValidationException::withMessages([
                        'conflict_overrides' => 'Sân thay thế cho ngày '.$conflict['date'].' không còn trống.',
                    ]);
                }

                $switchedCourtsByDate->put($conflict['date'], $override['venue_court_id']);
            }

            $dates->each(function (Carbon $date) use ($court, $rangesByDate): void {
                foreach ($rangesByDate->get($date->toDateString(), []) as $range) {
                    $this->assertWithinOperatingHours(
                        $court->venue_cluster_id,
                        $date->toDateString(),
                        $range['start_time'],
                        $range['end_time'],
                    );
                }
            });

            $conflicts = $dates
                ->filter(function (Carbon $date) use ($rangesByDate): bool {
                    foreach ($rangesByDate->get($date->toDateString(), []) as $range) {
                        if (! $this->checkAvailability($range['venue_court_id'], $date->toDateString(), $range['start_time'], $range['end_time'])) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values()
                ->map(fn (Carbon $date) => $date->toDateString());

            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'recurring_start_date' => 'Một số buổi trong lịch cố định vẫn bị trùng: '.$conflicts->take(8)->implode(', ').($conflicts->count() > 8 ? '...' : ''),
                ]);
            }

            $dates = $dates
                ->reject(fn (Carbon $date) => $skippedDates->contains($date->toDateString()))
                ->values();

            if ($dates->isEmpty()) {
                throw ValidationException::withMessages([
                    'recurring_start_date' => 'Tất cả buổi trong lịch cố định đã bị bỏ qua. Vui lòng chọn lại khoảng ngày hoặc sân.',
                ]);
            }

            $groupCode = $this->uniqueRecurringGroupCode();
            $bookings = collect();

            foreach ($dates as $date) {
                $dateString = $date->toDateString();
                $dateCourt = $switchedCourtsByDate->has($dateString)
                    ? $this->lockActiveCourt($switchedCourtsByDate->get($dateString))
                    : $court;
                $baseDateTimeRanges = $rangesByDate->get($dateString, []);
                $dateTimeRanges = $switchedCourtsByDate->has($dateString)
                    ? collect($baseDateTimeRanges)
                        ->map(fn (array $range): array => [
                            ...$range,
                            'venue_court_id' => $dateCourt->id,
                        ])
                        ->values()
                        ->all()
                    : $baseDateTimeRanges;
                $dateRangeCourts = $switchedCourtsByDate->has($dateString)
                    ? collect([$dateCourt->id => $dateCourt])
                    : $this->courtsForTimeRanges($dateTimeRanges, $court);

                foreach ($dateTimeRanges as $range) {
                    if (! $this->checkAvailability($range['venue_court_id'], $dateString, $range['start_time'], $range['end_time'])) {
                        throw ValidationException::withMessages([
                            'recurring_start_date' => 'Khung '.$dateString.' vừa có booking khác. Vui lòng tải lại lịch và thử lại.',
                        ]);
                    }
                }

                $bookings->push($this->createOperationalBooking(
                    $dateCourt,
                    array_merge($data, [
                        'start_time' => $dateTimeRanges[0]['start_time'],
                        'end_time' => $dateTimeRanges[array_key_last($dateTimeRanges)]['end_time'],
                        'time_ranges' => $dateTimeRanges,
                        'range_courts' => $dateRangeCourts,
                        'recurring_group_code' => $groupCode,
                        'recurring_start_date' => $data['recurring_start_date'],
                        'recurring_end_date' => $data['recurring_end_date'],
                        'recurrence_type' => $data['recurrence_type'],
                        'recurrence_interval' => $data['recurrence_interval'],
                        'recurrence_days_of_week' => $data['recurrence_type'] === 'weekly' ? ($data['recurrence_days_of_week'] ?? []) : null,
                        'recurrence_days_of_month' => $data['recurrence_type'] === 'monthly' ? ($data['recurrence_days_of_month'] ?? []) : null,
                    ]),
                    $actor,
                    $dateString,
                    'recurring',
                ));
            }

            $loadedBookings = Booking::query()
                ->with(['venueCourt.courtType', 'customer', 'payments'])
                ->whereIn('id', $bookings->pluck('id'))
                ->orderBy('booking_date')
                ->orderBy('start_time')
                ->get();

            return [
                'recurring_group_code' => $groupCode,
                'created_count' => $loadedBookings->count(),
                'skipped_count' => $skippedDates->count(),
                'switched_count' => $switchedCourtsByDate->count(),
                'total_price' => round($loadedBookings->sum(fn (Booking $booking) => (float) $booking->total_price), 2),
                'original_amount' => round($loadedBookings->sum(fn (Booking $booking) => (float) ($booking->original_amount ?? $booking->total_price)), 2),
                'discount_amount' => round($loadedBookings->sum(fn (Booking $booking) => (float) $booking->discount_amount), 2),
                'required_payment_amount' => round($loadedBookings->sum(fn (Booking $booking) => (float) $booking->required_payment_amount), 2),
                'bookings' => $loadedBookings->values(),
            ];
        });
    }

    public function eligibleVouchersForCounterBooking(array $data, User $actor): Collection
    {
        $court = VenueCourt::query()
            ->with('courtType')
            ->whereKey($data['venue_court_id'])
            ->firstOrFail();

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return collect();
        }

        $bookingType = $data['booking_type'] ?? 'single';
        $usageUserId = $data['customer_id'] ?? $actor->id;
        $usageCount = max((int) ($data['usage_count'] ?? 1), 1);
        $membership = $this->venueMemberships->discountForBooking($usageUserId, $court->venue_cluster_id, $amount);
        $amount = round(max($amount - (float) $membership['discount_amount'], 0), 2);

        return $this->activeVoucherQuery($court->venue_cluster_id, $data['voucher_code'] ?? null)
            ->get()
            ->map(fn (object $voucher): ?array => $this->voucherPreviewPayload(
                $voucher,
                $usageUserId,
                $court->venue_cluster_id,
                (string) $court->court_type_id,
                $bookingType,
                $amount,
                $usageCount,
            ))
            ->filter()
            ->sortByDesc('discount_amount')
            ->values();
    }

    public function previewRecurringConflicts(array $data): array
    {
        $court = VenueCourt::query()
            ->with(['venueCluster', 'courtType'])
            ->findOrFail($data['venue_court_id']);

        if ($court->status !== 'active') {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Sân này hiện không hoạt động.',
            ]);
        }

        if ($court->venueCluster?->status === 'locked') {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        $dates = $this->recurringDates($data);
        $this->validateRecurringDates($dates);
        $rangesByDate = $this->recurringRangesByDate($data, $dates, $court);

        $rangesByDate->each(function (array $timeRanges, string $dateString) use ($court, $data): void {
            $this->courtsForTimeRanges($timeRanges, $court);
            $this->validateTimeRanges($timeRanges);
            $this->validateRangeDurationsAndPayment($court->venue_cluster_id, $timeRanges, $data['payment_option']);
            $this->ensureRangesAreNotInPast($dateString, $timeRanges, 'recurring_start_date');
        });

        $conflicts = $this->recurringConflictPayloadForDateRanges($rangesByDate);

        return [
            'total_dates' => $dates->count(),
            'conflict_count' => $conflicts->count(),
            'dates' => $dates->values()->all(),
            'conflicts' => $conflicts->values()->all(),
        ];
    }

    public function collectCounterPayment(Booking $booking, User $actor, string $method, ?float $amount = null): Booking
    {
        if (! in_array($method, ['cash', 'bank_transfer'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Phương thức thu tại quầy không hợp lệ.',
            ]);
        }

        return DB::transaction(function () use ($booking, $actor, $method, $amount): Booking {
            $booking = Booking::query()
                ->with('payments')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCounterBookingCanCollect($booking);

            $outstandingAmount = $this->outstandingAmount($booking);
            $collectionAmount = round((float) ($amount ?: $outstandingAmount), 2);

            if ($collectionAmount <= 0 || $collectionAmount > $outstandingAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền thu không hợp lệ so với số còn phải thu.',
                ]);
            }

            $this->failPendingSepayPayments($booking, $actor, 'counter_payment_replaced_by_direct');

            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->whereIn('method', ['cash', 'bank_transfer'])
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($payment) {
                $statusBefore = $payment->status;
                $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];

                $payment->update([
                    'amount' => $collectionAmount,
                    'wallet_amount' => 0,
                    'gateway_amount' => $collectionAmount,
                    'payment_kind' => $this->paymentKindForCollection($booking, $collectionAmount),
                    'method' => $method,
                    'gateway_response' => array_merge($gatewayResponse, [
                        'counter_collection' => [
                            'source' => 'owner_counter_collect',
                            'actor_id' => $actor->id,
                            'collected_at' => now()->toIso8601String(),
                        ],
                    ]),
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            } else {
                $statusBefore = null;
                $payment = Payment::query()->create([
                    'payment_code' => $this->uniquePaymentCode(),
                    'booking_id' => $booking->id,
                    'amount' => $collectionAmount,
                    'wallet_amount' => 0,
                    'gateway_amount' => $collectionAmount,
                    'payment_kind' => $this->paymentKindForCollection($booking, $collectionAmount),
                    'method' => $method,
                    'gateway_response' => [
                        'counter_collection' => [
                            'source' => 'owner_counter_collect',
                            'actor_id' => $actor->id,
                            'collected_at' => now()->toIso8601String(),
                        ],
                    ],
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'counter_payment_collected',
                'request_payload' => [
                    'actor_id' => $actor->id,
                    'booking_code' => $booking->booking_code,
                    'method' => $method,
                    'amount' => $collectionAmount,
                ],
                'status_before' => $statusBefore,
                'status_after' => $payment->status,
            ]);

            $this->recordSystemVoucherSubsidyForPayment($payment);

            Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->whereIn('method', ['cash', 'bank_transfer'])
                ->whereKeyNot($payment->id)
                ->get()
                ->each(fn (Payment $pendingPayment) => $this->failPendingPayment($pendingPayment, $actor, 'counter_payment_replaced'));

            if (in_array($booking->status, ['pending_approval', 'pending_payment', 'confirmed', 'checked_in'], true)) {
                $booking->update([
                    'status' => $collectionAmount >= $outstandingAmount ? 'completed' : 'confirmed',
                ]);
                if ($booking->status === 'completed') {
                    $this->syncMembershipForCompletedBooking($booking);
                }
            }

            return $booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer', 'payments']);
        });
    }

    public function syncMembershipForCompletedBooking(Booking $booking): void
    {
        $fresh = $booking->fresh();
        $this->venueMemberships->syncBooking($fresh);
        $this->systemVip->creditCashbackForCompletedBooking($fresh);
    }

    public function collectRecurringGroupPayment(string $groupCode, User $actor, string $method, ?float $amount = null): array
    {
        if (! in_array($method, ['cash', 'bank_transfer'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Phương thức thu nhóm lịch cố định không hợp lệ.',
            ]);
        }

        $bookings = Booking::query()
            ->with('payments')
            ->where('source', 'counter')
            ->where('booking_type', 'recurring')
            ->where('recurring_group_code', $groupCode)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        if ($bookings->isEmpty()) {
            throw ValidationException::withMessages([
                'recurring_group_code' => 'Không tìm thấy nhóm lịch cố định.',
            ]);
        }

        $outstandingAmount = round($bookings->sum(fn (Booking $booking) => $this->outstandingAmount($booking)), 2);
        $collectionAmount = round((float) ($amount ?: $outstandingAmount), 2);

        if ($collectionAmount <= 0 || $collectionAmount > $outstandingAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thu không hợp lệ so với số còn phải thu của nhóm lịch.',
            ]);
        }

        $remaining = $collectionAmount;
        $updatedIds = collect();

        foreach ($bookings as $booking) {
            if ($remaining <= 0) {
                break;
            }

            $bookingOutstanding = round($this->outstandingAmount($booking), 2);

            if ($bookingOutstanding <= 0) {
                continue;
            }

            $amountForBooking = min($bookingOutstanding, $remaining);
            $updated = $this->collectCounterPayment($booking, $actor, $method, $amountForBooking);
            $updatedIds->push($updated->id);
            $remaining = round($remaining - $amountForBooking, 2);
        }

        $loadedBookings = Booking::query()
            ->with(['venueCluster', 'venueCourt.courtType', 'customer', 'payments'])
            ->where('recurring_group_code', $groupCode)
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return [
            'recurring_group_code' => $groupCode,
            'collected_amount' => $collectionAmount,
            'updated_booking_ids' => $updatedIds->values(),
            'bookings' => $loadedBookings,
        ];
    }

    public function outstandingAmount(Booking $booking): float
    {
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->sum('amount');

        return round(max((float) $booking->total_price - $paidAmount, 0), 2);
    }

    public function ensurePendingPaymentLocks(Booking $booking, string $lockedBy): Collection
    {
        if ($booking->status !== 'pending_payment') {
            return collect();
        }

        $existingLocks = SlotLock::query()->where('booking_id', $booking->id)->get();
        if ($existingLocks->isNotEmpty()) {
            return $existingLocks;
        }

        $booking->loadMissing('items');
        $slotHoldMinutes = (int) ($this->bookingConfigForCluster($booking->venue_cluster_id)?->slot_hold_minutes ?? 20);
        $expiresAt = Carbon::now()->addMinutes($slotHoldMinutes);
        $items = $booking->items->isNotEmpty()
            ? $booking->items
            : collect([(object) [
                'id' => null,
                'venue_court_id' => $booking->venue_court_id,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]]);

        return $items->map(fn ($item) => SlotLock::query()->create([
            'venue_cluster_id' => $booking->venue_cluster_id,
            'venue_court_id' => $item->venue_court_id,
            'lock_scope' => 'court',
            'booking_date' => $booking->booking_date,
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'locked_by' => $lockedBy,
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'lock_type' => 'auto',
            'reason' => "Giữ chỗ chờ thanh toán trong {$slotHoldMinutes} phút.",
            'expires_at' => $expiresAt,
        ]));
    }

    public function getAvailabilitySchedule(string $venueClusterId, string $bookingDate, ?int $courtTypeId = null, string $bookingType = 'single', bool $includeBusyDetails = false): array
    {
        $cluster = VenueCluster::query()->whereKey($venueClusterId)->where('status', 'active')->first();

        if (! $cluster) {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân không tồn tại hoặc chưa hoạt động.',
            ]);
        }

        $courtsQuery = VenueCourt::query()
            ->with('courtType:id,name')
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($courtTypeId) {
            $courtsQuery->where('court_type_id', $courtTypeId);
        }

        $courts = $courtsQuery->get(['id', 'venue_cluster_id', 'court_type_id', 'name', 'status', 'sort_order', 'layout_x', 'layout_y', 'layout_w', 'layout_h', 'layout_rotation']);
        $courtIds = $courts->pluck('id');
        $operatingHours = $this->resolveOperatingHours($cluster->id, $bookingDate);
        $timeSlots = $operatingHours['is_open']
            ? $this->buildTimeSlots($operatingHours['open_time'], $operatingHours['close_time'])
            : [];
        $busyIntervals = $this->busyIntervals($cluster->id, $courtIds, $bookingDate, $includeBusyDetails);
        $slotStatuses = [];

        foreach ($courts as $court) {
            foreach ($timeSlots as $slot) {
                $busyInterval = $this->overlappingInterval($busyIntervals, $court->id, $slot['start_time'], $slot['end_time']);
                $isAvailable = $busyInterval === null;
                $price = $this->resolveHourlyRate($cluster->id, $court->court_type_id, $bookingDate, $slot['start_time'], $slot['end_time'], $bookingType);

                $slotStatuses[] = [
                    'venue_court_id' => $court->id,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_available' => $isAvailable,
                    'hourly_rate' => $price['hourly_rate'],
                    'price' => round($price['hourly_rate'] / 2, 2),
                    'price_source' => $price['source'],
                    'busy_source' => $busyInterval['source'] ?? null,
                    'busy_status' => $busyInterval['status'] ?? null,
                    'schedule_lock_id' => $busyInterval['schedule_lock_id'] ?? null,
                    'lock_reason' => $busyInterval['reason'] ?? null,
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
            'operating_hours' => $operatingHours,
        ];
    }

    public function meetsMinimumAdvanceNotice(string $venueClusterId, string $bookingDate, string $startTime): bool
    {
        $minimumMinutes = (int) ($this->bookingConfigForCluster($venueClusterId)?->min_advance_booking_minutes ?? 30);
        $bookingStart = Carbon::parse($bookingDate)
            ->startOfDay()
            ->addMinutes($this->timeToMinutes($startTime));

        return now()->addMinutes($minimumMinutes)->lte($bookingStart);
    }

    public function resolveOperatingHours(string $venueClusterId, string $bookingDate): array
    {
        $config = $this->bookingConfigForCluster($venueClusterId);
        $specialHours = collect($config?->special_operating_hours ?? [])
            ->first(fn (array $hours): bool => $hours['start_date'] <= $bookingDate && $hours['end_date'] >= $bookingDate);

        if ($specialHours) {
            return [
                'is_open' => true,
                'open_time' => $this->normalizeClock($specialHours['open_time']),
                'close_time' => $this->normalizeClock($specialHours['close_time']),
                'source' => 'special',
            ];
        }

        $legacyHours = collect($config?->weekly_operating_hours ?? [])
            ->first(fn (array $hours): bool => (bool) ($hours['is_open'] ?? false));
        $openTime = $config?->fixed_open_time ?: ($legacyHours['open_time'] ?? '08:00');
        $closeTime = $config?->fixed_close_time ?: ($legacyHours['close_time'] ?? '22:00');

        return [
            'is_open' => true,
            'open_time' => $this->normalizeClock($openTime),
            'close_time' => $this->normalizeClock($closeTime),
            'source' => 'fixed',
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

    private function calculateTotalPriceForRanges(Collection $courts, string $bookingDate, array $timeRanges, string $bookingType): float
    {
        return round(collect($timeRanges)->sum(function (array $range) use ($courts, $bookingDate, $bookingType): float {
            return $this->calculateTotalPrice(
                $courts->get($range['venue_court_id']),
                $bookingDate,
                $range['start_time'],
                $range['end_time'],
                $bookingType,
            );
        }), 2);
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
            ->orderByRaw('CASE WHEN booking_type = ? THEN 0 ELSE 1 END', [$bookingType])
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
            ->orderByRaw('CASE WHEN booking_type = ? THEN 0 ELSE 1 END', [$bookingType])
            ->first();

        if ($priceSlot) {
            return [
                'hourly_rate' => (float) $priceSlot->price,
                'source' => 'price_slot',
            ];
        }

        $basePrice = VenueBasePrice::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->where('court_type_id', $courtTypeId)
            ->first();

        return [
            'hourly_rate' => (float) ($basePrice?->price ?? 10000.00),
            'source' => $basePrice ? 'base_price' : 'system_default',
        ];
    }

    private function createOperationalBooking(VenueCourt $court, array $data, User $actor, string $bookingDate, string $bookingType): Booking
    {
        $timeRanges = $this->normalizeTimeRanges($data, $court->id);
        $rangeCourts = $data['range_courts'] ?? $this->courtsForTimeRanges($timeRanges, $court);
        $durationMinutes = $this->rangesDurationMinutes($timeRanges);
        $originalAmount = $this->calculateTotalPriceForRanges($rangeCourts, $bookingDate, $timeRanges, $bookingType);
        $customer = $this->walkInCustomers->resolveOrCreate(
            $data['customer_id'] ?? null,
            $data['walk_in_name'] ?? null,
            $data['walk_in_phone'] ?? null,
        );
        $data['customer_id'] = $customer->id;
        $membership = $this->venueMemberships->discountForBooking($customer->id, $court->venue_cluster_id, $originalAmount);
        $membershipDiscountAmount = (float) $membership['discount_amount'];
        $amountAfterMembership = round(max($originalAmount - $membershipDiscountAmount, 0), 2);
        $vouchers = $this->resolveVouchersForBooking(
            $data,
            $customer->id,
            $court->venue_cluster_id,
            (string) $court->court_type_id,
            $bookingType,
            $amountAfterMembership,
        );
        $venueVoucher = $vouchers['venue'];
        $vipVoucher = $vouchers['vip'];
        $venueVoucherDiscountAmount = (float) ($venueVoucher['discount_amount'] ?? 0);
        $vipVoucherDiscountAmount = (float) ($vipVoucher['discount_amount'] ?? 0);
        $voucherDiscountAmount = round($venueVoucherDiscountAmount + $vipVoucherDiscountAmount, 2);
        $discountAmount = round($membershipDiscountAmount + $voucherDiscountAmount, 2);
        $totalPrice = round(max($amountAfterMembership - $voucherDiscountAmount, 0), 2);
        $requiredPaymentAmount = $this->requiredPaymentAmount($court->venue_cluster_id, $totalPrice, $data['payment_option']);
        $isPaid = $requiredPaymentAmount <= 0 && $data['payment_option'] !== 'no_prepay'
            ? true
            : (bool) ($data['is_paid'] ?? false);
        $status = $this->initialCounterStatus($data['payment_option'], $isPaid);
        $startTime = $timeRanges[0]['start_time'];
        $endTime = $timeRanges[array_key_last($timeRanges)]['end_time'];

        $booking = Booking::query()->create([
            'booking_code' => $this->uniqueBookingCode(),
            'customer_id' => $customer->id,
            'venue_court_id' => $court->id,
            'requested_venue_court_id' => $court->id,
            'venue_cluster_id' => $court->venue_cluster_id,
            'booking_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'total_price' => $totalPrice,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'membership_tier_discount_amount' => $membershipDiscountAmount,
            'membership_tier' => $membership['tier'] ?? 'standard',
            'system_discount_amount' => $vipVoucherDiscountAmount,
            'venue_discount_amount' => $venueVoucherDiscountAmount,
            'final_amount' => $totalPrice,
            'voucher_id' => $venueVoucher['id'] ?? $vipVoucher['id'] ?? null,
            'voucher_code_snapshot' => $venueVoucher['code'] ?? $vipVoucher['code'] ?? null,
            'venue_voucher_id' => $venueVoucher['id'] ?? null,
            'venue_voucher_code_snapshot' => $venueVoucher['code'] ?? null,
            'vip_voucher_id' => $vipVoucher['id'] ?? null,
            'vip_voucher_code_snapshot' => $vipVoucher['code'] ?? null,
            'payment_option' => $data['payment_option'],
            'required_payment_amount' => $requiredPaymentAmount,
            'source' => 'counter',
            'booking_type' => $bookingType,
            'recurring_group_code' => $data['recurring_group_code'] ?? null,
            'recurring_start_date' => $data['recurring_start_date'] ?? null,
            'recurring_end_date' => $data['recurring_end_date'] ?? null,
            'recurrence_type' => $data['recurrence_type'] ?? null,
            'recurrence_interval' => $data['recurrence_interval'] ?? null,
            'recurrence_days_of_week' => $data['recurrence_days_of_week'] ?? null,
            'recurrence_days_of_month' => $data['recurrence_days_of_month'] ?? null,
            'status' => $status,
            'walk_in_name' => $data['walk_in_name'] ?? null,
            'walk_in_phone' => $data['walk_in_phone'] ?? null,
            'created_by' => $actor->id,
        ]);

        $bookingItems = collect();

        foreach ($timeRanges as $index => $range) {
            $rangeCourt = $rangeCourts->get($range['venue_court_id']);
            $rangeDuration = $this->durationMinutes($range['start_time'], $range['end_time']);
            $rangeTotal = $this->calculateTotalPrice($rangeCourt, $bookingDate, $range['start_time'], $range['end_time'], $bookingType);
            $bookingItems->push($this->createBookingItem($booking, $rangeCourt, $range, $rangeDuration, $rangeTotal, $index + 1));
        }

        $this->ensurePendingPaymentLocks($booking->setRelation('items', $bookingItems), $actor->id);

        foreach (array_filter([$venueVoucher, $vipVoucher]) as $voucher) {
            $this->recordVoucherUsage($voucher, $booking, $customer->id);
        }

        $payment = $this->createCounterPayment($booking, $actor, $isPaid, $data['payment_method'] ?? 'cash');

        if ($payment && $payment->status === 'paid') {
            $this->recordSystemVoucherSubsidyForPayment($payment);
        } elseif (
            ! $payment
            && $booking->payment_option !== 'no_prepay'
            && (float) $booking->system_discount_amount > 0
            && (float) $booking->required_payment_amount <= 0
        ) {
            $this->systemWallets->reserveVoucherForBooking((float) $booking->system_discount_amount, $booking->id, null, [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'source' => 'counter_booking_fully_discounted',
                'system_discount_amount' => (float) $booking->system_discount_amount,
            ]);
        }

        if ($booking->status === 'completed') {
            $this->syncMembershipForCompletedBooking($booking);
        }

        return $booking;
    }

    private function createBookingItem(Booking $booking, VenueCourt $court, array $range, int $durationMinutes, float $totalPrice, int $sortOrder): BookingItem
    {
        $durationHours = max($durationMinutes / 60, 0.5);

        return BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $court->id,
            'requested_venue_court_id' => $court->id,
            'start_time' => $range['start_time'],
            'end_time' => $range['end_time'],
            'duration_minutes' => $durationMinutes,
            'unit_price' => round($totalPrice / $durationHours, 2),
            'subtotal' => $totalPrice,
            'status' => 'active',
            'sort_order' => $sortOrder,
        ]);
    }

    private function createCounterPayment(Booking $booking, User $actor, bool $isPaid, string $method): ?Payment
    {
        if ($method === 'sepay' || $booking->payment_option === 'no_prepay' || (float) $booking->required_payment_amount <= 0) {
            return null;
        }

        $payment = Payment::query()->create([
            'payment_code' => $this->uniquePaymentCode(),
            'booking_id' => $booking->id,
            'amount' => $booking->required_payment_amount,
            'wallet_amount' => 0,
            'gateway_amount' => $booking->required_payment_amount,
            'payment_kind' => $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
            'method' => $method,
            'gateway_response' => [
                'source' => 'owner_counter',
                'created_by' => $actor->id,
                'recorded_as_paid' => $isPaid,
            ],
            'status' => $isPaid ? 'paid' : 'pending',
            'paid_at' => $isPaid ? now() : null,
        ]);

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => $isPaid ? 'counter_payment_recorded' : 'counter_payment_pending',
            'request_payload' => [
                'actor_id' => $actor->id,
                'booking_code' => $booking->booking_code,
                'method' => $method,
                'amount' => (float) $payment->amount,
            ],
            'status_before' => null,
            'status_after' => $payment->status,
        ]);

        return $payment;
    }

    private function recordSystemVoucherSubsidyForPayment(Payment $payment): void
    {
        $payment->loadMissing('booking');

        $amount = $this->systemVoucherAmountForPayment($payment);

        if ($amount <= 0) {
            return;
        }

        $this->systemWallets->reserveVoucher($amount, $payment->id, null, [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'booking_id' => $payment->booking_id,
            'booking_code' => $payment->booking?->booking_code,
            'source' => 'counter_payment',
            'customer_paid_amount' => (float) $payment->amount,
            'system_discount_amount' => (float) ($payment->booking?->system_discount_amount ?? 0),
        ]);
    }

    private function systemVoucherAmountForPayment(Payment $payment): float
    {
        $booking = $payment->booking;
        $systemDiscount = (float) ($booking?->system_discount_amount ?? 0);

        if ($systemDiscount <= 0) {
            return 0;
        }

        $customerPayable = (float) ($booking?->final_amount ?? $booking?->total_price ?? $payment->amount);

        if ($customerPayable <= 0) {
            return round($systemDiscount, 2);
        }

        $ratio = min(max((float) $payment->amount / $customerPayable, 0), 1);

        return round($systemDiscount * $ratio, 2);
    }

    private function assertCounterBookingCanCollect(Booking $booking): void
    {
        if ($booking->source !== 'counter') {
            throw ValidationException::withMessages([
                'booking_id' => 'Chỉ hỗ trợ thu tiền tại quầy cho booking được tạo tại quầy.',
            ]);
        }

        if (in_array($booking->status, ['cancelled', 'expired', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Booking này không còn ở trạng thái có thể thu tiền.',
            ]);
        }
    }

    private function failPendingSepayPayments(Booking $booking, User $actor, string $eventType): void
    {
        Payment::query()
            ->where('booking_id', $booking->id)
            ->where('method', 'sepay')
            ->where('status', 'pending')
            ->lockForUpdate()
            ->get()
            ->each(fn (Payment $payment) => $this->failPendingPayment($payment, $actor, $eventType));
    }

    private function failPendingPayment(Payment $payment, User $actor, string $eventType): void
    {
        $statusBefore = $payment->status;
        $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];

        $payment->update([
            'gateway_response' => array_merge($gatewayResponse, [
                'replaced_by_counter_collection' => [
                    'actor_id' => $actor->id,
                    'replaced_at' => now()->toIso8601String(),
                ],
            ]),
            'status' => 'failed',
        ]);

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => $eventType,
            'request_payload' => [
                'actor_id' => $actor->id,
                'booking_id' => $payment->booking_id,
            ],
            'status_before' => $statusBefore,
            'status_after' => $payment->status,
            'error_code' => 'counter_collection_replaced',
            'error_message' => 'Payment pending được thay thế bởi thao tác thu tiền tại quầy.',
        ]);
    }

    private function paymentKindForCollection(Booking $booking, float $amount): string
    {
        if ((int) round($amount) >= (int) round((float) $booking->total_price)) {
            return 'full';
        }

        if ($booking->payment_option === 'deposit' && (int) round($amount) === (int) round((float) $booking->required_payment_amount)) {
            return 'deposit';
        }

        return 'partial';
    }

    private function lockActiveCourt(string $venueCourtId): VenueCourt
    {
        $court = VenueCourt::query()
            ->with('venueCluster')
            ->whereKey($venueCourtId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($court->status !== 'active') {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Sân này hiện không hoạt động.',
            ]);
        }

        if ($court->venueCluster?->status === 'locked') {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        return $court;
    }

    private function validateDurationAndPayment(string $venueClusterId, string $startTime, string $endTime, string $paymentOption): void
    {
        $this->validateDurationMinutesAndPayment(
            $venueClusterId,
            $this->durationMinutes($startTime, $endTime),
            $paymentOption,
        );
    }

    private function validateDurationMinutesAndPayment(string $venueClusterId, int $durationMinutes, string $paymentOption): void
    {
        $config = $this->bookingConfigForCluster($venueClusterId);
        $minDuration = $config?->min_duration_minutes ?: 30;
        $maxDuration = $config?->max_duration_minutes;

        $this->assertDurationWithinConfig($durationMinutes, $minDuration, $maxDuration, 'end_time');
        $this->assertPaymentOptionAllowed($config, $paymentOption);
    }

    private function validateRangeDurationsAndPayment(string $venueClusterId, array $timeRanges, string $paymentOption): void
    {
        $config = $this->bookingConfigForCluster($venueClusterId);
        $minDuration = $config?->min_duration_minutes ?: 30;
        $maxDuration = $config?->max_duration_minutes;

        collect($timeRanges)
            ->groupBy('venue_court_id')
            ->each(function (Collection $ranges) use ($minDuration, $maxDuration): void {
                $durationMinutes = $ranges->sum(fn (array $range): int => $this->durationMinutes($range['start_time'], $range['end_time']));
                $this->assertDurationWithinConfig($durationMinutes, $minDuration, $maxDuration, 'time_ranges');
            });

        $this->assertPaymentOptionAllowed($config, $paymentOption);
    }

    private function assertDurationWithinConfig(int $durationMinutes, int $minDuration, ?int $maxDuration, string $errorKey): void
    {
        if ($durationMinutes < $minDuration) {
            throw ValidationException::withMessages([
                $errorKey => "Mỗi sân phải được đặt tối thiểu {$minDuration} phút.",
            ]);
        }

        if ($maxDuration && $durationMinutes > $maxDuration) {
            throw ValidationException::withMessages([
                $errorKey => "Mỗi sân chỉ được đặt tối đa {$maxDuration} phút.",
            ]);
        }
    }

    private function assertPaymentOptionAllowed(?BookingConfig $config, string $paymentOption): void
    {
        $allowed = [
            'full_payment' => $config?->allow_full_payment ?? true,
            'deposit' => $config?->allow_deposit ?? true,
            'no_prepay' => $config?->allow_no_prepay ?? true,
        ];

        if (! ($allowed[$paymentOption] ?? false)) {
            throw ValidationException::withMessages([
                'payment_option' => 'Hình thức thanh toán này không được cụm sân hỗ trợ.',
            ]);
        }
    }

    private function validateTimeRange(string $startTime, string $endTime): void
    {
        if ($this->durationMinutes($startTime, $endTime) <= 0) {
            throw ValidationException::withMessages([
                'end_time' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
            ]);
        }
    }

    private function validateTimeRanges(array $timeRanges): void
    {
        if (empty($timeRanges)) {
            throw ValidationException::withMessages([
                'time_ranges' => 'Vui lòng chọn ít nhất một khung giờ.',
            ]);
        }

        foreach ($timeRanges as $range) {
            $this->validateTimeRange($range['start_time'], $range['end_time']);
        }

        collect($timeRanges)
            ->groupBy('venue_court_id')
            ->each(function (Collection $ranges): void {
                $previousEnd = null;

                $ranges->sortBy(fn (array $range) => $this->timeToMinutes($range['start_time']))
                    ->each(function (array $range) use (&$previousEnd): void {
                        if ($previousEnd && $this->timeToMinutes($range['start_time']) < $this->timeToMinutes($previousEnd)) {
                            throw ValidationException::withMessages([
                                'time_ranges' => 'Các khung giờ trên cùng một sân không được chồng lấn nhau.',
                            ]);
                        }

                        $previousEnd = $range['end_time'];
                    });
            });
    }

    private function ensureRangesAreNotInPast(string $bookingDate, array $timeRanges, string $errorKey = 'start_time'): void
    {
        $date = Carbon::parse($bookingDate)->startOfDay();
        $today = Carbon::today();

        if ($date->lt($today)) {
            throw ValidationException::withMessages([
                $errorKey => 'Không thể đặt lịch cho ngày đã qua.',
            ]);
        }

        if (! $date->isSameDay($today)) {
            return;
        }

        $now = Carbon::now();
        $nowMinutes = ($now->hour * 60) + $now->minute;
        $pastRange = collect($timeRanges)
            ->sortBy(fn (array $range) => $this->timeToMinutes($range['start_time']))
            ->first(fn (array $range) => $this->timeToMinutes($range['start_time']) <= $nowMinutes);

        if ($pastRange) {
            throw ValidationException::withMessages([
                $errorKey => 'Không thể đặt khung giờ đã qua trong hôm nay. Vui lòng chọn giờ bắt đầu sau thời điểm hiện tại.',
            ]);
        }
    }

    private function ensureRecurringRangesAreNotInPast(Collection $dates, array $timeRanges): void
    {
        if (! $dates->contains(fn (Carbon $date): bool => $date->isSameDay(Carbon::today()))) {
            return;
        }

        $this->ensureRangesAreNotInPast(Carbon::today()->toDateString(), $timeRanges, 'recurring_start_date');
    }

    private function activeVoucherQuery(string $venueClusterId, ?string $voucherCode = null)
    {
        $now = now();

        return DB::table('vouchers')
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('valid_from')
                ->orWhere('valid_from', '<=', $now))
            ->where(fn ($query) => $query
                ->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $now))
            ->where(fn ($query) => $query
                ->where('owner_type', 'system')
                ->orWhere(fn ($venueQuery) => $venueQuery
                    ->where('owner_type', 'venue')
                    ->where('owner_id', $venueClusterId)))
            ->when($voucherCode, fn ($query) => $query->where('code', Str::upper(trim($voucherCode))))
            ->orderByRaw("CASE WHEN owner_type = 'venue' THEN 0 ELSE 1 END")
            ->orderByDesc('discount_value');
    }

    private function resolveVouchersForBooking(array $data, string $usageUserId, string $venueClusterId, string $courtTypeId, string $bookingType, float $amount): array
    {
        $hasSplitVoucherInput = ($data['venue_voucher_id'] ?? null)
            || ($data['venue_voucher_code'] ?? null)
            || ($data['vip_voucher_id'] ?? null)
            || ($data['vip_voucher_code'] ?? null);

        if (! $hasSplitVoucherInput) {
            $voucher = $this->resolveVoucherForBooking($data, $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amount);

            return [
                'venue' => ($voucher['owner_type'] ?? null) === 'venue' ? $voucher : null,
                'vip' => $voucher && ($voucher['owner_type'] ?? null) !== 'venue' ? $voucher : null,
            ];
        }

        $venueVoucher = $this->resolveVoucherForBooking([
            'voucher_id' => $data['venue_voucher_id'] ?? null,
            'voucher_code' => $data['venue_voucher_code'] ?? null,
        ], $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amount, 'venue');

        $amountAfterVenueVoucher = round(max($amount - (float) ($venueVoucher['discount_amount'] ?? 0), 0), 2);

        $vipVoucher = $this->resolveVoucherForBooking([
            'voucher_id' => $data['vip_voucher_id'] ?? null,
            'voucher_code' => $data['vip_voucher_code'] ?? null,
        ], $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amountAfterVenueVoucher, 'system');

        return [
            'venue' => $venueVoucher,
            'vip' => $vipVoucher,
        ];
    }

    private function resolveVoucherForBooking(array $data, string $usageUserId, string $venueClusterId, string $courtTypeId, string $bookingType, float $amount, ?string $expectedOwnerType = null): ?array
    {
        $voucherId = $data['voucher_id'] ?? null;
        $voucherCode = $data['voucher_code'] ?? null;

        if (! $voucherId && ! $voucherCode) {
            return null;
        }

        $voucherQuery = $this->activeVoucherQuery($venueClusterId, $voucherCode)
            ->when($voucherId, fn ($query) => $query->where('id', $voucherId));

        if (DB::connection()->transactionLevel() > 0) {
            $voucherQuery->lockForUpdate();
        }

        $voucher = $voucherQuery->first();

        if (! $voucher) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher không tồn tại hoặc chưa được kích hoạt.',
            ]);
        }

        if ($expectedOwnerType && $voucher->owner_type !== $expectedOwnerType) {
            throw ValidationException::withMessages([
                'voucher_code' => $expectedOwnerType === 'venue'
                    ? 'Voucher sân phải là voucher do sân phát hành.'
                    : 'Voucher VIP phải là voucher hệ thống hoặc voucher được phát từ gói VIP.',
            ]);
        }

        $unavailableReason = $this->voucherUnavailableReason(
            $voucher,
            $usageUserId,
            $venueClusterId,
            $courtTypeId,
            $bookingType,
            $amount,
        );

        if ($unavailableReason) {
            throw ValidationException::withMessages([
                'voucher_code' => $unavailableReason,
            ]);
        }

        $payload = $this->voucherPreviewPayload($voucher, $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amount);

        if (! $payload) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher không đủ điều kiện áp dụng cho booking này.',
            ]);
        }

        return $payload;
    }

    private function voucherPreviewPayload(object $voucher, string $usageUserId, string $venueClusterId, string $courtTypeId, string $bookingType, float $amount, int $usageCount = 1): ?array
    {
        if (! $this->voucherCanBeUsed($voucher, $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amount, $usageCount)) {
            return null;
        }

        $discountAmount = $this->voucherDiscountAmount($voucher, $amount);

        if ($discountAmount <= 0) {
            return null;
        }

        return [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'description' => $voucher->description,
            'owner_type' => $voucher->owner_type,
            'funded_by' => $voucher->funded_by,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'max_discount_amount' => $voucher->max_discount_amount !== null ? (float) $voucher->max_discount_amount : null,
            'min_order_amount' => (float) $voucher->min_order_amount,
            'discount_amount' => $discountAmount,
            'final_amount' => round(max($amount - $discountAmount, 0), 2),
            'discount_label' => $voucher->discount_type === 'percent'
                ? rtrim(rtrim(number_format((float) $voucher->discount_value, 2, '.', ''), '0'), '.') . '%'
                : number_format((float) $voucher->discount_value, 0, ',', '.') . ' đ',
            'scope_label' => $voucher->owner_type === 'venue' ? 'Voucher của sân' : 'Voucher hệ thống',
        ];
    }

    private function voucherCanBeUsed(object $voucher, string $usageUserId, string $venueClusterId, string $courtTypeId, string $bookingType, float $amount, int $usageCount = 1): bool
    {
        return $this->voucherUnavailableReason($voucher, $usageUserId, $venueClusterId, $courtTypeId, $bookingType, $amount, $usageCount) === null;
    }

    private function voucherUnavailableReason(object $voucher, string $usageUserId, string $venueClusterId, string $courtTypeId, string $bookingType, float $amount, int $usageCount = 1): ?string
    {
        if (($voucher->status ?? null) !== 'active') {
            return 'Voucher đã bị tắt hoặc chưa được kích hoạt.';
        }

        if (($voucher->assigned_user_id ?? null) && (string) $voucher->assigned_user_id !== (string) $usageUserId) {
            return 'Voucher VIP nay chi danh cho dung tai khoan duoc phat.';
        }

        if ((float) $voucher->min_order_amount > $amount) {
            return 'Voucher chưa đạt giá trị đơn tối thiểu.';
        }

        if ($voucher->total_quantity !== null && ((int) $voucher->used_quantity + $usageCount) > (int) $voucher->total_quantity) {
            return 'Voucher vừa hết lượt sử dụng. Vui lòng chọn voucher khác hoặc bỏ áp dụng voucher.';
        }

        if ($voucher->per_user_limit !== null) {
            $usedByUser = DB::table('voucher_usages')
                ->where('voucher_id', $voucher->id)
                ->where('user_id', $usageUserId)
                ->where('status', 'applied')
                ->count();

            if (($usedByUser + $usageCount) > (int) $voucher->per_user_limit) {
                return 'Khách này đã dùng hết số lượt cho voucher này.';
            }
        }

        $scopes = DB::table('voucher_scopes')
            ->where('voucher_id', $voucher->id)
            ->get();

        if ($scopes->isEmpty() || $scopes->contains('scope_type', 'all')) {
            return null;
        }

        $userTierKey = $this->venueMemberships->userTierKey($usageUserId, $venueClusterId);
        $inScope = $scopes->contains(function (object $scope) use ($venueClusterId, $courtTypeId, $bookingType, $userTierKey, $usageUserId): bool {
            return match ($scope->scope_type) {
                'venue_cluster' => (string) $scope->scope_id === (string) $venueClusterId,
                'court_type' => (string) $scope->scope_id === (string) $courtTypeId,
                'booking_type' => (string) $scope->scope_id === (string) $bookingType,
                'membership_tier' => (string) $scope->scope_id === (string) $userTierKey,
                'vip_package' => $this->systemVip->userHasVipPackage($usageUserId, (string) $scope->scope_id),
                default => false,
            };
        });

        return $inScope ? null : 'Voucher không áp dụng cho sân, loại sân hoặc hình thức đặt này.';
    }

    private function voucherDiscountAmount(object $voucher, float $amount): float
    {
        $discount = $voucher->discount_type === 'percent'
            ? $amount * ((float) $voucher->discount_value / 100)
            : (float) $voucher->discount_value;

        if ($voucher->max_discount_amount !== null) {
            $discount = min($discount, (float) $voucher->max_discount_amount);
        }

        return round(min(max($discount, 0), $amount), 2);
    }

    private function recordVoucherUsage(array $voucher, Booking $booking, string $usageUserId): void
    {
        $voucherRow = DB::table('vouchers')
            ->where('id', $voucher['id'])
            ->lockForUpdate()
            ->first();

        if (! $voucherRow) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher không tồn tại hoặc đã bị xóa.',
            ]);
        }

        if (($voucherRow->status ?? null) !== 'active') {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher đã bị tắt hoặc chưa được kích hoạt.',
            ]);
        }

        if (
            $voucherRow->total_quantity !== null
            && ((int) $voucherRow->used_quantity + 1) > (int) $voucherRow->total_quantity
        ) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Voucher vừa hết lượt sử dụng. Vui lòng chọn voucher khác hoặc bỏ áp dụng voucher.',
            ]);
        }

        DB::table('voucher_usages')->insert([
            'id' => (string) Str::uuid(),
            'voucher_id' => $voucher['id'],
            'user_id' => $usageUserId,
            'booking_id' => $booking->id,
            'payment_id' => null,
            'discount_amount' => $voucher['discount_amount'],
            'used_at' => now(),
            'status' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vouchers')
            ->where('id', $voucher['id'])
            ->update([
                'used_quantity' => (int) $voucherRow->used_quantity + 1,
                'updated_at' => now(),
            ]);
    }

    private function requiredPaymentAmount(string $venueClusterId, float $totalPrice, string $paymentOption): float
    {
        if ($paymentOption === 'full_payment') {
            return round($totalPrice, 2);
        }

        if ($paymentOption === 'deposit') {
            $config = $this->bookingConfigForCluster($venueClusterId);
            $depositPercent = (float) ($config?->deposit_percent ?? 30);

            return round($totalPrice * ($depositPercent / 100), 2);
        }

        return 0.0;
    }

    private function initialCounterStatus(string $paymentOption, bool $isPaid): string
    {
        if ($isPaid) {
            return 'completed';
        }

        if ($paymentOption === 'no_prepay') {
            return 'confirmed';
        }

        return 'pending_payment';
    }

    private function bookingConfigForCluster(string $venueClusterId): ?BookingConfig
    {
        return BookingConfig::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->first();
    }

    private function validateRecurringDates(Collection $dates): void
    {
        if ($dates->isEmpty()) {
            throw ValidationException::withMessages([
                'recurring_start_date' => 'Không có buổi nào phù hợp với lịch cố định đã chọn.',
            ]);
        }

        if ($dates->count() > 130) {
            throw ValidationException::withMessages([
                'recurring_end_date' => 'Lịch cố định tối đa 130 buổi mỗi lần tạo.',
            ]);
        }
    }

    private function recurringConflictPayload(VenueCourt $court, Collection $dates, string $startTime, string $endTime): Collection
    {
        return $dates
            ->filter(fn (Carbon $date) => ! $this->checkAvailability(
                $court->id,
                $date->toDateString(),
                $startTime,
                $endTime,
            ))
            ->values()
            ->map(function (Carbon $date) use ($court, $startTime, $endTime): array {
                $dateString = $date->toDateString();

                return [
                    'date' => $dateString,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'current_court' => $this->recurringCourtPayload($court),
                    'reason' => 'Khung giờ đã có booking hoặc đang bị khóa.',
                    'alternatives' => $this->availableAlternativeCourts($court, $dateString, $startTime, $endTime)
                        ->map(fn (VenueCourt $candidate): array => $this->recurringCourtPayload($candidate))
                        ->values()
                        ->all(),
                ];
            });
    }

    private function recurringConflictPayloadForRanges(VenueCourt $court, Collection $dates, array $timeRanges): Collection
    {
        if (count($timeRanges) === 1) {
            $range = $timeRanges[0];
            $rangeCourt = VenueCourt::query()
                ->with('courtType')
                ->findOrFail($range['venue_court_id']);

            return $this->recurringConflictPayload(
                $rangeCourt,
                $dates,
                $range['start_time'],
                $range['end_time'],
            );
        }

        return $dates
            ->flatMap(function (Carbon $date) use ($timeRanges): Collection {
                $dateString = $date->toDateString();

                return collect($timeRanges)
                    ->filter(fn (array $range): bool => ! $this->checkAvailability(
                        $range['venue_court_id'],
                        $dateString,
                        $range['start_time'],
                        $range['end_time'],
                    ))
                    ->map(function (array $range) use ($dateString): array {
                        $rangeCourt = VenueCourt::query()
                            ->with('courtType')
                            ->findOrFail($range['venue_court_id']);

                        return [
                            'key' => implode('|', [
                                $dateString,
                                $range['venue_court_id'],
                                $range['start_time'],
                                $range['end_time'],
                            ]),
                            'date' => $dateString,
                            'start_time' => $range['start_time'],
                            'end_time' => $range['end_time'],
                            'current_court' => $this->recurringCourtPayload($rangeCourt),
                            'reason' => 'Một khung trong nhóm lịch đã có booking hoặc đang bị khóa.',
                            'alternatives' => $this->availableAlternativeCourts(
                                $rangeCourt,
                                $dateString,
                                $range['start_time'],
                                $range['end_time'],
                            )
                                ->map(fn (VenueCourt $candidate): array => $this->recurringCourtPayload($candidate))
                                ->values()
                                ->all(),
                        ];
                    });
            })
            ->values();
    }

    private function recurringConflictPayloadForDateRanges(Collection $rangesByDate): Collection
    {
        return $rangesByDate
            ->flatMap(function (array $timeRanges, string $dateString): Collection {
                return collect($timeRanges)
                    ->filter(fn (array $range): bool => ! $this->checkAvailability(
                        $range['venue_court_id'],
                        $dateString,
                        $range['start_time'],
                        $range['end_time'],
                    ))
                    ->map(function (array $range) use ($dateString): array {
                        $rangeCourt = VenueCourt::query()
                            ->with('courtType')
                            ->findOrFail($range['venue_court_id']);

                        return [
                            'key' => implode('|', [
                                $dateString,
                                $range['venue_court_id'],
                                $range['start_time'],
                                $range['end_time'],
                            ]),
                            'date' => $dateString,
                            'start_time' => $range['start_time'],
                            'end_time' => $range['end_time'],
                            'current_court' => $this->recurringCourtPayload($rangeCourt),
                            'reason' => 'Một khung trong nhóm lịch đã có booking hoặc đang bị khóa.',
                            'alternatives' => $this->availableAlternativeCourts(
                                $rangeCourt,
                                $dateString,
                                $range['start_time'],
                                $range['end_time'],
                            )
                                ->map(fn (VenueCourt $candidate): array => $this->recurringCourtPayload($candidate))
                                ->values()
                                ->all(),
                        ];
                    });
            })
            ->values();
    }

    private function availableAlternativeCourts(VenueCourt $court, string $date, string $startTime, string $endTime): Collection
    {
        return VenueCourt::query()
            ->with('courtType')
            ->where('venue_cluster_id', $court->venue_cluster_id)
            ->where('court_type_id', $court->court_type_id)
            ->where('status', 'active')
            ->whereKeyNot($court->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (VenueCourt $candidate): bool => $this->checkAvailability(
                $candidate->id,
                $date,
                $startTime,
                $endTime,
            ))
            ->values();
    }

    private function recurringCourtPayload(VenueCourt $court): array
    {
        return [
            'id' => $court->id,
            'name' => $court->name,
            'court_type' => $court->courtType ? [
                'id' => $court->courtType->id,
                'name' => $court->courtType->name,
            ] : null,
        ];
    }

    private function recurringDates(array $data): Collection
    {
        $start = Carbon::parse($data['recurring_start_date'])->startOfDay();
        $end = Carbon::parse($data['recurring_end_date'])->startOfDay();
        $interval = max((int) $data['recurrence_interval'], 1);
        $dates = collect();
        $weekDays = collect($data['recurrence_days_of_week'] ?? [])->map(fn ($day) => (int) $day)->values();
        $monthDays = collect($data['recurrence_days_of_month'] ?? [])->map(fn ($day) => (int) $day)->values();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $daysDiff = $start->diffInDays($date);
            $weeksDiff = intdiv($daysDiff, 7);
            $monthsDiff = (($date->year - $start->year) * 12) + ($date->month - $start->month);
            $matches = match ($data['recurrence_type']) {
                'daily' => $daysDiff % $interval === 0,
                'weekly' => $weeksDiff % $interval === 0 && $weekDays->contains($date->dayOfWeekIso - 1),
                'monthly' => $monthsDiff % $interval === 0 && $monthDays->contains($date->day),
                default => false,
            };

            if ($matches) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    private function recurringRangesByDate(array $data, Collection $dates, VenueCourt $defaultCourt): Collection
    {
        $weekdayRanges = $this->recurringWeekdayRanges($data, $defaultCourt);
        $fallbackRanges = $this->normalizeTimeRanges($data, $defaultCourt->id);

        return $dates
            ->mapWithKeys(function (Carbon $date) use ($weekdayRanges, $fallbackRanges): array {
                $day = $date->dayOfWeekIso - 1;

                return [
                    $date->toDateString() => $weekdayRanges[$day] ?? $fallbackRanges,
                ];
            });
    }

    private function recurringWeekdayRanges(array $data, VenueCourt $defaultCourt): array
    {
        if (($data['recurrence_type'] ?? null) !== 'weekly' || empty($data['weekday_time_ranges'])) {
            return [];
        }

        return collect($data['weekday_time_ranges'])
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['day_of_week'] => $this->normalizeTimeRanges(
                    ['time_ranges' => $item['time_ranges']],
                    $defaultCourt->id,
                ),
            ])
            ->all();
    }

    private function uniqueBookingCode(): string
    {
        do {
            $code = 'BK'.Str::upper(Str::random(8));
        } while (Booking::query()->where('booking_code', $code)->exists());

        return $code;
    }

    private function uniqueRecurringGroupCode(): string
    {
        do {
            $code = 'RG'.Str::upper(Str::random(8));
        } while (Booking::query()->where('recurring_group_code', $code)->exists());

        return $code;
    }

    private function uniquePaymentCode(): string
    {
        do {
            $code = 'PM'.Str::upper(Str::random(10));
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }

    private function busyIntervals(string $venueClusterId, Collection $courtIds, string $bookingDate, bool $includeDetails = false): Collection
    {
        $bookingColumns = $includeDetails
            ? ['id', 'booking_code', 'customer_id', 'venue_court_id', 'start_time', 'end_time', 'status', 'payment_option', 'total_price', 'required_payment_amount', 'source', 'walk_in_name', 'walk_in_phone']
            : ['id', 'venue_court_id', 'start_time', 'end_time', 'status'];

        $bookingQuery = Booking::query()
            ->with('items:id,booking_id,venue_court_id,start_time,end_time,status,status_reason,subtotal');

        if ($includeDetails) {
            $bookingQuery->with([
                'customer:id,username,full_name,phone,email',
                'payments:id,booking_id,amount,status,method,payment_kind,paid_at',
            ]);
        }

        $bookings = $bookingQuery
            ->where('venue_cluster_id', $venueClusterId)
            ->where('booking_date', $bookingDate)
            ->whereIn('status', self::BLOCKING_BOOKING_STATUSES)
            ->where(function ($query) use ($courtIds) {
                $query->whereIn('venue_court_id', $courtIds)
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                        ->whereIn('venue_court_id', $courtIds)
                        ->where(fn ($activeItemQuery) => $this->activeBookingItemConstraint($activeItemQuery)));
            })
            ->get($bookingColumns)
            ->flatMap(function (Booking $booking) use ($courtIds, $includeDetails): Collection {
                $ranges = $booking->items->isNotEmpty()
                    ? $booking->items
                        ->filter(fn (BookingItem $item): bool => $this->isActiveBookingItem($item))
                        ->map(fn (BookingItem $item): array => [
                        'venue_court_id' => $item->venue_court_id,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                        'booking_item_id' => $item->id,
                        'booking_item_status' => $item->status,
                        'booking_item_subtotal' => (float) $item->subtotal,
                    ])
                    : collect([[
                        'venue_court_id' => $booking->venue_court_id,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                    ]]);

                return $ranges
                    ->filter(fn (array $range): bool => $courtIds->contains($range['venue_court_id']))
                    ->map(function (array $range) use ($booking, $includeDetails): array {
                        $payload = [
                            ...$range,
                            'source' => 'booking',
                            'status' => $booking->status,
                            'schedule_lock_id' => null,
                            'reason' => null,
                        ];

                        if (! $includeDetails) {
                            return $payload;
                        }

                        $paidAmount = (float) $booking->payments
                            ->where('status', 'paid')
                            ->sum('amount');

                        return [
                            ...$payload,
                            'booking_id' => $booking->id,
                            'booking_code' => $booking->booking_code,
                            'payment_option' => $booking->payment_option,
                            'total_price' => (float) $booking->total_price,
                            'required_payment_amount' => (float) $booking->required_payment_amount,
                            'paid_amount' => $paidAmount,
                            'outstanding_amount' => max((float) $booking->total_price - $paidAmount, 0),
                            'booking_source' => $booking->source,
                            'customer' => $booking->customer ? [
                                'id' => $booking->customer->id,
                                'username' => $booking->customer->username,
                                'full_name' => $booking->customer->full_name,
                                'phone' => $booking->customer->phone,
                                'email' => $booking->customer->email,
                            ] : null,
                            'walk_in_name' => $booking->walk_in_name,
                            'walk_in_phone' => $booking->walk_in_phone,
                        ];
                    });
            })
            ->toBase();

        $slotLocks = SlotLock::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->where('booking_date', $bookingDate)
            ->where(fn ($query) => $this->activeSlotLockConstraint($query))
            ->where(function ($query) use ($courtIds) {
                $query->where('lock_scope', 'cluster')
                    ->orWhereIn('venue_court_id', $courtIds);
            })
            ->get(['id', 'venue_court_id', 'lock_scope', 'start_time', 'end_time', 'lock_type', 'reason'])
            ->flatMap(function (SlotLock $lock) use ($courtIds) {
                $targetCourtIds = $lock->lock_scope === 'cluster' ? $courtIds : collect([$lock->venue_court_id]);

                return $targetCourtIds->map(fn ($courtId) => [
                    'venue_court_id' => $courtId,
                    'start_time' => $lock->start_time,
                    'end_time' => $lock->end_time,
                    'source' => 'slot_lock',
                    'status' => $lock->lock_type,
                    'schedule_lock_id' => $lock->id,
                    'reason' => $lock->reason,
                ]);
            });

        return $bookings->merge($slotLocks)->values();
    }

    private function activeSlotLockConstraint($query): void
    {
        $query->whereIn('lock_type', ['manual', 'emergency'])
            ->orWhere(function ($autoQuery): void {
                $autoQuery->where('lock_type', 'auto')
                    ->where('expires_at', '>', Carbon::now());
            });
    }

    private function activeBookingItemConstraint($query): void
    {
        $query->whereNull('status')
            ->orWhereIn('status', ['active', 'moved']);
    }

    private function isActiveBookingItem(BookingItem $item): bool
    {
        return in_array($item->status ?: 'active', ['active', 'moved'], true);
    }

    private function overlappingInterval(Collection $intervals, string $venueCourtId, string $startTime, string $endTime): ?array
    {
        $slotStart = $this->timeToMinutes($startTime);
        $slotEnd = $this->timeToMinutes($endTime);

        return $intervals->first(function (array $interval) use ($venueCourtId, $slotStart, $slotEnd) {
            return $interval['venue_court_id'] === $venueCourtId
                && $this->timeToMinutes($interval['start_time']) < $slotEnd
                && $this->timeToMinutes($interval['end_time']) > $slotStart;
        });
    }

    private function buildTimeSlots(string $openTime = '00:00:00', string $closeTime = '24:00:00'): array
    {
        $slots = [];
        $openMinutes = $this->timeToMinutes($openTime);
        $closeMinutes = $this->timeToMinutes($closeTime);

        for ($minutes = $openMinutes; $minutes < $closeMinutes; $minutes += 30) {
            $slots[] = [
                'start_time' => $this->minutesToTime($minutes),
                'end_time' => $this->minutesToTime(min($minutes + 30, $closeMinutes)),
                'label' => substr($this->minutesToTime($minutes), 0, 5),
            ];
        }

        return $slots;
    }

    private function assertWithinOperatingHours(string $venueClusterId, string $bookingDate, string $startTime, string $endTime): void
    {
        $hours = $this->resolveOperatingHours($venueClusterId, $bookingDate);

        if (! $hours['is_open']) {
            throw ValidationException::withMessages([
                'booking_date' => 'Cụm sân đóng cửa trong ngày đã chọn.',
            ]);
        }

        if (! $this->isWithinOperatingHours($venueClusterId, $bookingDate, $startTime, $endTime)) {
            throw ValidationException::withMessages([
                'start_time' => sprintf(
                    'Khung giờ đặt sân phải nằm trong giờ mở cửa %s - %s.',
                    substr($hours['open_time'], 0, 5),
                    substr($hours['close_time'], 0, 5),
                ),
            ]);
        }
    }

    private function assertMinimumAdvanceNotice(string $venueClusterId, string $bookingDate, string $startTime): void
    {
        if ($this->meetsMinimumAdvanceNotice($venueClusterId, $bookingDate, $startTime)) {
            return;
        }

        $minimumMinutes = (int) ($this->bookingConfigForCluster($venueClusterId)?->min_advance_booking_minutes ?? 30);

        throw ValidationException::withMessages([
            'start_time' => "Booking phải được đặt trước ít nhất {$minimumMinutes} phút.",
        ]);
    }

    private function isWithinOperatingHours(string $venueClusterId, string $bookingDate, string $startTime, string $endTime): bool
    {
        $hours = $this->resolveOperatingHours($venueClusterId, $bookingDate);

        if (! $hours['is_open']) {
            return false;
        }

        return $this->timeToMinutes($startTime) >= $this->timeToMinutes($hours['open_time'])
            && $this->timeToMinutes($endTime) <= $this->timeToMinutes($hours['close_time'])
            && $this->timeToMinutes($endTime) > $this->timeToMinutes($startTime);
    }

    private function normalizeClock(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function durationMinutes(string $startTime, string $endTime): int
    {
        return $this->timeToMinutes($endTime) - $this->timeToMinutes($startTime);
    }

    private function rangesDurationMinutes(array $timeRanges): int
    {
        return collect($timeRanges)->sum(fn (array $range) => $this->durationMinutes($range['start_time'], $range['end_time']));
    }

    private function courtsForTimeRanges(array $timeRanges, VenueCourt $primaryCourt): Collection
    {
        $courtIds = collect($timeRanges)->pluck('venue_court_id')->unique()->values();
        $courts = VenueCourt::query()
            ->with('venueCluster')
            ->whereIn('id', $courtIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($courts->count() !== $courtIds->count()) {
            throw ValidationException::withMessages([
                'time_ranges' => 'Một hoặc nhiều sân trong khung giờ không hợp lệ.',
            ]);
        }

        foreach ($courts as $court) {
            if ($court->status !== 'active') {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Sân con này hiện không hoạt động.',
                ]);
            }

            if ($court->venue_cluster_id !== $primaryCourt->venue_cluster_id) {
                throw ValidationException::withMessages([
                    'time_ranges' => 'Các sân trong cùng một booking phải thuộc cùng cụm sân.',
                ]);
            }

            if ($court->venueCluster?->status === 'locked') {
                throw ValidationException::withMessages([
                    'venue_cluster_id' => 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.',
                ]);
            }
        }

        return $courts;
    }

    private function normalizeTimeRanges(array $data, ?string $defaultCourtId = null): array
    {
        $defaultCourtId = $defaultCourtId ?: ($data['venue_court_id'] ?? null);
        $ranges = $data['time_ranges'] ?? [[
            'venue_court_id' => $defaultCourtId,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]];

        return collect($ranges)
            ->map(fn (array $range) => [
                'venue_court_id' => $range['venue_court_id'] ?? $defaultCourtId,
                'start_time' => $this->minutesToTime($this->timeToMinutes($range['start_time'])),
                'end_time' => $this->minutesToTime($this->timeToMinutes($range['end_time'])),
            ])
            ->sortBy(fn (array $range) => sprintf(
                '%05d-%s',
                $this->timeToMinutes($range['start_time']),
                $range['venue_court_id'],
            ))
            ->values()
            ->all();
    }

    private function minutesToTime(int $minutes): string
    {
        if ($minutes >= 1440) {
            return '24:00:00';
        }

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
