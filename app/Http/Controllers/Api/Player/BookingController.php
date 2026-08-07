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
            'clusters' => $clusters->map(function (VenueCluster $cluster): array {
                $payload = $cluster->toArray();
                $config = $cluster->bookingConfig;

                $payload['booking_config'] = [
                    'venue_cluster_id' => $cluster->id,
                    'min_duration_minutes' => $config?->min_duration_minutes ?? 30,
                    'max_duration_minutes' => $config?->max_duration_minutes,
                    'min_advance_booking_minutes' => $config?->min_advance_booking_minutes ?? 30,
                    'fixed_open_time' => $config?->fixed_open_time,
                    'fixed_close_time' => $config?->fixed_close_time,
                    'weekly_operating_hours' => $config?->weekly_operating_hours ?? [],
                    'special_operating_hours' => $config?->special_operating_hours ?? [],
                    'slot_hold_minutes' => $config?->slot_hold_minutes ?? 20,
                    'allow_full_payment' => $config?->allow_full_payment ?? true,
                    'allow_deposit' => $config?->allow_deposit ?? true,
                    'allow_no_prepay' => $config?->allow_no_prepay ?? true,
                    'deposit_percent' => $config?->deposit_percent !== null
                        ? (float) $config->deposit_percent
                        : 30,
                ];

                return $payload;
            })->values(),
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
     * API lấy voucher đủ điều kiện cho slot đang chọn.
     */
    public function eligibleVouchers(Request $request)
    {
        $validated = $request->validate([
            'venue_court_id' => 'required|exists:venue_courts,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'amount' => 'nullable|numeric|min:0',
        ]);
        $this->ensureValidTimeRange($validated['start_time'], $validated['end_time']);

        $court = VenueCourt::findOrFail($validated['venue_court_id']);
        $amount = isset($validated['amount'])
            ? (float) $validated['amount']
            : $this->bookingService->calculateTotalPrice(
                $court,
                $validated['booking_date'],
                $validated['start_time'],
                $validated['end_time'],
                'single',
            );

        $vouchers = $this->bookingService->eligibleVouchersForCounterBooking([
            ...$validated,
            'amount' => $amount,
            'booking_type' => 'single',
            'customer_id' => $request->user()->id,
        ], $request->user());

        return response()->json([
            'venue_vouchers' => $vouchers
                ->where('owner_type', 'venue')
                ->values(),
            'vip_vouchers' => $vouchers
                ->where('owner_type', 'system')
                ->values(),
        ]);
    }

    /**
     * API đặt sân mới (Yêu cầu đăng nhập).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status_group' => 'nullable|in:all,upcoming,completed,cancelled,refunded',
            'status' => 'nullable|in:pending_approval,pending_payment,confirmed,checked_in,completed,cancelled,expired,rejected',
            'search' => 'nullable|string|max:100',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'booking_type' => 'nullable|in:single,recurring',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded,not_required',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Booking::query()
            ->with([
                'venueCourt.venueCluster',
                'venueCourt.courtType',
                'venueCluster',
                'items.venueCourt.courtType',
                'items.requestedVenueCourt.courtType',
                'payments' => fn ($query) => $query->latest('created_at'),
                'refunds',
            ])
            ->where('customer_id', auth()->id());

        $query->when($validated['search'] ?? null, function ($query, string $search): void {
            $query->where('booking_code', 'like', '%'.$search.'%');
        });
        $query->when($validated['from_date'] ?? null, fn ($query, string $date) => $query->whereDate('booking_date', '>=', $date));
        $query->when($validated['to_date'] ?? null, fn ($query, string $date) => $query->whereDate('booking_date', '<=', $date));
        $query->when($validated['booking_type'] ?? null, fn ($query, string $type) => $query->where('booking_type', $type));

        if (! empty($validated['payment_status'])) {
            $paymentStatus = $validated['payment_status'];
            if ($paymentStatus === 'refunded') {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', 'refunded'));
            } elseif ($paymentStatus === 'not_required') {
                $query->where('payment_option', 'no_prepay');
            } else {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery->where('status', $paymentStatus));
            }
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            $this->applyStatusGroup($query, $validated['status_group'] ?? 'all');
        }

        $bookings = $query
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->through(fn (Booking $booking) => $this->historyPayload($booking));

        return response()->json($bookings);
    }

    public function recurringGroup(Request $request, string $groupCode)
    {
        $bookings = Booking::query()
            ->where('customer_id', $request->user()->id)
            ->where('booking_type', 'recurring')
            ->where('recurring_group_code', $groupCode)
            ->with([
                'venueCluster',
                'venueCourt.courtType',
                'items.venueCourt.courtType',
                'payments' => fn ($query) => $query->latest('created_at'),
                'refunds',
            ])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy nhóm lịch cố định.'], 404);
        }

        return response()->json([
            'group_code' => $groupCode,
            'summary' => [
                'cluster' => $bookings->first()->venueCluster?->only(['id', 'name']),
                'start_date' => $bookings->min('booking_date')?->toDateString() ?? $bookings->min('booking_date'),
                'end_date' => $bookings->max('booking_date')?->toDateString() ?? $bookings->max('booking_date'),
                'total' => $bookings->count(),
                'completed' => $bookings->where('status', 'completed')->count(),
                'cancelled' => $bookings->whereIn('status', ['cancelled', 'expired', 'rejected'])->count(),
                'upcoming' => $bookings->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in'])->count(),
                'total_amount' => (float) $bookings->sum('total_price'),
                'paid_amount' => (float) $bookings->flatMap->payments->where('status', 'paid')->sum('amount'),
            ],
            'items' => $bookings->map(fn (Booking $booking) => $this->historyPayload($booking))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_court_id' => 'required|exists:venue_courts,id',
            'booking_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.Carbon::now('Asia/Ho_Chi_Minh')->toDateString(),
            ],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => 'required|in:full_payment,deposit,wallet,no_prepay',
            'voucher_id' => 'nullable|integer|exists:vouchers,id',
            'voucher_code' => 'nullable|string|max:50',
            'venue_voucher_id' => 'nullable|integer|exists:vouchers,id',
            'venue_voucher_code' => 'nullable|string|max:50',
            'vip_voucher_id' => 'nullable|integer|exists:vouchers,id',
            'vip_voucher_code' => 'nullable|string|max:50',
            'time_ranges' => 'nullable|array',
            'time_ranges.*.venue_court_id' => 'required_with:time_ranges|exists:venue_courts,id',
            'time_ranges.*.start_time' => ['required_with:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'time_ranges.*.end_time' => ['required_with:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
        ]);
        $this->ensureValidTimeRange($validated['start_time'], $validated['end_time']);
        foreach ($validated['time_ranges'] ?? [] as $range) {
            $this->ensureValidTimeRange($range['start_time'], $range['end_time']);
        }

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
        $booking->load([
            'venueCourt.venueCluster',
            'venueCourt.courtType',
            'venueCluster',
            'items.venueCourt.courtType',
            'items.requestedVenueCourt.courtType',
            'payments.logs',
            'payments.userWallet',
            'refunds.statusHistories',
        ]);

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
        $bookingArray['paid_amount'] = (float) $booking->payments->where('status', 'paid')->sum('amount');
        $bookingArray['refunded_amount'] = (float) $booking->refunds->whereIn('status', [
            'completed', 'paid', 'refunded', 'admin_completed',
        ])->sum('amount');

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

    public function cancelPreview(Request $request, string $id)
    {
        $booking = Booking::query()
            ->with(['payments', 'venueCourt.venueCluster'])
            ->findOrFail($id);

        if ((string) $booking->customer_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem chính sách hủy booking này.'], 403);
        }

        return response()->json($this->refundCancellationPolicyService->evaluateBookingCancellation(
            $booking,
            $request->user(),
        ));
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

    private function applyStatusGroup($query, string $statusGroup): void
    {
        if ($statusGroup === 'upcoming') {
            $today = now()->toDateString();
            $currentTime = now()->format('H:i:s');

            $query->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in'])
                ->where(function ($query) use ($today, $currentTime) {
                    $query->whereDate('booking_date', '>', $today)
                        ->orWhere(function ($query) use ($today, $currentTime) {
                            $query->whereDate('booking_date', $today)
                                ->where('start_time', '>=', $currentTime);
                        });
                });

            return;
        }

        if ($statusGroup === 'completed') {
            $query->where('status', 'completed');

            return;
        }

        if ($statusGroup === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'expired', 'rejected']);

            return;
        }

        if ($statusGroup === 'refunded') {
            $query->whereHas('payments', fn ($query) => $query->where('status', 'refunded'));
        }
    }

    private function historyPayload(Booking $booking): array
    {
        $payments = $booking->payments;
        $latestPayment = $payments->first();
        $paidAmount = (float) $payments->where('status', 'paid')->sum('amount');
        $isRefunded = $payments->contains(fn ($payment) => $payment->status === 'refunded');
        $bookingDate = $booking->booking_date instanceof Carbon
            ? $booking->booking_date->toDateString()
            : $booking->booking_date;

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_type' => $booking->booking_type,
            'recurring_group_code' => $booking->recurring_group_code,
            'booking_date' => $bookingDate,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'duration_minutes' => (int) $booking->duration_minutes,
            'total_price' => (float) $booking->total_price,
            'required_payment_amount' => (float) $booking->required_payment_amount,
            'paid_amount' => $paidAmount,
            'payment_option' => $booking->payment_option,
            'payment_status' => $isRefunded
                ? 'refunded'
                : ($latestPayment?->status ?? ((float) $booking->required_payment_amount > 0 ? 'pending' : 'not_required')),
            'status' => $booking->status,
            'status_reason' => $booking->status_reason,
            'cancelled_at' => $booking->cancelled_at,
            'can_cancel' => $this->canCustomerCancel($booking),
            'venue_cluster' => $booking->venueCluster ?: $booking->venueCourt?->venueCluster,
            'venue_court' => $booking->venueCourt,
            'items' => $booking->items->values(),
            'refunds' => $booking->refunds->values(),
            'has_court_change' => $booking->items->contains(fn ($item) =>
                $item->requested_venue_court_id && $item->requested_venue_court_id !== $item->venue_court_id
            ),
            'has_partial_cancellation' => $booking->items->contains(fn ($item) =>
                in_array($item->status, ['cancelled', 'interrupted'], true)
            ),
        ];
    }

    private function canCustomerCancel(Booking $booking): bool
    {
        if (! in_array($booking->status, ['pending_approval', 'pending_payment', 'confirmed'], true)) {
            return false;
        }

        if (! $booking->booking_date || ! $booking->start_time) {
            return false;
        }

        $bookingDate = $booking->booking_date instanceof Carbon
            ? $booking->booking_date->toDateString()
            : (string) $booking->booking_date;
        $startAt = Carbon::parse($bookingDate.' '.substr($booking->start_time, 0, 8));

        return $startAt->isFuture();
    }
}
