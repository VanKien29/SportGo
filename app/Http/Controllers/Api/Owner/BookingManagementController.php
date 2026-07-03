<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\Bookings\OwnerBookingCancellationService;
use App\Services\BookingService;
use App\Services\Payments\SepayPaymentService;
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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired', 'rejected'])],
            'source' => ['nullable', Rule::in(['online', 'counter'])],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
            'recurring_group_code' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $bookings = Booking::query()
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
            })
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

        return response()->json(['data' => $bookings]);
    }

    public function recurringGroups(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired', 'rejected'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $bookings = Booking::query()
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
            })
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

        return response()->json(['data' => $booking]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
        ]);

        abort_unless($this->visibleClusterIds($request->user()->id)->contains($validated['venue_cluster_id']), 403);

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
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'booking_type' => ['nullable', Rule::in(['single', 'recurring'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'usage_count' => ['nullable', 'integer', 'min:1', 'max:130'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        abort_unless($this->visibleClusterIds($request->user()->id)->contains($validated['venue_cluster_id']), 403);

        $court = VenueCourt::query()
            ->where('venue_cluster_id', $validated['venue_cluster_id'])
            ->findOrFail($validated['venue_court_id']);

        $validated['venue_court_id'] = $court->id;

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
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required_without:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required_without:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'time_ranges' => ['nullable', 'array', 'min:1', 'max:32'],
            'time_ranges.*.venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'time_ranges.*.start_time' => ['required_with:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'time_ranges.*.end_time' => ['required_with:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'weekday_time_ranges' => ['nullable', 'array', 'max:7'],
            'weekday_time_ranges.*.day_of_week' => ['required_with:weekday_time_ranges', 'integer', 'between:0,6', 'distinct'],
            'weekday_time_ranges.*.time_ranges' => ['required_with:weekday_time_ranges', 'array', 'min:1', 'max:32'],
            'weekday_time_ranges.*.time_ranges.*.venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'weekday_time_ranges.*.time_ranges.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'weekday_time_ranges.*.time_ranges.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'sepay'])],
            'voucher_id' => ['nullable', 'uuid', 'exists:vouchers,id'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ], $this->walkInValidationMessages());

        if (($validated['payment_method'] ?? null) === 'sepay' && $validated['payment_option'] === 'no_prepay') {
            throw ValidationException::withMessages([
                'payment_method' => 'Thu sau bằng chuyển khoản sẽ được tạo ở bước thu tiền sau trận.',
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

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

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
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'reject', 'cancel', 'check_in', 'complete'])],
            'status_reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:1000'],
            'cancellation_reason_type' => ['nullable', Rule::in(['owner_maintenance', 'owner_emergency', 'venue_locked', 'admin_action'])],
        ]);

        $allowedActions = match ($booking->status) {
            'pending_approval' => ['confirm', 'reject', 'cancel'],
            'pending_payment' => ['cancel'],
            'confirmed' => ['check_in', 'cancel'],
            'checked_in' => ['complete'],
            default => [],
        };

        if (! in_array($validated['action'], $allowedActions, true)) {
            throw ValidationException::withMessages([
                'action' => 'Thao tác không hợp lệ với trạng thái hiện tại của booking.',
            ]);
        }

        if (
            $validated['action'] === 'complete'
            && $booking->source === 'counter'
            && $this->bookingService->outstandingAmount($booking) > 0
        ) {
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
                'data' => $result['booking']->fresh(['venueCourt.courtType', 'customer', 'payments']),
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
                    ? 'Đã hủy booking và tạo yêu cầu hoàn tiền chờ admin chuyển khoản.'
                    : 'Đã hủy booking.',
                'data' => $result['booking'],
                'refunds' => $result['refunds'],
            ]);
        }

        $booking->update([
            'status' => $status,
            'status_reason' => $validated['status_reason'] ?? null,
            'cancelled_by' => in_array($status, ['cancelled', 'rejected'], true) ? $request->user()->id : $booking->cancelled_by,
            'cancellation_initiator' => in_array($status, ['cancelled', 'rejected'], true) ? 'owner' : $booking->cancellation_initiator,
            'cancellation_reason_type' => in_array($status, ['cancelled', 'rejected'], true)
                ? ($validated['cancellation_reason_type'] ?? 'owner_maintenance')
                : $booking->cancellation_reason_type,
            'cancelled_at' => in_array($status, ['cancelled', 'rejected'], true) ? now() : $booking->cancelled_at,
        ]);

        $refunds = [];
        if ($status === 'completed') {
            $this->bookingService->syncMembershipForCompletedBooking($booking);
        }

        return response()->json([
            'message' => 'Đã cập nhật trạng thái booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'customer', 'payments']),
            'refunds' => $refunds,
        ]);
    }

    public function changeCourt(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'items'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

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

        $validated = $request->validate([
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'court_changed_reason' => ['required', 'string', 'max:1000'],
        ]);

        $newCourt = VenueCourt::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->findOrFail($validated['venue_court_id']);

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

        return response()->json([
            'message' => 'Đã đổi sân thực tế cho booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer', 'items.venueCourt.courtType']),
        ]);
    }

    public function collectPayment(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'payments'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

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
                'data' => $booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer', 'payments']),
            ]);
        }

        $updated = $this->bookingService->collectCounterPayment(
            $booking,
            $request->user(),
            $validated['payment_method'],
            isset($validated['amount']) ? (float) $validated['amount'] : null,
        );

        return response()->json([
            'message' => 'Đã ghi nhận thu tiền tại quầy.',
            'data' => $updated,
        ]);
    }

    public function collectRecurringGroupPayment(Request $request, string $groupCode): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $exists = Booking::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->where('source', 'counter')
            ->where('booking_type', 'recurring')
            ->where('recurring_group_code', $groupCode)
            ->exists();

        abort_unless($exists, 404);

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
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
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
            'time_ranges.*.venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'time_ranges.*.start_time' => ['required_with:time_ranges', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'time_ranges.*.end_time' => ['required_with:time_ranges', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer'])],
            'voucher_id' => ['nullable', 'uuid', 'exists:vouchers,id'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ];

        if ($allowConflictResolution) {
            $rules += [
                'conflict_resolution' => ['nullable', Rule::in(['abort', 'skip', 'mixed'])],
                'conflict_overrides' => ['nullable', 'array'],
                'conflict_overrides.*.date' => ['required_with:conflict_overrides', 'date_format:Y-m-d'],
                'conflict_overrides.*.action' => ['required_with:conflict_overrides', Rule::in(['skip', 'switch'])],
                'conflict_overrides.*.venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            ];
        } else {
            $rules['walk_in_name'] = ['nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"];
            $rules['walk_in_phone'] = ['nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'];
        }

        $validated = $request->validate($rules, $this->walkInValidationMessages());

        if ($validated['recurrence_type'] === 'weekly' && empty($validated['recurrence_days_of_week'])) {
            throw ValidationException::withMessages(['recurrence_days_of_week' => 'Vui lòng chọn thứ trong tuần.']);
        }

        if ($validated['recurrence_type'] === 'weekly' && ! empty($validated['weekday_time_ranges'])) {
            $configuredDays = collect($validated['weekday_time_ranges'])->pluck('day_of_week')->sort()->values();
            $selectedDays = collect($validated['recurrence_days_of_week'] ?? [])->sort()->values();

            if ($configuredDays->diff($selectedDays)->isNotEmpty() || $selectedDays->diff($configuredDays)->isNotEmpty()) {
                throw ValidationException::withMessages(['weekday_time_ranges' => 'Mỗi thứ đã chọn cần có sân và khung giờ riêng.']);
            }
        }

        if ($validated['recurrence_type'] === 'monthly' && empty($validated['recurrence_days_of_month'])) {
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

    private function ensureClusterCanMutate(Request $request, VenueCluster $cluster): void
    {
        abort_unless($this->visibleClusterIds($request->user()->id)->contains($cluster->id), 403);

        if ($cluster->status === 'locked') {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.',
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

    private function timeToMinutes(string $time): int
    {
        if (str_starts_with($time, '24:00')) {
            return 24 * 60;
        }
        [$hours, $minutes] = explode(':', $time);
        return (int) $hours * 60 + (int) $minutes;
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
            ->sortBy(fn (Booking $booking): string => $booking->booking_date->toDateString() . ' ' . ($booking->start_time ?? ''))
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

                return [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'booking_date' => $booking->booking_date->toDateString(),
                    'status' => $booking->status,
                    'status_reason' => $booking->status_reason,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'total_price' => (float) $booking->total_price,
                    'paid_amount' => (float) $booking->payments->where('status', 'paid')->sum('amount'),
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

}
