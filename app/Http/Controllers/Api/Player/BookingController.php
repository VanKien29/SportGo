<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Services\Memberships\VenueMembershipService;
use App\Services\Policies\RefundCancellationPolicyService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                'playerPost.participants',
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
            'playerPost.participants',
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

    public function previewRecurring(Request $request)
    {
        $validated = $this->validateRecurringPayload($request);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureRecurringCluster($validated, $court);

        return response()->json([
            'message' => 'Đã kiểm tra lịch cố định.',
            'data' => $this->bookingService->previewRecurringConflicts($validated),
        ]);
    }

    public function storeRecurring(Request $request)
    {
        $validated = $this->validateRecurringPayload($request);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureRecurringCluster($validated, $court);

        $preview = $this->bookingService->previewRecurringConflicts($validated);
        if (! empty($preview['conflicts']) && empty($validated['conflict_resolution'])) {
            return response()->json([
                'message' => 'Một số buổi trong lịch cố định đã bị trùng. Vui lòng chọn cách xử lý.',
                ...$preview,
            ], 409);
        }

        $result = $this->bookingService->createRecurringBookings($validated, $request->user());

        return response()->json([
            'message' => 'Đã tạo booking cố định.',
            'data' => $result,
        ], 201);
    }

    public function changeCourt(Request $request, string $id)
    {
        $booking = Booking::query()
            ->with(['items', 'payments', 'venueCourt', 'venueCluster'])
            ->findOrFail($id);

        $this->assertCustomerOwnsBooking($request, $booking);
        $this->assertBookingCanBeEdited($booking);

        $validated = $request->validate([
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'court_changed_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($booking->items->count() !== 1) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Booking nhiều khung giờ cần được hỗ trợ đổi sân theo từng khung.',
            ]);
        }

        $item = $booking->items->first();
        $newCourt = VenueCourt::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->with('courtType')
            ->findOrFail($validated['venue_court_id']);

        if (! $this->bookingService->checkAvailability(
            $newCourt->id,
            $booking->booking_date->toDateString(),
            $item->start_time,
            $item->end_time,
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Sân mới đã có lịch hoặc đang được giữ trong khung giờ này.',
            ]);
        }

        DB::transaction(function () use ($booking, $item, $newCourt, $validated, $request): void {
            $booking->forceFill([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $validated['court_changed_reason'],
            ])->save();
            $item->forceFill([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $validated['court_changed_reason'],
            ])->save();
        });

        return response()->json([
            'message' => 'Đã đổi sân cho booking.',
            'data' => $booking->fresh(['venueCluster', 'venueCourt.courtType', 'items.venueCourt.courtType']),
        ]);
    }

    public function reschedule(Request $request, string $id)
    {
        $booking = Booking::query()
            ->with(['items', 'payments', 'venueCourt', 'venueCluster'])
            ->findOrFail($id);

        $this->assertCustomerOwnsBooking($request, $booking);
        $this->assertBookingCanBeEdited($booking);

        $validated = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->ensureValidTimeRange($validated['start_time'], $validated['end_time']);

        if ($booking->items->count() !== 1) {
            throw ValidationException::withMessages([
                'booking_date' => 'Booking nhiều khung giờ cần được hỗ trợ đổi lịch theo từng khung.',
            ]);
        }

        $item = $booking->items->first();
        $court = VenueCourt::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->with('courtType')
            ->findOrFail($validated['venue_court_id'] ?? $booking->venue_court_id);

        if (! $this->bookingService->meetsMinimumAdvanceNotice(
            (string) $booking->venue_cluster_id,
            $validated['booking_date'],
            $validated['start_time'],
        )) {
            throw ValidationException::withMessages([
                'booking_date' => 'Khung giờ mới chưa đủ thời gian đặt trước theo cấu hình sân.',
            ]);
        }

        if (! $this->bookingService->checkAvailability(
            $court->id,
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time'],
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'start_time' => 'Khung giờ mới đã có lịch hoặc đang được giữ.',
            ]);
        }

        $newOriginalAmount = $this->bookingService->calculateTotalPrice(
            $court,
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time'],
            'single',
        );
        $existingDiscount = max((float) ($booking->original_amount ?? $booking->total_price) - (float) $booking->total_price, 0);
        $newTotal = round(max($newOriginalAmount - min($existingDiscount, $newOriginalAmount), 0), 2);
        $requiredPayment = $this->bookingService->calculateRequiredPaymentAmount(
            (string) $booking->venue_cluster_id,
            $newTotal,
            (string) $booking->payment_option,
        );

        DB::transaction(function () use ($booking, $item, $court, $validated, $newOriginalAmount, $newTotal, $requiredPayment, $request): void {
            SlotLock::query()->where('booking_id', $booking->id)->delete();
            Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            $duration = $this->timeToMinutes($validated['end_time']) - $this->timeToMinutes($validated['start_time']);
            $booking->forceFill([
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'duration_minutes' => $duration,
                'venue_court_id' => $court->id,
                'requested_venue_court_id' => $court->id,
                'original_amount' => $newOriginalAmount,
                'total_price' => $newTotal,
                'final_amount' => $newTotal,
                'required_payment_amount' => $requiredPayment,
                'court_changed_by' => $court->id === $booking->venue_court_id ? $booking->court_changed_by : $request->user()->id,
                'court_changed_at' => $court->id === $booking->venue_court_id ? $booking->court_changed_at : now(),
                'court_changed_reason' => $validated['reason'],
            ])->save();
            $item->forceFill([
                'venue_court_id' => $court->id,
                'requested_venue_court_id' => $court->id,
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'duration_minutes' => $duration,
                'unit_price' => $duration > 0 ? round($newTotal / max($duration / 60, 0.5), 2) : 0,
                'subtotal' => $newTotal,
            ])->save();

            $booking->refresh()->load('items');
            $this->bookingService->ensurePendingPaymentLocks($booking, (string) $request->user()->id);
        });

        return response()->json([
            'message' => 'Đã đổi ngày và khung giờ booking.',
            'data' => $booking->fresh(['venueCluster', 'venueCourt.courtType', 'items.venueCourt.courtType', 'payments']),
        ]);
    }

    public function cancelItems(Request $request, string $id)
    {
        $validated = $request->validate([
            'booking_item_ids' => ['required', 'array', 'min:1'],
            'booking_item_ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $booking = Booking::query()->findOrFail($id);
        if ((string) $booking->customer_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền thay đổi booking này.'], 403);
        }

        $result = $this->refundCancellationPolicyService->cancelBookingItems(
            $booking,
            $request->user(),
            $validated['booking_item_ids'],
            $validated['reason'],
        );

        return response()->json([
            'message' => 'Đã hủy các khung giờ được chọn và tạo yêu cầu hoàn theo chính sách.',
            ...$result,
        ]);
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
            'venueCluster.venueCourts.courtType',
            'items.venueCourt.courtType',
            'items.requestedVenueCourt.courtType',
            'payments.logs',
            'payments.userWallet',
            'refunds.statusHistories',
            'playerPost.participants',
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

    private function validateRecurringPayload(Request $request): array
    {
        $validated = $request->validate([
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'recurring_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'recurring_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:recurring_start_date'],
            'recurrence_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:12'],
            'recurrence_days_of_week' => ['nullable', 'array'],
            'recurrence_days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
            'recurrence_days_of_month' => ['nullable', 'array'],
            'recurrence_days_of_month.*' => ['integer', 'between:1,31', 'distinct'],
            'start_time' => ['required_without:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required_without:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'time_ranges' => ['nullable', 'array', 'min:1', 'max:32'],
            'time_ranges.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'time_ranges.*.start_time' => ['required_with:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'time_ranges.*.end_time' => ['required_with:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'weekday_time_ranges' => ['nullable', 'array', 'max:7'],
            'weekday_time_ranges.*.day_of_week' => ['required_with:weekday_time_ranges', 'integer', 'between:0,6', 'distinct'],
            'weekday_time_ranges.*.time_ranges' => ['required_with:weekday_time_ranges', 'array', 'min:1', 'max:32'],
            'weekday_time_ranges.*.time_ranges.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'weekday_time_ranges.*.time_ranges.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'weekday_time_ranges.*.time_ranges.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'date_time_ranges' => ['nullable', 'array', 'max:130'],
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'venue_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'venue_voucher_code' => ['nullable', 'string', 'max:50'],
            'vip_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'vip_voucher_code' => ['nullable', 'string', 'max:50'],
            'conflict_resolution' => ['nullable', Rule::in(['abort', 'skip', 'mixed'])],
            'conflict_overrides' => ['nullable', 'array'],
            'conflict_overrides.*.date' => ['required_with:conflict_overrides', 'date_format:Y-m-d'],
            'conflict_overrides.*.key' => ['nullable', 'string', 'max:120'],
            'conflict_overrides.*.action' => ['required_with:conflict_overrides', Rule::in(['skip', 'switch'])],
            'conflict_overrides.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
        ]);

        if ($validated['recurrence_type'] === 'weekly' && empty($validated['recurrence_days_of_week'])) {
            throw ValidationException::withMessages([
                'recurrence_days_of_week' => 'Vui lòng chọn ít nhất một thứ trong tuần.',
            ]);
        }

        if ($validated['recurrence_type'] === 'monthly' && empty($validated['recurrence_days_of_month'])) {
            throw ValidationException::withMessages([
                'recurrence_days_of_month' => 'Vui lòng chọn ngày trong tháng.',
            ]);
        }

        $timeRanges = $validated['time_ranges'] ?? [[
            'venue_court_id' => $validated['venue_court_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]];
        foreach ($timeRanges as $index => $range) {
            $this->ensureValidTimeRange($range['start_time'], $range['end_time']);
            $timeRanges[$index]['venue_court_id'] = $range['venue_court_id'] ?: $validated['venue_court_id'];
        }
        $validated['time_ranges'] = $timeRanges;
        $validated['start_time'] = $timeRanges[0]['start_time'];
        $validated['end_time'] = $timeRanges[array_key_last($timeRanges)]['end_time'];

        if ($validated['recurrence_type'] === 'weekly' && empty($validated['weekday_time_ranges'])) {
            $validated['weekday_time_ranges'] = collect($validated['recurrence_days_of_week'])
                ->map(fn (int $day): array => [
                    'day_of_week' => $day,
                    'time_ranges' => $timeRanges,
                ])
                ->values()
                ->all();
        }

        return [
            ...$validated,
            'customer_id' => $request->user()->id,
            'source' => 'online',
            'initial_status' => $validated['payment_option'] === 'no_prepay' ? 'pending_approval' : 'pending_payment',
            'create_counter_payment' => false,
            'is_paid' => false,
        ];
    }

    private function ensureRecurringCluster(array $validated, VenueCourt $court): void
    {
        if ((string) $validated['venue_cluster_id'] !== (string) $court->venue_cluster_id) {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân không khớp với sân đang chọn.',
            ]);
        }
    }

    private function assertCustomerOwnsBooking(Request $request, Booking $booking): void
    {
        if ((string) $booking->customer_id !== (string) $request->user()->id) {
            abort(403, 'Bạn không có quyền thao tác với booking này.');
        }
    }

    private function assertBookingCanBeEdited(Booking $booking): void
    {
        if (! in_array($booking->status, ['pending_approval', 'pending_payment'], true)) {
            throw ValidationException::withMessages([
                'booking' => 'Chỉ booking đang chờ duyệt hoặc chờ thanh toán mới có thể đổi sân/đổi lịch trực tiếp.',
            ]);
        }

        if ($booking->payments->contains(fn ($payment): bool => $payment->status === 'paid')) {
            throw ValidationException::withMessages([
                'booking' => 'Booking đã có thanh toán thành công. Vui lòng gửi yêu cầu hỗ trợ để đổi lịch.',
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
            'matchmaking' => $this->matchmakingPayload($booking->playerPost),
            'has_court_change' => $booking->items->contains(fn ($item) =>
                $item->requested_venue_court_id && $item->requested_venue_court_id !== $item->venue_court_id
            ),
            'has_partial_cancellation' => $booking->items->contains(fn ($item) =>
                str_starts_with((string) $item->status, 'cancelled') || in_array($item->status, ['interrupted', 'interrupted_by_emergency'], true)
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

    private function matchmakingPayload($post): ?array
    {
        if (! $post) return null;

        $approved = $post->participants
            ->where('pivot.status', 'approved')
            ->values();
        $total = $approved->count() + (int) $post->needed_players;

        return [
            'id' => $post->id,
            'status' => $post->status,
            'needed_players' => (int) $post->needed_players,
            'approved_players' => $approved->count(),
            'total_players' => $total,
            'label' => sprintf('%d/%d người', $approved->count(), $total),
        ];
    }
}
