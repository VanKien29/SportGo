<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SlotLock;
use App\Services\BookingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        return response()->json([
            'available' => $available,
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

    /**
     * API giả lập thanh toán thành công để test luồng giữ sân 20 phút.
     */
    public function simulatePayment(string $id, Request $request)
    {
        $request->validate([
            'method' => 'required|in:vnpay,momo,zalopay',
        ]);

        $booking = Booking::findOrFail($id);

        if ($booking->customer_id !== auth()->id()) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện giao dịch cho đơn đặt sân này.',
            ], 403);
        }

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Đơn đặt sân này không ở trạng thái chờ thanh toán.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking, $request) {
                // 1. Cập nhật trạng thái Booking sang Confirmed
                $booking->update([
                    'status' => 'confirmed',
                ]);

                // 2. Xoá Slot Lock liên quan để mở khoá tạm thời
                SlotLock::where('booking_id', $booking->id)->delete();

                // 3. Tạo bản ghi thanh toán thành công (Payment)
                Payment::create([
                    'payment_code' => 'PM' . strtoupper(Str::random(10)),
                    'booking_id' => $booking->id,
                    'amount' => $booking->required_payment_amount,
                    'payment_kind' => $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
                    'method' => $request->input('method'),
                    'gateway_txn_id' => 'TXN_' . Str::random(12),
                    'gateway_response' => ['status' => 'simulated_success', 'timestamp' => Carbon::now()->toIso8601String()],
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);
            });

            return response()->json([
                'message' => 'Thanh toán giả lập thành công.',
                'booking' => $booking->fresh(['venueCourt.venueCluster']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình thanh toán: ' . $e->getMessage(),
            ], 500);
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
