<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired', 'rejected'])],
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
            ->when(! empty($validated['venue_court_id']), function ($query) use ($validated) {
                $courtId = $validated['venue_court_id'];

                $query->where(function ($courtQuery) use ($courtId) {
                    $courtQuery->where('venue_court_id', $courtId)
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('venue_court_id', $courtId));
                });
            })
            ->when(! empty($validated['booking_date']), fn ($query) => $query->where('booking_date', $validated['booking_date']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
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
            'payment_option' => ['required', Rule::in(['full_payment', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'sepay'])],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ], $this->walkInValidationMessages());

        if (($validated['payment_method'] ?? null) === 'sepay' && $validated['payment_option'] === 'no_prepay') {
            throw ValidationException::withMessages([
                'payment_method' => 'Thu sau bằng chuyển khoản sẽ được tạo ở bước thu tiền sau trận.',
            ]);
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

        $validated = $request->validate([
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'recurring_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'recurring_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:recurring_start_date'],
            'recurrence_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:12'],
            'recurrence_days_of_week' => ['nullable', 'array'],
            'recurrence_days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
            'recurrence_days_of_month' => ['nullable', 'array'],
            'recurrence_days_of_month.*' => ['integer', 'between:1,31', 'distinct'],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => ['required', Rule::in(['full_payment', 'deposit', 'no_prepay'])],
            'is_paid' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer'])],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM][\pL\pM\s.'-]*$/u"],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:15', 'regex:/^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/'],
        ], $this->walkInValidationMessages());

        if ($validated['recurrence_type'] === 'weekly' && empty($validated['recurrence_days_of_week'])) {
            throw ValidationException::withMessages(['recurrence_days_of_week' => 'Vui lòng chọn thứ trong tuần.']);
        }

        if ($validated['recurrence_type'] === 'monthly' && empty($validated['recurrence_days_of_month'])) {
            throw ValidationException::withMessages(['recurrence_days_of_month' => 'Vui lòng chọn ngày trong tháng.']);
        }

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

        $result = $this->bookingService->createRecurringBookings($validated, $request->user());

        return response()->json([
            'message' => 'Đã tạo booking cố định.',
            'data' => $result,
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with(['venueCluster', 'payments'])->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'reject', 'cancel', 'check_in', 'complete'])],
            'status_reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:1000'],
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

        $booking->update([
            'status' => $status,
            'status_reason' => $validated['status_reason'] ?? null,
            'cancelled_by' => in_array($status, ['cancelled', 'rejected'], true) ? $request->user()->id : $booking->cancelled_by,
            'cancelled_at' => in_array($status, ['cancelled', 'rejected'], true) ? now() : $booking->cancelled_at,
        ]);

        if (in_array($status, ['cancelled', 'rejected'], true)) {
            SlotLock::query()->where('booking_id', $booking->id)->delete();
        }

        return response()->json([
            'message' => 'Đã cập nhật trạng thái booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'customer', 'payments']),
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

}
