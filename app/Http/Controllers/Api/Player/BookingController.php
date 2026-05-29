<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PriceSlot;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use App\Services\BookingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * API lấy dữ liệu khởi tạo cụm sân và sân con hoạt động.
     */
    public function initData()
    {
        $clusters = \App\Models\VenueCluster::with(['bookingConfig', 'venueCourts' => function ($query) {
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
        );

        // Tra cứu đơn giá từ bảng price_slots
        $court = VenueCourt::findOrFail($request->input('venue_court_id'));
        $dayOfWeek = Carbon::parse($request->input('booking_date'))->dayOfWeekIso;
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        $priceSlot = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
            ->where('court_type_id', $court->court_type_id)
            ->where('is_active', true)
            ->where(function ($query) use ($dayOfWeek) {
                $query->whereJsonContains('apply_to_days', $dayOfWeek)
                    ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek);
            })
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->first();

        $hourlyRate = $priceSlot ? (float) $priceSlot->price : 10000.00;

        return response()->json([
            'available' => $available,
            'hourly_rate' => $hourlyRate,
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
                $timeLeftSeconds = max(0, Carbon::now()->diffInSeconds($lock->expires_at, false));
            }
        }

        $bookingArray = $booking->toArray();
        $bookingArray['time_left_seconds'] = $timeLeftSeconds;

        return response()->json($bookingArray);
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