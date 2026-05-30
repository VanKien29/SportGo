<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingManagementController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

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
            ->with(['customer:id,username,full_name,phone,email', 'venueCourt.courtType', 'requestedVenueCourt', 'payments'])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->when(! empty($validated['venue_cluster_id']), fn ($query) => $query->where('venue_cluster_id', $validated['venue_cluster_id']))
            ->when(! empty($validated['venue_court_id']), fn ($query) => $query->where('venue_court_id', $validated['venue_court_id']))
            ->when(! empty($validated['booking_date']), fn ($query) => $query->where('booking_date', $validated['booking_date']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('booking_date')
            ->orderBy('start_time')
            ->limit(200)
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()
            ->with(['customer:id,username,full_name,phone,email', 'venueCluster', 'venueCourt.courtType', 'requestedVenueCourt', 'payments'])
            ->findOrFail($id);

        $this->ensureBookingAccess($request, $booking);

        return response()->json(['data' => $booking]);
    }

    public function storeCounter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'payment_option' => ['required', Rule::in(['full_payment', 'deposit', 'no_prepay'])],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
        ]);

        $court = VenueCourt::query()->with('venueCluster')->findOrFail($validated['venue_court_id']);
        $this->ensureClusterCanMutate($request, $court->venueCluster);

        $booking = $this->bookingService->createCounterBooking($validated, $request->user());

        return response()->json([
            'message' => 'Đã tạo booking tại quầy.',
            'data' => $booking->load(['venueCourt.courtType', 'customer']),
        ], 201);
    }

    public function storeRecurring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'recurring_start_date' => ['required', 'date_format:Y-m-d'],
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
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'walk_in_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'walk_in_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
        ]);

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
        $booking = Booking::query()->with('venueCluster')->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'reject', 'cancel', 'check_in', 'complete'])],
            'status_reason' => ['required_if:action,reject,cancel', 'nullable', 'string', 'max:1000'],
        ]);

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

        return response()->json([
            'message' => 'Đã cập nhật trạng thái booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'customer', 'payments']),
        ]);
    }

    public function changeCourt(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->with('venueCluster')->findOrFail($id);
        $this->ensureClusterCanMutate($request, $booking->venueCluster);

        $validated = $request->validate([
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'court_changed_reason' => ['required', 'string', 'max:1000'],
        ]);

        $newCourt = VenueCourt::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('status', 'active')
            ->findOrFail($validated['venue_court_id']);

        if (! $this->bookingService->checkAvailability(
            $newCourt->id,
            $booking->booking_date->toDateString(),
            $booking->start_time,
            $booking->end_time,
            $booking->id,
        )) {
            throw ValidationException::withMessages(['venue_court_id' => 'Sân mới đã bận trong khung giờ này.']);
        }

        $booking->update([
            'venue_court_id' => $newCourt->id,
            'court_changed_by' => $request->user()->id,
            'court_changed_at' => now(),
            'court_changed_reason' => $validated['court_changed_reason'],
        ]);

        return response()->json([
            'message' => 'Đã đổi sân thực tế cho booking.',
            'data' => $booking->fresh(['venueCourt.courtType', 'requestedVenueCourt', 'customer']),
        ]);
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
