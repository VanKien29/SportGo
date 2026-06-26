<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Services\Memberships\VenueMembershipService;
use App\Services\Policies\RefundCancellationPolicyService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    protected RefundCancellationPolicyService $refundCancellationPolicyService;

    public function __construct(
        BookingService $bookingService,
        RefundCancellationPolicyService $refundCancellationPolicyService,
        private readonly VenueMembershipService $venueMemberships,
    ) {
        $this->bookingService = $bookingService;
        $this->refundCancellationPolicyService = $refundCancellationPolicyService;
    }

    /**
     * API lấy dữ liệu khởi tạo cụm sân và sân con hoạt động.
     */
    public function initData()
    {
        $clusters = VenueCluster::with(['bookingConfig', 'venueCourts' => function ($query) {
            $query->where('status', 'active');
        }, 'venueCourts.courtType'])->where('status', 'active')->get();

        return response()->json([
            'clusters' => $clusters,
        ]);
    }

    /**
     * API kiểm tra lịch trống của sân con.
     */
    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'venue_court_id' => 'required|exists:venue_courts,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
        ]);
        $this->ensureValidTimeRange($validated['start_time'], $validated['end_time']);

        $available = $this->bookingService->checkAvailability(
            $request->input('venue_court_id'),
            $request->input('booking_date'),
            $request->input('start_time'),
            $request->input('end_time')
        ) && $this->bookingService->meetsMinimumAdvanceNotice(
            VenueCourt::findOrFail($request->input('venue_court_id'))->venue_cluster_id,
            $request->input('booking_date'),
            $request->input('start_time'),
        );

        $court = VenueCourt::findOrFail($request->input('venue_court_id'));
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
        $durationHours = max((($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute)) / 60, 0.5);
        $totalPrice = $this->bookingService->calculateTotalPrice(
            $court,
            $request->input('booking_date'),
            $startTime,
            $endTime,
        );
        $membership = $this->venueMemberships->discountForBooking(
            $request->user()->id,
            $court->venue_cluster_id,
            $totalPrice,
        );
        $membershipDiscount = (float) ($membership['discount_amount'] ?? 0);
        $finalPrice = round(max($totalPrice - $membershipDiscount, 0), 2);

        return response()->json([
            'available' => $available,
            'hourly_rate' => round($totalPrice / $durationHours, 2),
            'total_price' => $totalPrice,
            'final_amount' => $finalPrice,
            'membership_discount' => $membership,
            'price_preview' => [
                'original_amount' => $totalPrice,
                'membership_discount_amount' => $membershipDiscount,
                'final_amount' => $finalPrice,
            ],
        ]);
    }

    /**
     * API lấy lịch dạng interval để FE tự sinh bảng 30 phút, không lưu từng ô trong DB.
     */
    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'venue_cluster_id' => 'required|exists:venue_clusters,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'court_type_id' => 'nullable|integer|exists:court_types,id',
            'booking_type' => 'nullable|in:single,recurring',
        ]);

        return response()->json($this->bookingService->getAvailabilitySchedule(
            $validated['venue_cluster_id'],
            $validated['booking_date'],
            isset($validated['court_type_id']) ? (int) $validated['court_type_id'] : null,
            $validated['booking_type'] ?? 'single'
        ));
    }

    /**
     * API đặt sân mới (Yêu cầu đăng nhập).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_court_id' => 'required|exists:venue_courts,id',
            'booking_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => 'required|in:full_payment,deposit,no_prepay',
        ]);
        $this->ensureValidTimeRange($validated['start_time'], $validated['end_time']);

        try {
            $booking = $this->bookingService->createBooking($validated, auth()->id());

            return response()->json($booking, 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API xem chi tiết đơn đặt sân.
     */
    public function show(string $id)
    {
        $booking = Booking::findOrFail($id);

        // Bảo vệ quyền riêng tư: Người chơi chỉ được xem đơn đặt của chính mình
        if ($booking->customer_id !== auth()->id()) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập thông tin đơn đặt sân này.',
            ], 403);
        }

        // Đính kèm các thông tin liên quan nếu cần
        $booking->load(['venueCourt.venueCluster', 'venueCourt.courtType']);

        // Tính thời gian giữ chỗ còn lại (giây)
        $timeLeftSeconds = 0;
        if ($booking->status === 'pending_payment') {
            $lock = SlotLock::where('booking_id', $booking->id)
                ->where('expires_at', '>', Carbon::now())
                ->first();
            if ($lock) {
                $timeLeftSeconds = (int) max(0, floor(Carbon::now()->diffInSeconds($lock->expires_at, false)));
            }
        }

        $bookingArray = $booking->toArray();
        $bookingArray['time_left_seconds'] = $timeLeftSeconds;

        return response()->json($bookingArray);
    }

    public function cancel(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::findOrFail($id);

        try {
            $result = $this->refundCancellationPolicyService->cancelBooking(
                $booking,
                $request->user(),
                null,
                $validated['reason'] ?? null
            );

            return response()->json([
                'message' => 'Đã hủy booking theo chính sách.',
                ...$result,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function ensureValidTimeRange(string $startTime, string $endTime): void
    {
        if ($this->timeToMinutes($endTime) <= $this->timeToMinutes($startTime)) {
            throw ValidationException::withMessages([
                'end_time' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
            ]);
        }
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
