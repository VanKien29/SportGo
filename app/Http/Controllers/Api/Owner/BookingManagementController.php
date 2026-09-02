<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\Bookings\OwnerBookingCancellationService;
use App\Services\Bookings\BookingLifecycleService;
use App\Services\Bookings\BookingApprovalService;
use App\Services\Bookings\BookingCourtChangeService;
use App\Services\BookingService;
use App\Services\Customers\WalkInCustomerService;
use App\Services\Payments\SepayPaymentService;
use App\Services\VenueStaffAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BookingManagementController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SepayPaymentService $sepayPaymentService,
        private readonly OwnerBookingCancellationService $ownerBookingCancellationService,
        private readonly BookingApprovalService $bookingApprovals,
        private readonly BookingCourtChangeService $bookingCourtChanges,
        private readonly VenueStaffAccessService $venueStaffAccess,
        private readonly WalkInCustomerService $walkInCustomers,
        private readonly BookingLifecycleService $bookingLifecycle,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'integer', 'exists:venue_clusters,id'],
            'venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'no_show', 'cancelled', 'expired', 'rejected'])],
            'source' => ['nullable', Rule::in(['online', 'counter'])],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
            'recurring_group_code' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $bookingQuery = Booking::query()
            ->with([
                'customer:id,username,full_name,phone,email',
                'venueCourt.courtType',
                'requestedVenueCourt',
                'items.venueCourt.courtType',
                'payments',
                'slotLocks',
            ])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->when(! empty($validated['venue_cluster_id']), fn ($query) => $query->where('venue_cluster_id', $validated['venue_cluster_id']))
            ->when(! empty($validated['source']), fn ($query) => $query->where('source', $validated['source']))
            ->when(! empty($validated['booking_type']), fn ($query) => $query->where('booking_type', $validated['booking_type']))
            ->when(! empty($validated['recurring_group_code']), fn ($query) => $query->where('recurring_group_code', $validated['recurring_group_code']))
            ->when(! empty($validated['venue_court_id']), function ($query) use ($validated) {
                $courtId = $validated['venue_court_id'];

                $query->where(function ($courtQuery) use ($courtId) {
                    $courtQuery->where('venue_court_id', $courtId)
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('venue_court_id', $courtId));
                });
            })
            ->when(! empty($validated['booking_date']), fn ($query) => $query->where('booking_date', $validated['booking_date']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['q']), function ($query) use ($validated) {
                $keyword = trim($validated['q']);

                $query->where(function ($searchQuery) use ($keyword) {
                    $searchQuery->where('booking_code', 'like', "%{$keyword}%")
                        ->orWhere('walk_in_name', 'like', "%{$keyword}%")
                        ->orWhere('walk_in_phone', 'like', "%{$keyword}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($keyword) {
                            $customerQuery->where('username', 'like', "%{$keyword}%")
                                ->orWhere('full_name', 'like', "%{$keyword}%")
                                ->orWhere('phone', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%");
                        });
                });
            });

        $this->applyCourtScope($bookingQuery, $request, $clusterIds);

        $bookings = $bookingQuery
            ->orderByRaw("CASE status
                WHEN 'pending_approval' THEN 0
                WHEN 'pending_payment' THEN 1
                WHEN 'confirmed' THEN 2
                WHEN 'checked_in' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5
            END")
            ->orderByDesc('booking_date')
            ->orderBy('start_time')
            ->limit(200)
            ->get();

        $bookings->each(fn (Booking $booking) => $this->attachSettlementSummary($booking));

        return response()->json(['data' => $bookings]);
    }

    public function recurringGroups(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'integer', 'exists:venue_clusters,id'],
            'venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'no_show', 'cancelled', 'expired', 'rejected'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $bookingQuery = Booking::query()
            ->with([
                'customer:id,username,full_name,phone,email',
                'venueCluster:id,name',
                'venueCourt.courtType',
                'items.venueCourt.courtType',
                'payments',
            ])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->where('source', 'counter')
            ->where('booking_type', 'recurring')
            ->whereNotNull('recurring_group_code')
            ->when(! empty($validated['venue_cluster_id']), fn ($query) => $query->where('venue_cluster_id', $validated['venue_cluster_id']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['venue_court_id']), function ($query) use ($validated) {
                $courtId = $validated['venue_court_id'];

                $query->where(function ($courtQuery) use ($courtId) {
                    $courtQuery->where('venue_court_id', $courtId)
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('venue_court_id', $courtId));
                });
            })
            ->when(! empty($validated['q']), function ($query) use ($validated) {
                $keyword = trim($validated['q']);

                $query->where(function ($searchQuery) use ($keyword) {
                    $searchQuery->where('recurring_group_code', 'like', "%{$keyword}%")
                        ->orWhere('booking_code', 'like', "%{$keyword}%")
                        ->orWhere('walk_in_name', 'like', "%{$keyword}%")
                        ->orWhere('walk_in_phone', 'like', "%{$keyword}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($keyword) {
                            $customerQuery->where('username', 'like', "%{$keyword}%")
                                ->orWhere('full_name', 'like', "%{$keyword}%")
                                ->orWhere('phone', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%");
                        });
                });
            });

        $this->applyCourtScope($bookingQuery, $request, $clusterIds);

        $bookings = $bookingQuery
            ->orderByDesc('recurring_start_date')
            ->orderBy('start_time')
            ->limit(500)
            ->get()
            ->groupBy('recurring_group_code')
            ->map(fn (Collection $groupBookings): array => $this->recurringGroupPayload($groupBookings))
            ->sortByDesc('start_date')
            ->values();

        return response()->json(['data' => $bookings]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()
            ->with([
                'customer:id,username,full_name,phone,email',
                'venueCluster',
                'venueCourt.courtType',
                'requestedVenueCourt',
                'items.venueCourt.courtType',
                'payments',
                'slotLocks',
            ])
            ->findOrFail($id);

        $this->ensureBookingAccess($request, $booking);
        $this->assertBookingCourtAccess($request, $booking);

        $this->attachSettlementSummary($booking);

        return response()->json(['data' => $booking]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
        ]);

        abort_unless($this->visibleClusterIds($request->user()->id)->contains($validated['venue_cluster_id']), 403);

        $allowedCourtTypeIds = $this->venueStaffAccess->allowedCourtTypeIds(
            $request->user(),
            (string) $validated['venue_cluster_id']
        );

        if ($allowedCourtTypeIds !== null && empty($validated['court_type_id'])) {
            if ($allowedCourtTypeIds->isNotEmpty()) {
                $validated['court_type_id'] = $allowedCourtTypeIds->first();
            }
        }

        if ($allowedCourtTypeIds !== null && ! empty($validated['court_type_id'])) {
            abort_unless($allowedCourtTypeIds->contains((int) $validated['court_type_id']), 403);
        }

        return response()->json($this->bookingService->getAvailabilitySchedule(
            $validated['venue_cluster_id'],
            $validated['booking_date'],
            isset($validated['court_type_id']) ? (int) $validated['court_type_id'] : null,
            $validated['booking_type'] ?? 'single',
            includeBusyDetails: true,
        ));
    }

    public function eligibleVouchers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'usage_count' => ['nullable', 'integer', 'min:1', 'max:130'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'walk_in_phone' => ['nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ]);

        abort_unless($this->visibleClusterIds($request->user()->id)->contains($validated['venue_cluster_id']), 403);

        $court = VenueCourt::query()
            ->where('venue_cluster_id', $validated['venue_cluster_id'])
            ->findOrFail($validated['venue_court_id']);
        $this->venueStaffAccess->assertCourtAccess($request->user(), $court);

        $validated['venue_court_id'] = $court->id;

        if (empty($validated['customer_id'])) {
            $customer = $this->walkInCustomers->findByPhone($validated['walk_in_phone'] ?? null);
            $validated['customer_id'] = $customer?->id ?? 0;
        }

        return response()->json([
            'data' => $this->bookingService
                ->eligibleVouchersForCounterBooking($validated, $request->user())
                ->values(),
        ]);
    }

    public function storeCounter(Request $request): JsonResponse
    {
        $this->normalizeWalkInContact($request);

        $validated = $request->validate([
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'booking_date' => ['nullable', 'required_without:booking_dates', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday()],
            'booking_dates' => ['nullable', 'array', 'min:1', 'max:31'],
            'booking_dates.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday(), 'distinct'],
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
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'sepay'])],
            'voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ], $this->walkInValidationMessages());

        if (($validated['payment_method'] ?? null) === 'sepay' && $validated['payment_option'] === 'no_prepay') {
            throw ValidationException::withMessages([
                'payment_method' => 'Thu sau bằng chuyển khoản sẽ được tạo ở bước thu tiền sau trận.',
            ]);
        }

        $bookingDates = collect($validated['booking_dates'] ?? [$validated['booking_date']])
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $validated['booking_date'] = $bookingDates->first();

        if ($bookingDates->count() > 1 && ($validated['payment_method'] ?? null) === 'sepay') {
            throw ValidationException::withMessages([
                'payment_method' => 'Booking nhiều ngày chưa hỗ trợ một mã QR chung. Vui lòng chọn tiền mặt hoặc thu sau.',
            ]);
        }

        if (empty($validated['time_ranges'])) {
            if ($this->timeToMinutes($validated['start_time']) >= $this->timeToMinutes($validated['end_time'])) {
                throw ValidationException::withMessages(['end_time' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
            }
        } else {
            foreach ($validated['time_ranges'] as $index => $range) {
                if ($this->timeToMinutes($range['start_time']) >= $this->timeToMinutes($range['end_time'])) {
                    throw ValidationException::withMessages(["time_ranges.$index.end_time" => 'Giờ kết thúc phải sau giờ bắt đầu.']);
                }
            }
        }

        if (($validated['payment_method'] ?? null) === 'sepay') {
            $validated['is_paid'] = false;
        }

        $this->assertPayloadCourtAccess($request, $validated);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

        if ($bookingDates->count() > 1) {
            $bookings = $this->bookingService->createCounterBookingsForDates(
                $validated,
                $bookingDates,
                $request->user(),
            );

            return response()->json([
                'message' => "Đã tạo {$bookings->count()} booking tại quầy.",
                'data' => $bookings,
                'payment_qr' => null,
            ], 201);
        }

        $booking = $this->bookingService->createCounterBooking($validated, $request->user());
        $paymentQr = null;

        if (($validated['payment_method'] ?? null) === 'sepay') {
            try {
                $paymentQr = $this->sepayPaymentService->createCounterCollectionPayment(
                    $booking,
                    $request->user(),
                    (float) $booking->required_payment_amount,
                );
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'message' => 'Đã tạo booking tại quầy.',
            'data' => $booking->load(['venueCourt.courtType', 'customer']),
            'payment_qr' => $paymentQr,
        ], 201);
    }

    public function storeRecurring(Request $request): JsonResponse
    {
        $this->normalizeWalkInContact($request);

        $validated = $this->validateRecurringPayload($request);
        $this->assertPayloadCourtAccess($request, $validated);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureRecurringClusterMatchesSelected($validated, $court);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

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

    public function previewRecurring(Request $request): JsonResponse
    {
        $this->normalizeWalkInContact($request);

        $validated = $this->validateRecurringPayload($request, false);
        $this->assertPayloadCourtAccess($request, $validated);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureRecurringClusterMatchesSelected($validated, $court);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

        return response()->json([
            'message' => 'Đã kiểm tra lịch cố định.',
            'data' => $this->bookingService->previewRecurringConflicts($validated),
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'payments'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster, true);
        $this->assertBookingCourtAccess($request, $booking);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'reject', 'cancel', 'check_in', 'complete'])],
            'status_reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:1000'],
            'cancellation_reason_type' => ['nullable', Rule::in(['owner_maintenance', 'owner_emergency', 'venue_locked', 'admin_action'])],
        ]);

        // Một số booking trả sau được tạo trước khi trạng thái chờ duyệt được chuẩn hóa
        // và còn nằm ở pending_payment. Booking cọc mới luôn chờ chủ sân duyệt
        // ngay cả khi khách chưa chuyển cọc.
        $isPayLater = $booking->payment_option === 'no_prepay';
        $isDeposit = $booking->payment_option === 'deposit';
        $paidAmount = (float) $booking->payments
            ->where('status', 'paid')
            ->sum('amount');
        $depositPaid = $isDeposit
            && ((float) $booking->required_payment_amount <= 0
                || $paidAmount + 0.01 >= (float) $booking->required_payment_amount);
        $allowedActions = match ($booking->status) {
            'pending_approval' => ['confirm', 'reject', 'cancel'],
            'pending_payment' => ($isPayLater || $depositPaid)
                ? ['confirm', 'reject', 'cancel']
                : ['cancel'],
            'confirmed' => ['check_in', 'cancel'],
            'checked_in' => ['complete'],
            default => [],
        };

        if (! in_array($validated['action'], $allowedActions, true)) {
            throw ValidationException::withMessages([
                    'action' => 'Thao tác không hợp lệ với trạng thái hiện tại của booking.',
            ]);
        }

        if ($booking->status === 'pending_approval' && $this->bookingApprovals->expireIfDue($booking)) {
            throw ValidationException::withMessages([
                'action' => $this->bookingApprovals->approvalExpiredMessage($booking),
            ]);
        }

        $sessionStart = $booking->booking_date && $booking->start_time
            ? $this->businessDateTime($booking->booking_date->format('Y-m-d'), (string) $booking->start_time)
            : null;
        $sessionEnd = $booking->booking_date && $booking->end_time
            ? $this->businessDateTime($booking->booking_date->format('Y-m-d'), (string) $booking->end_time)
            : null;

        if ($validated['action'] === 'check_in' && $sessionStart && $sessionEnd) {
            $now = Carbon::now($this->businessTimezone());
            if ($now->lessThan($sessionStart->copy()->subMinutes(30)) || $now->greaterThan($sessionEnd->copy()->addMinutes(30))) {
                throw ValidationException::withMessages([
                    'action' => 'Chỉ có thể check-in từ 30 phút trước giờ bắt đầu đến 30 phút sau giờ kết thúc.',
                ]);
            }
        }

        if ($validated['action'] === 'check_in' && $this->bookingService->outstandingAmount($booking) > 0.009) {
            throw ValidationException::withMessages([
                'action' => 'Chỉ có thể check-in booking đã thanh toán đủ. Vui lòng thu tiền mặt hoặc tạo QR chuyển khoản trước.',
            ]);
        }

        if ($validated['action'] === 'complete' && $sessionEnd && Carbon::now($this->businessTimezone())->lessThan($sessionEnd->copy()->addMinutes(15))) {
            throw ValidationException::withMessages([
                'action' => 'Chưa thể hoàn thành booking trước khi buổi chơi kết thúc.',
            ]);
        }

        if ($validated['action'] === 'complete' && $this->bookingService->outstandingAmount($booking) > 0.009) {
            throw ValidationException::withMessages([
                'action' => 'Vui lòng thu đủ tiền trước khi hoàn thành booking.',
            ]);
        }

        if ($validated['action'] === 'cancel' && $booking->status === 'pending_payment' && $booking->payments->contains('status', 'pending')) {
            $result = $this->sepayPaymentService->cancelPendingPayment(
                $booking,
                $request->user()->id,
                $validated['status_reason'],
                'owner_payment_cancelled',
            );

            return response()->json([
                'message' => 'Đã hủy booking và vô hiệu giao dịch đang chờ.',
                'data' => $this->attachSettlementSummary($result['booking']->fresh(['venueCourt.courtType', 'customer', 'payments'])),
            ]);
        }

        if ($validated['action'] === 'confirm' && $booking->status === 'pending_approval') {
            $result = $this->bookingApprovals->approve($booking, $request->user());

            if ($result['expired'] ?? false) {
                throw ValidationException::withMessages([
                    'action' => $this->bookingApprovals->approvalExpiredMessage($booking),
                ]);
            }

            return response()->json([
                'message' => ($result['fallback_to_pay_later'] ?? false)
                    ? 'Đã duyệt booking và chuyển hình thức thanh toán sang trả sau.'
                    : 'Đã duyệt booking và xác nhận lịch chơi.',
                'data' => $this->attachSettlementSummary($result['booking']),
                'refunds' => [],
            ]);
        }

        $status = match ($validated['action']) {
            'confirm' => 'confirmed',
            'reject' => 'rejected',
            'cancel' => 'cancelled',
            'check_in' => 'checked_in',
            'complete' => 'completed',
        };

        if (in_array($status, ['cancelled', 'rejected'], true)) {
            $result = $this->ownerBookingCancellationService->cancelBooking(
                $booking,
                $request->user(),
                $validated['status_reason'],
                $status,
            );

            return response()->json([
                'message' => count($result['refunds'])
                    ? 'Đã hủy booking và hoàn số tiền đã thanh toán vào ví SportGo của khách.'
                    : 'Đã hủy booking.',
                'data' => $this->attachSettlementSummary($result['booking']),
                'refunds' => $result['refunds'],
            ]);
        }

        $booking->update([
            'status' => $status,
            'payment_deadline_at' => $status === 'confirmed' ? null : $booking->payment_deadline_at,
            'status_reason' => $validated['status_reason'] ?? null,
            'cancelled_by' => in_array($status, ['cancelled', 'rejected'], true) ? $request->user()->id : $booking->cancelled_by,
            'cancellation_initiator' => in_array($status, ['cancelled', 'rejected'], true) ? 'owner' : $booking->cancellation_initiator,
            'cancellation_reason_type' => in_array($status, ['cancelled', 'rejected'], true)
                ? ($validated['cancellation_reason_type'] ?? 'owner_maintenance')
                : $booking->cancellation_reason_type,
            'cancelled_at' => in_array($status, ['cancelled', 'rejected'], true) ? now() : $booking->cancelled_at,
        ]);

        if ($validated['action'] === 'confirm' && ($isPayLater || $isDeposit)) {
            // Lock auto chỉ có nhiệm vụ chờ owner duyệt; sau khi duyệt booking
            // phải được giữ bởi trạng thái booking thay vì một lock tạm.
            SlotLock::query()
                ->where('booking_id', $booking->id)
                ->where('lock_type', 'auto')
                ->delete();
        }

        $refunds = [];
        if ($status === 'completed') {
            $this->bookingService->syncMembershipForCompletedBooking($booking);
        }

        return response()->json([
            'message' => 'Đã cập nhật trạng thái booking.',
            'data' => $this->attachSettlementSummary($booking->fresh(['venueCourt.courtType', 'customer', 'payments'])),
            'refunds' => $refunds,
        ]);
    }

    public function courtOptions(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()
            ->with(['venueCluster', 'venueCourt.courtType', 'items.venueCourt.courtType'])
            ->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);
        $this->assertBookingCourtAccess($request, $booking);

        return response()->json([
            'data' => $this->bookingCourtChanges->availableCourts($booking, $request->user()),
        ]);
    }

    public function changeCourt(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'venueCourt.courtType', 'items.venueCourt.courtType'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);
        $this->assertBookingCourtAccess($request, $booking);

        if (! in_array($booking->status, ['pending_approval', 'pending_payment', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Chỉ có thể đổi sân trước khi khách check-in.',
            ]);
        }

        if ($booking->items->count() > 1) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Booking có nhiều khung sân. Vui lòng hủy và tạo lại để tránh thay đổi sai lịch.',
            ]);
        }

        $bookingStart = $booking->booking_date && $booking->start_time
            ? $this->businessDateTime($booking->booking_date->toDateString(), (string) $booking->start_time)
            : null;
        if ($bookingStart && Carbon::now($this->businessTimezone())->gte($bookingStart)) {
            throw ValidationException::withMessages([
                'venue_court_id' => 'Không thể đổi sân sau khi booking đã bắt đầu.',
            ]);
        }

        $validated = $request->validate([
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'court_changed_reason' => ['required', 'string', 'max:1000'],
        ]);

        $bookingItem = $booking->items->first();
        $oldCourt = $bookingItem?->venueCourt ?: $booking->venueCourt;
        $startTime = $bookingItem?->start_time ?? $booking->start_time;
        $endTime = $bookingItem?->end_time ?? $booking->end_time;
        $updatedBooking = $this->bookingCourtChanges->change(
            $booking,
            (int) $validated['venue_court_id'],
            $request->user(),
            $validated['court_changed_reason'],
        );
        $newCourt = $updatedBooking->venueCourt;

        $this->bookingLifecycle->notifyMatchmakingBookingChanged(
            $updatedBooking,
            'booking-court-switched-'.$booking->id.'-'.$oldCourt?->id.'-'.$newCourt?->id,
            'Kèo giao lưu đã đổi sân',
            "Booking gốc của bài giao lưu đã đổi từ {$oldCourt?->name} sang {$newCourt?->name}.",
            [
                'status' => $updatedBooking->status,
                'reason' => $validated['court_changed_reason'],
                'from_venue_court_id' => $oldCourt?->id,
                'from_venue_court_name' => $oldCourt?->name,
                'to_venue_court_id' => $newCourt?->id,
                'to_venue_court_name' => $newCourt?->name,
                'booking_date' => $updatedBooking->booking_date?->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
        );

        return response()->json([
            'message' => 'Đã đổi sân thực tế cho booking.',
            'data' => $updatedBooking,
        ]);

        /* Legacy inline implementation retained below only as a reference.
         * All requests now use BookingCourtChangeService above. */
        $newCourt = VenueCourt::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->where('court_type_id', $booking->venueCourt?->court_type_id)
            ->whereKeyNot($booking->venueCourt?->id)
            ->findOrFail($validated['venue_court_id']);
        $this->venueStaffAccess->assertCourtAccess($request->user(), $newCourt);

        $bookingItem = $booking->items->first();
        $startTime = $bookingItem?->start_time ?? $booking->start_time;
        $endTime = $bookingItem?->end_time ?? $booking->end_time;

        if (! $this->bookingService->checkAvailability(
            $newCourt->id,
            $booking->booking_date->toDateString(),
            $startTime,
            $endTime,
            $booking->id,
        )) {
            throw ValidationException::withMessages(['venue_court_id' => 'Sân mới đã bận trong khung giờ này.']);
        }

        DB::transaction(function () use ($booking, $bookingItem, $newCourt, $request, $validated): void {
            $booking->update([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $validated['court_changed_reason'],
            ]);

            $bookingItem?->update(['venue_court_id' => $newCourt->id]);
        });

        $this->bookingLifecycle->notifyMatchmakingBookingChanged(
            $booking,
            'booking-court-switched-'.$booking->id.'-'.$oldCourt?->id.'-'.$newCourt->id,
            'Kèo giao lưu được đổi sân',
            "Booking gốc của bài giao lưu được đổi từ {$oldCourt?->name} sang {$newCourt->name}.",
            [
                'status' => $booking->status,
                'reason' => $validated['court_changed_reason'],
                'from_venue_court_id' => $oldCourt?->id,
                'from_venue_court_name' => $oldCourt?->name,
                'to_venue_court_id' => $newCourt->id,
                'to_venue_court_name' => $newCourt->name,
                'booking_date' => $booking->booking_date?->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
        );

        return response()->json([
            'message' => 'Đã đổi sân thực tế cho booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer', 'items.venueCourt.courtType']),
        ]);
    }

    public function collectPayment(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'payments'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster, true);
        $this->assertBookingCourtAccess($request, $booking);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'sepay'])],
            'amount' => ['nullable', 'numeric', 'min:1000'],
        ]);

        if ($validated['payment_method'] === 'sepay') {
            try {
                $paymentQr = $this->sepayPaymentService->createCounterCollectionPayment(
                    $booking,
                    $request->user(),
                    isset($validated['amount']) ? (float) $validated['amount'] : null,
                );
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json([
                'message' => $paymentQr['reused']
                    ? 'Đã mở lại thông tin chuyển khoản đang chờ.'
                    : 'Đã tạo thông tin chuyển khoản.',
                'payment_qr' => $paymentQr,
                'data' => $this->attachSettlementSummary($booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer', 'payments'])),
            ]);
        }

        $updated = $this->bookingService->collectCounterPayment(
            $booking,
            $request->user(),
            $validated['payment_method'],
            isset($validated['amount']) ? (float) $validated['amount'] : null,
        );

        $collectedPayment = $updated->payments
            ->where('status', 'paid')
            ->sortByDesc('paid_at')
            ->first();
        if ($updated->customer_id && $collectedPayment) {
            try {
                broadcast(new \App\Events\BookingPaymentUpdated(
                    $updated->id,
                    $updated->customer_id,
                    $collectedPayment->id,
                    (string) $collectedPayment->status,
                    (string) $updated->status,
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => 'Đã ghi nhận thanh toán.',
            'data' => $this->attachSettlementSummary($updated),
        ]);
    }

    public function collectRecurringGroupPayment(Request $request, string $groupCode): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $groupBookings = Booking::query()
            ->with(['venueCourt', 'items.venueCourt'])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->where('source', 'counter')
            ->where('booking_type', 'recurring')
            ->where('recurring_group_code', $groupCode)
            ->get();

        abort_if($groupBookings->isEmpty(), 404);
        $groupBookings->each(
            fn (Booking $booking) => $this->assertBookingCourtAccess($request, $booking)
        );

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer'])],
            'amount' => ['nullable', 'numeric', 'min:1000'],
        ]);

        $result = $this->bookingService->collectRecurringGroupPayment(
            $groupCode,
            $request->user(),
            $validated['payment_method'],
            isset($validated['amount']) ? (float) $validated['amount'] : null,
        );

        return response()->json([
            'message' => 'Đã ghi nhận thu tiền nhóm lịch cố định.',
            'data' => $this->recurringGroupPayload($result['bookings']),
        ]);
    }

    private function normalizeWalkInContact(Request $request): void
    {
        if ($request->has('walk_in_name')) {
            $name = preg_replace('/\s+/u', ' ', trim((string) $request->input('walk_in_name')));
            $request->merge(['walk_in_name' => $name]);
        }

        if ($request->has('walk_in_phone')) {
            $phone = preg_replace('/[\s().-]+/', '', trim((string) $request->input('walk_in_phone')));
            $request->merge(['walk_in_phone' => $phone]);
        }
    }

    private function walkInValidationMessages(): array
    {
        return [
            'walk_in_name.required_without' => 'Vui lòng nhập tên khách.',
            'walk_in_name.min' => 'Tên khách phải có ít nhất 2 ký tự.',
            'walk_in_name.max' => 'Tên khách không được vượt quá 100 ký tự.',
            'walk_in_name.regex' => 'Tên khách chỉ được chứa chữ cái, khoảng trắng, dấu chấm, dấu nháy hoặc gạch nối.',
            'walk_in_phone.required_without' => 'Vui lòng nhập số điện thoại khách.',
            'walk_in_phone.max' => 'Số điện thoại không hợp lệ.',
            'walk_in_phone.regex' => 'Số điện thoại phải là số Việt Nam hợp lệ, ví dụ 0901234567.',
        ];
    }

    private function validateRecurringPayload(Request $request, bool $allowConflictResolution = true): array
    {
        $rules = [
            'venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'recurring_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday()],
            'recurring_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:recurring_start_date'],
            'recurrence_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:12'],
            'recurrence_days_of_week' => ['nullable', 'array'],
            'recurrence_days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
            'recurrence_days_of_month' => ['nullable', 'array'],
            'recurrence_days_of_month.*' => ['integer', 'between:1,31', 'distinct'],
            'recurring_dates' => ['nullable', 'array', 'min:1', 'max:130'],
            'recurring_dates.*' => ['date_format:Y-m-d', 'after_or_equal:'.$this->businessToday(), 'distinct'],
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
            'date_time_ranges.*.date' => ['required_with:date_time_ranges', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday(), 'distinct'],
            'date_time_ranges.*.time_ranges' => ['required_with:date_time_ranges', 'array', 'min:1', 'max:32'],
            'date_time_ranges.*.time_ranges.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            'date_time_ranges.*.time_ranges.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'date_time_ranges.*.time_ranges.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer'])],
            'voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ];

        if ($allowConflictResolution) {
            $rules += [
                'conflict_resolution' => ['nullable', Rule::in(['abort', 'skip', 'mixed'])],
                'conflict_overrides' => ['nullable', 'array'],
                'conflict_overrides.*.date' => ['required_with:conflict_overrides', 'date_format:Y-m-d'],
                'conflict_overrides.*.key' => ['nullable', 'string', 'max:120'],
                'conflict_overrides.*.action' => ['required_with:conflict_overrides', Rule::in(['skip', 'switch'])],
                'conflict_overrides.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
            ];
        } else {
            $rules['walk_in_name'] = ['nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"];
            $rules['walk_in_phone'] = ['nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'];
        }

        $validated = $request->validate($rules, $this->walkInValidationMessages());

        $usesExplicitDates = ! empty($validated['recurring_dates']);

        if ($usesExplicitDates) {
            $selectedDates = collect($validated['recurring_dates'])->sort()->values();
            $configuredDates = collect($validated['date_time_ranges'] ?? [])->pluck('date')->sort()->values();

            if ($configuredDates->diff($selectedDates)->isNotEmpty() || $selectedDates->diff($configuredDates)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'date_time_ranges' => 'Mỗi ngày đã chọn cần có sân và khung giờ riêng.',
                ]);
            }

            $validated['recurring_dates'] = $selectedDates->all();
            $validated['recurring_start_date'] = $selectedDates->first();
            $validated['recurring_end_date'] = $selectedDates->last();
        }

        if (! $usesExplicitDates && $validated['recurrence_type'] === 'weekly' && empty($validated['recurrence_days_of_week'])) {
            throw ValidationException::withMessages(['recurrence_days_of_week' => 'Vui lòng chọn thứ trong tuần.']);
        }

        if (! $usesExplicitDates && $validated['recurrence_type'] === 'weekly' && empty($validated['weekday_time_ranges'])) {
            throw ValidationException::withMessages(['weekday_time_ranges' => 'Mỗi thứ đã chọn cần có sân và khung giờ riêng.']);
        }

        if (! $usesExplicitDates && $validated['recurrence_type'] === 'weekly' && ! empty($validated['weekday_time_ranges'])) {
            $configuredDays = collect($validated['weekday_time_ranges'])->pluck('day_of_week')->sort()->values();
            $selectedDays = collect($validated['recurrence_days_of_week'] ?? [])->sort()->values();

            if ($configuredDays->diff($selectedDays)->isNotEmpty() || $selectedDays->diff($configuredDays)->isNotEmpty()) {
                throw ValidationException::withMessages(['weekday_time_ranges' => 'Mỗi thứ đã chọn cần có sân và khung giờ riêng.']);
            }
        }

        if (! $usesExplicitDates && $validated['recurrence_type'] === 'monthly' && empty($validated['recurrence_days_of_month'])) {
            throw ValidationException::withMessages(['recurrence_days_of_month' => 'Vui lòng chọn ngày trong tháng.']);
        }

        if (empty($validated['time_ranges']) && $this->timeToMinutes($validated['start_time']) >= $this->timeToMinutes($validated['end_time'])) {
            throw ValidationException::withMessages(['end_time' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
        }

        if (! empty($validated['time_ranges'])) {
            foreach ($validated['time_ranges'] as $index => $range) {
                if ($this->timeToMinutes($range['start_time']) >= $this->timeToMinutes($range['end_time'])) {
                    throw ValidationException::withMessages(["time_ranges.$index.end_time" => 'Giờ kết thúc phải sau giờ bắt đầu.']);
                }
            }
        }

        foreach ($validated['date_time_ranges'] ?? [] as $dateIndex => $dateGroup) {
            foreach ($dateGroup['time_ranges'] as $rangeIndex => $range) {
                if ($this->timeToMinutes($range['start_time']) >= $this->timeToMinutes($range['end_time'])) {
                    throw ValidationException::withMessages([
                        "date_time_ranges.$dateIndex.time_ranges.$rangeIndex.end_time" => 'Giờ kết thúc phải sau giờ bắt đầu.',
                    ]);
                }
            }
        }

        return $validated;
    }

    private function ensureRecurringClusterMatchesSelected(array $validated, VenueCourt $court): void
    {
        if (($validated['venue_cluster_id'] ?? null) !== $court->venue_cluster_id) {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Lịch cố định chỉ được tạo trong cụm sân đang chọn.',
            ]);
        }
    }

    private function ensureBookingAccess(Request $request, Booking $booking): void
    {
        abort_unless($this->visibleClusterIds($request->user()->id)->contains($booking->venue_cluster_id), 403);
    }

    private function ensureClusterCanMutate(Request $request, VenueCluster $cluster, bool $allowLockedExisting = false): void
    {
        abort_unless($this->visibleClusterIds($request->user()->id)->contains($cluster->id), 403);

        if ($cluster->status === 'locked' && ! $allowLockedExisting) {
            $reason = trim((string) $cluster->status_reason);
            throw ValidationException::withMessages([
                'venue_cluster_id' => $reason !== ''
                    ? 'Cụm sân đang bị khóa. Lý do: '.rtrim($reason, ' .').'.'
                    : 'Cụm sân đang bị khóa và không thể thực hiện thao tác này.',
            ]);
        }
    }

    private function visibleClusterIds(string $userId): Collection
    {
        $ownedClusterIds = DB::table('venue_clusters')
            ->where('owner_id', $userId)
            ->pluck('id');

        $assignedClusterIds = DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $ownedClusterIds
            ->merge($assignedClusterIds)
            ->unique()
            ->values();
    }

    private function applyCourtScope($query, Request $request, Collection $clusterIds): void
    {
        if (! $this->venueStaffAccess->isStaff($request->user())) {
            return;
        }

        $query->where(function ($scoped) use ($clusterIds, $request): void {
            foreach ($clusterIds as $clusterId) {
                $allowed = $this->venueStaffAccess->allowedCourtTypeIds($request->user(), (string) $clusterId);

                $scoped->orWhere(function ($clusterQuery) use ($clusterId, $allowed): void {
                    $clusterQuery->where('venue_cluster_id', $clusterId);

                    if ($allowed !== null) {
                        $clusterQuery->where(function ($courtQuery) use ($allowed): void {
                            $courtQuery
                                ->whereHas('venueCourt', fn ($q) => $q->whereIn('court_type_id', $allowed))
                                ->orWhereHas('items.venueCourt', fn ($q) => $q->whereIn('court_type_id', $allowed));
                        });
                    }
                });
            }
        });
    }

    private function assertPayloadCourtAccess(Request $request, array $payload): void
    {
        $courtIds = collect([$payload['venue_court_id'] ?? null])
            ->merge(collect($payload['time_ranges'] ?? [])->pluck('venue_court_id'))
            ->merge(collect($payload['weekday_time_ranges'] ?? [])
                ->flatMap(fn (array $day) => collect($day['time_ranges'] ?? [])->pluck('venue_court_id')))
            ->merge(collect($payload['date_time_ranges'] ?? [])
                ->flatMap(fn (array $date) => collect($date['time_ranges'] ?? [])->pluck('venue_court_id')))
            ->filter()
            ->unique()
            ->values();

        if ($courtIds->isEmpty()) {
            return;
        }

        VenueCourt::query()
            ->whereIn('id', $courtIds)
            ->get()
            ->each(fn (VenueCourt $court) => $this->venueStaffAccess->assertCourtAccess($request->user(), $court));
    }

    private function assertBookingCourtAccess(Request $request, Booking $booking): void
    {
        $booking->loadMissing(['venueCourt', 'items.venueCourt']);

        collect([$booking->venueCourt])
            ->merge($booking->items->pluck('venueCourt'))
            ->filter()
            ->unique('id')
            ->each(fn (VenueCourt $court) => $this->venueStaffAccess->assertCourtAccess($request->user(), $court));
    }

    private function timeToMinutes(string $time): int
    {
        if (str_starts_with($time, '24:00')) {
            return 24 * 60;
        }
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
    }

    private function businessToday(): string
    {
        return Carbon::now($this->businessTimezone())->toDateString();
    }

    private function businessDateTime(string $date, string $time): Carbon
    {
        $normalizedTime = substr($time, 0, 8);

        if ($normalizedTime === '24:00:00') {
            return Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $this->businessTimezone())->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$normalizedTime, $this->businessTimezone());
    }

    private function recurringGroupPayload(Collection $bookings): array
    {
        $first = $bookings->sortBy('booking_date')->first();
        $paidAmount = round($bookings->sum(fn (Booking $booking): float => (float) $booking->payments->where('status', 'paid')->sum('amount')), 2);
        $totalPrice = round($bookings->sum(fn (Booking $booking): float => (float) $booking->total_price), 2);
        $requiredAmount = round($bookings->sum(fn (Booking $booking): float => (float) $booking->required_payment_amount), 2);
        $courtNames = $bookings
            ->flatMap(function (Booking $booking): array {
                if ($booking->items->isNotEmpty()) {
                    return $booking->items->map(fn ($item) => $item->venueCourt?->name)->filter()->all();
                }

                return [$booking->venueCourt?->name];
            })
            ->filter()
            ->unique()
            ->values();
        $timeRanges = $bookings
            ->flatMap(function (Booking $booking): array {
                if ($booking->items->isNotEmpty()) {
                    return $booking->items
                        ->map(fn ($item): array => [
                            'venue_court_id' => $item->venue_court_id,
                            'court_name' => $item->venueCourt?->name,
                            'start_time' => $item->start_time,
                            'end_time' => $item->end_time,
                        ])
                        ->all();
                }

                return [[
                    'venue_court_id' => $booking->venue_court_id,
                    'court_name' => $booking->venueCourt?->name,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                ]];
            })
            ->unique(fn (array $range): string => implode('|', [
                $range['venue_court_id'],
                $range['start_time'],
                $range['end_time'],
            ]))
            ->sortBy(fn (array $range): string => sprintf(
                '%s|%s',
                $range['court_name'] ?? '',
                $range['start_time'] ?? '',
            ))
            ->values();
        $statusCounts = $bookings->groupBy('status')->map->count();
        $paymentOptions = $bookings->pluck('payment_option')->unique()->values();
        $occurrences = $bookings
            ->sortBy(fn (Booking $booking): string => $booking->booking_date->toDateString().' '.($booking->start_time ?? ''))
            ->map(function (Booking $booking): array {
                $items = $booking->items->isNotEmpty()
                    ? $booking->items
                    : collect([(object) [
                        'id' => null,
                        'venue_court_id' => $booking->venue_court_id,
                        'venueCourt' => $booking->venueCourt,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'status' => 'active',
                        'status_reason' => null,
                        'subtotal' => $booking->total_price,
                    ]]);

                $itemPayload = $items
                    ->map(fn ($item): array => [
                        'id' => $item->id,
                        'venue_court_id' => $item->venue_court_id,
                        'court_name' => $item->venueCourt?->name,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                        'status' => $item->status ?: 'active',
                        'status_reason' => $item->status_reason,
                        'subtotal' => (float) $item->subtotal,
                        'interrupted_at' => $item->interrupted_at,
                        'played_minutes' => $item->played_minutes,
                        'remaining_minutes' => $item->remaining_minutes,
                        'incident_resolution' => $item->incident_resolution,
                    ])
                    ->values();
                $cancelledItems = $itemPayload->filter(fn (array $item): bool => str_starts_with((string) $item['status'], 'cancelled_')
                    || $item['status'] === 'interrupted_by_emergency');
                $activeItems = $itemPayload->reject(fn (array $item): bool => str_starts_with((string) $item['status'], 'cancelled_')
                    || $item['status'] === 'interrupted_by_emergency');
                $settlement = $this->bookingService->settlementSummary($booking);

                return [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'booking_date' => $booking->booking_date->toDateString(),
                    'status' => $booking->status,
                    'status_reason' => $booking->status_reason,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'total_price' => (float) $booking->total_price,
                    'paid_amount' => $settlement['paid_amount'],
                    'outstanding_amount' => $settlement['outstanding_amount'],
                    'settlement_status' => $settlement['status'],
                    'settlement_status_label' => $settlement['label'],
                    'settlement_due_at' => $settlement['due_at'],
                    'settlement_overdue' => $settlement['is_overdue'],
                    'items' => $itemPayload,
                    'active_item_count' => $activeItems->count(),
                    'cancelled_item_count' => $cancelledItems->count(),
                    'has_cancelled_by_maintenance' => $cancelledItems->contains(fn (array $item): bool => $item['status'] === 'cancelled_by_maintenance'),
                    'has_interrupted_by_emergency' => $cancelledItems->contains(fn (array $item): bool => $item['status'] === 'interrupted_by_emergency'),
                ];
            })
            ->values();
        $itemStatusCounts = $occurrences
            ->flatMap(fn (array $occurrence): array => $occurrence['items']->all())
            ->groupBy(fn (array $item): string => $item['status'] ?: 'active')
            ->map->count();

        return [
            'recurring_group_code' => $first->recurring_group_code,
            'booking_ids' => $bookings->pluck('id')->values(),
            'booking_count' => $bookings->count(),
            'start_date' => $bookings->min(fn (Booking $booking): string => $booking->booking_date->toDateString()),
            'end_date' => $bookings->max(fn (Booking $booking): string => $booking->booking_date->toDateString()),
            'start_time' => $first->start_time,
            'end_time' => $first->end_time,
            'recurrence_type' => $first->recurrence_type,
            'recurrence_interval' => $first->recurrence_interval,
            'recurrence_days_of_week' => $first->recurrence_days_of_week,
            'recurrence_days_of_month' => $first->recurrence_days_of_month,
            'recurring_dates' => $occurrences->pluck('booking_date')->unique()->values(),
            'venue_cluster_id' => $first->venue_cluster_id,
            'venue_cluster_name' => $first->venueCluster?->name,
            'court_names' => $courtNames,
            'time_ranges' => $timeRanges,
            'customer' => $first->customer ? [
                'id' => $first->customer->id,
                'username' => $first->customer->username,
                'full_name' => $first->customer->full_name,
                'phone' => $first->customer->phone,
                'email' => $first->customer->email,
            ] : null,
            'walk_in_name' => $first->walk_in_name,
            'walk_in_phone' => $first->walk_in_phone,
            'payment_option' => $paymentOptions->count() === 1 ? $paymentOptions->first() : 'mixed',
            'total_price' => $totalPrice,
            'required_payment_amount' => $requiredAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => max(round($totalPrice - $paidAmount, 2), 0),
            'status_counts' => $statusCounts,
            'item_status_counts' => $itemStatusCounts,
            'occurrences' => $occurrences,
            'has_conflict_sensitive_items' => $bookings->contains(fn (Booking $booking): bool => in_array($booking->status, ['pending_payment', 'confirmed', 'checked_in'], true)),
        ];
    }

    private function attachSettlementSummary(Booking $booking): Booking
    {
        $summary = $this->bookingService->settlementSummary($booking);

        $booking->setAttribute('settlement_status', $summary['status']);
        $booking->setAttribute('settlement_status_label', $summary['label']);
        $booking->setAttribute('paid_amount', $summary['paid_amount']);
        $booking->setAttribute('outstanding_amount', $summary['outstanding_amount']);
        $booking->setAttribute('settlement_due_at', $summary['due_at']);
        $booking->setAttribute('settlement_overdue', $summary['is_overdue']);

        return $booking;
    }

}
