<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\BookingConfig;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        $clusters = VenueCluster::query()
            ->with('bookingConfig')
            ->whereIn('id', $clusterIds)
            ->orderBy('name')
            ->get()
            ->map(fn (VenueCluster $cluster) => [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'booking_config' => $cluster->bookingConfig ?: [
                    'venue_cluster_id' => $cluster->id,
                    'min_duration_minutes' => 30,
                    'max_duration_minutes' => null,
                ],
            ]);

        $courtTypes = DB::table('venue_courts')
            ->join('court_types', 'court_types.id', '=', 'venue_courts.court_type_id')
            ->whereIn('venue_courts.venue_cluster_id', $clusterIds)
            ->whereNull('venue_courts.deleted_at')
            ->whereNull('court_types.deleted_at')
            ->where('court_types.is_active', true)
            ->select(['venue_courts.venue_cluster_id', 'court_types.id', 'court_types.name'])
            ->distinct()
            ->orderBy('court_types.name')
            ->get()
            ->groupBy('venue_cluster_id')
            ->map(fn (Collection $items) => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => $item->name,
            ])->values());

        $priceSlots = PriceSlot::query()
            ->with('courtType:id,name')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->orderBy('court_type_id')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'clusters' => $clusters,
            'court_types_by_cluster' => $courtTypes,
            'price_slots' => $priceSlots,
        ]);
    }

    public function updateDuration(Request $request, string $venueClusterId): JsonResponse
    {
        $this->ensureClusterAccess($request, $venueClusterId);

        $validated = $request->validate([
            'min_duration_minutes' => ['required', 'integer', 'min:30'],
            'max_duration_minutes' => ['nullable', 'integer', 'gte:min_duration_minutes'],
        ]);

        $config = BookingConfig::query()->updateOrCreate(
            ['venue_cluster_id' => $venueClusterId],
            [
                'min_duration_minutes' => $validated['min_duration_minutes'],
                'max_duration_minutes' => $validated['max_duration_minutes'] ?? null,
            ]
        );

        return response()->json($config);
    }

    public function storePriceSlot(Request $request): JsonResponse
    {
        $validated = $this->validatedPriceSlot($request);
        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], (int) $validated['court_type_id']);
        $this->ensureNoOverlap($validated);

        $slot = PriceSlot::query()->create([
            ...$validated,
            'start_time' => $this->normalizeTime($validated['start_time']),
            'end_time' => $this->normalizeTime($validated['end_time']),
        ]);

        return response()->json($slot->load('courtType:id,name'), 201);
    }

    public function updatePriceSlot(Request $request, string $id): JsonResponse
    {
        $slot = PriceSlot::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $slot->venue_cluster_id);

        $validated = $this->validatedPriceSlot($request, $slot);
        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], (int) $validated['court_type_id']);
        $this->ensureNoOverlap($validated, $slot->id);

        $slot->update([
            ...$validated,
            'start_time' => $this->normalizeTime($validated['start_time']),
            'end_time' => $this->normalizeTime($validated['end_time']),
        ]);

        return response()->json($slot->fresh('courtType:id,name'));
    }

    public function destroyPriceSlot(Request $request, string $id): JsonResponse
    {
        $slot = PriceSlot::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $slot->venue_cluster_id);
        $slot->delete();

        return response()->json(['message' => 'Đã xóa khung giá.']);
    }

    private function validatedPriceSlot(Request $request, ?PriceSlot $slot = null): array
    {
        $rules = [
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'court_type_id' => ['required', 'integer', 'exists:court_types,id'],
            'apply_to_days' => ['required', 'array', 'min:1'],
            'apply_to_days.*' => ['integer', 'between:1,7', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'booking_type' => ['required', Rule::in(['all', 'single', 'recurring'])],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($slot) {
            foreach (['venue_cluster_id', 'court_type_id', 'apply_to_days', 'start_time', 'end_time', 'booking_type', 'price', 'is_active'] as $field) {
                $rules[$field] = ['sometimes', ...array_slice($rules[$field], 1)];
            }
        }

        $validated = $request->validate($rules);

        if (! $slot) {
            return $validated;
        }

        return array_merge($slot->only([
            'venue_cluster_id',
            'court_type_id',
            'apply_to_days',
            'start_time',
            'end_time',
            'booking_type',
            'price',
            'is_active',
        ]), $validated);
    }

    private function ensureNoOverlap(array $data, ?string $exceptId = null): void
    {
        if (! $data['is_active']) {
            return;
        }

        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->normalizeTime($data['end_time']);
        $days = $this->normalizeDays($data['apply_to_days']);

        $query = PriceSlot::query()
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->where('court_type_id', $data['court_type_id'])
            ->where('booking_type', $data['booking_type'])
            ->where('is_active', true)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        $hasOverlap = $query->get()
            ->contains(fn (PriceSlot $slot) => count(array_intersect($days, $this->normalizeDays($slot->apply_to_days ?? []))) > 0);

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_time' => 'Khung giờ bị trùng với cấu hình giá đang hoạt động.',
            ]);
        }
    }

    private function ensureClusterAccess(Request $request, string $venueClusterId): void
    {
        abort_unless($this->visibleClusterIds($request->user()->id)->contains($venueClusterId), 403);
    }

    private function ensureCourtTypeBelongsToCluster(string $venueClusterId, int $courtTypeId): void
    {
        $exists = DB::table('venue_courts')
            ->where('venue_cluster_id', $venueClusterId)
            ->where('court_type_id', $courtTypeId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'court_type_id' => 'Loại sân không thuộc cụm sân đã chọn.',
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

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    private function normalizeDays(array $days): array
    {
        return collect($days)
            ->map(fn ($day) => (int) $day)
            ->map(fn (int $day) => $day === 0 ? 7 : $day)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
