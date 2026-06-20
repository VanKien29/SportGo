<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConfig;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\VenueBasePrice;
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

        $holidayPrices = HolidayPrice::query()
            ->with('courtType:id,name')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->orderByDesc('holiday_date')
            ->orderBy('court_type_id')
            ->orderBy('start_time')
            ->get();

        $basePrices = VenueBasePrice::query()
            ->with('courtType:id,name')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->orderBy('court_type_id')
            ->get();

        return response()->json([
            'clusters' => $clusters,
            'court_types_by_cluster' => $courtTypes,
            'base_prices' => $basePrices,
            'system_default_price' => 10000,
            'price_slots' => $priceSlots,
            'holiday_prices' => $holidayPrices,
        ]);
    }

    public function updateDuration(Request $request, string $venueClusterId): JsonResponse
    {
        $this->ensureClusterAccess($request, $venueClusterId);

        $validated = $request->validate([
            'min_duration_minutes' => ['required', 'integer', 'min:30', 'max:120', 'multiple_of:30'],
            'max_duration_minutes' => ['nullable', 'integer', 'gte:min_duration_minutes', 'max:1440', 'multiple_of:30'],
        ]);

        $oldValues = BookingConfig::query()->where('venue_cluster_id', $venueClusterId)->first()?->toArray() ?? [];
        $config = BookingConfig::query()->updateOrCreate(
            ['venue_cluster_id' => $venueClusterId],
            [
                'min_duration_minutes' => $validated['min_duration_minutes'],
                'max_duration_minutes' => $validated['max_duration_minutes'] ?? null,
            ]
        );

        $this->audit($request, 'pricing.duration_updated', BookingConfig::class, $venueClusterId, $oldValues, $config->fresh()->toArray());

        return response()->json($config);
    }

    public function updateBasePrice(Request $request, int $courtTypeId): JsonResponse
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ], [
            'price.gt' => 'Giá chung phải lớn hơn 0.',
        ]);

        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], $courtTypeId);

        $existing = VenueBasePrice::query()
            ->where('venue_cluster_id', $validated['venue_cluster_id'])
            ->where('court_type_id', $courtTypeId)
            ->first();
        $oldValues = $existing?->toArray() ?? [];

        $basePrice = VenueBasePrice::query()->updateOrCreate(
            [
                'venue_cluster_id' => $validated['venue_cluster_id'],
                'court_type_id' => $courtTypeId,
            ],
            ['price' => $validated['price']],
        )->load('courtType:id,name');

        $this->audit(
            $request,
            'pricing.base_price_updated',
            VenueBasePrice::class,
            $basePrice->id,
            $oldValues,
            $basePrice->toArray(),
        );

        return response()->json($basePrice);
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

        $slot->load('courtType:id,name');
        $this->audit($request, 'pricing.weekly_created', PriceSlot::class, $slot->id, [], $slot->toArray());

        return response()->json($slot, 201);
    }

    public function updatePriceSlot(Request $request, string $id): JsonResponse
    {
        $slot = PriceSlot::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $slot->venue_cluster_id);

        $validated = $this->validatedPriceSlot($request, $slot);
        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], (int) $validated['court_type_id']);
        $this->ensureNoOverlap($validated, $slot->id);

        $oldValues = $slot->toArray();
        $slot->update([
            ...$validated,
            'start_time' => $this->normalizeTime($validated['start_time']),
            'end_time' => $this->normalizeTime($validated['end_time']),
        ]);

        $slot = $slot->fresh('courtType:id,name');
        $action = array_key_exists('is_active', $request->all()) && count($request->all()) === 1
            ? 'pricing.weekly_toggled'
            : 'pricing.weekly_updated';
        $this->audit($request, $action, PriceSlot::class, $slot->id, $oldValues, $slot->toArray());

        return response()->json($slot);
    }

    public function destroyPriceSlot(Request $request, string $id): JsonResponse
    {
        $slot = PriceSlot::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $slot->venue_cluster_id);
        $oldValues = $slot->load('courtType:id,name')->toArray();
        $slot->delete();
        $this->audit($request, 'pricing.weekly_deleted', PriceSlot::class, $slot->id, $oldValues, []);

        return response()->json(['message' => 'Đã xóa khung giá.']);
    }

    public function storeHolidayPrice(Request $request): JsonResponse
    {
        $validated = $this->validatedHolidayPrice($request);
        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], (int) $validated['court_type_id']);
        $this->ensureNoHolidayOverlap($validated);

        $price = HolidayPrice::query()->create([
            ...$validated,
            'start_time' => $this->normalizeTime($validated['start_time']),
            'end_time' => $this->normalizeTime($validated['end_time']),
        ])->load('courtType:id,name');

        $this->audit($request, 'pricing.special_created', HolidayPrice::class, $price->id, [], $price->toArray());

        return response()->json($price, 201);
    }

    public function updateHolidayPrice(Request $request, string $id): JsonResponse
    {
        $price = HolidayPrice::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $price->venue_cluster_id);

        $validated = $this->validatedHolidayPrice($request, $price);
        $this->ensureClusterAccess($request, $validated['venue_cluster_id']);
        $this->ensureCourtTypeBelongsToCluster($validated['venue_cluster_id'], (int) $validated['court_type_id']);
        $this->ensureNoHolidayOverlap($validated, $price->id);

        $oldValues = $price->toArray();
        $price->update([
            ...$validated,
            'start_time' => $this->normalizeTime($validated['start_time']),
            'end_time' => $this->normalizeTime($validated['end_time']),
        ]);

        $price = $price->fresh('courtType:id,name');
        $action = array_key_exists('is_active', $request->all()) && count($request->all()) === 1
            ? 'pricing.special_toggled'
            : 'pricing.special_updated';
        $this->audit($request, $action, HolidayPrice::class, $price->id, $oldValues, $price->toArray());

        return response()->json($price);
    }

    public function destroyHolidayPrice(Request $request, string $id): JsonResponse
    {
        $price = HolidayPrice::query()->findOrFail($id);
        $this->ensureClusterAccess($request, $price->venue_cluster_id);
        $oldValues = $price->load('courtType:id,name')->toArray();
        $price->delete();
        $this->audit($request, 'pricing.special_deleted', HolidayPrice::class, $price->id, $oldValues, []);

        return response()->json(['message' => 'Đã xóa giá ngày đặc biệt.']);
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
            'price' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($slot) {
            foreach (['venue_cluster_id', 'court_type_id', 'apply_to_days', 'start_time', 'end_time', 'booking_type', 'price', 'is_active'] as $field) {
                $rules[$field] = ['sometimes', ...array_slice($rules[$field], 1)];
            }
        }

        $validated = $request->validate($rules, [
            'price.gt' => 'Giá / giờ phải lớn hơn 0.',
        ]);

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
            ->whereIn('booking_type', $this->overlappingBookingTypes($data['booking_type']))
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

    private function validatedHolidayPrice(Request $request, ?HolidayPrice $price = null): array
    {
        $rules = [
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'court_type_id' => ['required', 'integer', 'exists:court_types,id'],
            'date_type' => ['required', Rule::in(['holiday', 'special_date'])],
            'holiday_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'booking_type' => ['required', Rule::in(['all', 'single', 'recurring'])],
            'price' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($price) {
            foreach (array_keys($rules) as $field) {
                $rules[$field] = ['sometimes', ...array_slice($rules[$field], 1)];
            }
        }

        $validated = $request->validate($rules, [
            'price.gt' => 'Giá / giờ phải lớn hơn 0.',
        ]);

        if (! $price) {
            return $validated;
        }

        return array_merge($price->only(array_keys($rules)), $validated);
    }

    private function ensureNoHolidayOverlap(array $data, ?string $exceptId = null): void
    {
        if (! $data['is_active']) {
            return;
        }

        $query = HolidayPrice::query()
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->where('court_type_id', $data['court_type_id'])
            ->where('holiday_date', $data['holiday_date'])
            ->whereIn('booking_type', $this->overlappingBookingTypes($data['booking_type']))
            ->where('is_active', true)
            ->where('start_time', '<', $this->normalizeTime($data['end_time']))
            ->where('end_time', '>', $this->normalizeTime($data['start_time']));

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_time' => 'Khung giờ bị trùng với giá ngày đặc biệt đang hoạt động.',
            ]);
        }
    }

    private function overlappingBookingTypes(string $bookingType): array
    {
        return $bookingType === 'all'
            ? ['all', 'single', 'recurring']
            : ['all', $bookingType];
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

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'owner',
            'module' => 'pricing',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'owner',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
