<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
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
            ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed'])
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

            $court = VenueCourt::findOrFail($venueCourtId);
            $venueClusterId = $court->venue_cluster_id;

            // 1. Kiểm tra tính trống của sân
            if (!$this->checkAvailability($venueCourtId, $bookingDate, $startTime, $endTime)) {
                throw new Exception('Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.');
            }

            // 2. Tính toán thời lượng đặt sân (phút)
            $start = Carbon::createFromFormat('H:i:s', $startTime);
            $end = Carbon::createFromFormat('H:i:s', $endTime);
            if ($end->lessThanOrEqualTo($start)) {
                throw new Exception('Giờ kết thúc phải lớn hơn giờ bắt đầu.');
            }
            $durationMinutes = $start->diffInMinutes($end);

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

            // 5. Tính giá tiền đặt sân
            $dayOfWeek = Carbon::parse($bookingDate)->dayOfWeekIso; // 1 (Thứ 2) - 7 (Chủ Nhật)
            $priceSlot = PriceSlot::where('venue_cluster_id', $venueClusterId)
                ->where('court_type_id', $court->court_type_id)
                ->where('is_active', true)
                ->where(function ($query) use ($dayOfWeek) {
                    $query->whereJsonContains('apply_to_days', $dayOfWeek)
                        ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek);
                })
                ->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime)
                ->first();

            $hourlyRate = $priceSlot;
            $totalPrice = ($durationMinutes / 60) * $hourlyRate;

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
}