<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'area' => ['nullable', 'string', 'max:100'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ]);

        $query = VenueCluster::query()
            ->with(['venueCourts' => function ($query) {
                $query->with('courtType:id,name,parent_id')
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->where('status', 'active');

        if (! empty($validated['q'])) {
            $keyword = $validated['q'];
            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%");
            });
        }

        if (! empty($validated['area'])) {
            $query->where('address', 'like', '%'.$validated['area'].'%');
        }

        if (isset($validated['min_rating'])) {
            $query->where('rating_avg', '>=', $validated['min_rating']);
        }

        if (! empty($validated['court_type_id'])) {
            $courtTypeId = (int) $validated['court_type_id'];
            $courtTypeIds = $this->courtTypeSelfAndDescendants($courtTypeId);

            $query->whereHas('venueCourts', function ($query) use ($courtTypeIds) {
                $query->whereIn('court_type_id', $courtTypeIds)->where('status', 'active');
            });
        }

        $clusters = $query
            ->orderByDesc('rating_avg')
            ->orderBy('name')
            ->get()
            ->map(fn (VenueCluster $cluster) => $this->summaryPayload($cluster));

        return response()->json(['data' => $clusters]);
    }

    public function show(string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->with([
                'bookingConfig',
                'venueCourts' => function ($query) {
                    $query->with('courtType:id,name,parent_id')
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
            ])
            ->where('status', 'active')
            ->where(function ($query) use ($id) {
                $query->whereKey($id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        return response()->json([
            'data' => array_merge($this->summaryPayload($cluster), [
                'description' => $cluster->description,
                'phone_contact' => $cluster->phone_contact,
                'map_url' => $cluster->map_url,
                'latitude' => $cluster->latitude,
                'longitude' => $cluster->longitude,
                'amenities' => $cluster->amenities ?? [],
                'booking_config' => $cluster->bookingConfig,
                'venue_courts' => $cluster->venueCourts,
                'price_slots' => PriceSlot::query()
                    ->with('courtType:id,name,parent_id')
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('is_active', true)
                    ->orderBy('court_type_id')
                    ->orderBy('start_time')
                    ->get(),
                'holiday_prices' => HolidayPrice::query()
                    ->with('courtType:id,name,parent_id')
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('is_active', true)
                    ->orderBy('holiday_date')
                    ->orderBy('start_time')
                    ->get(),
                'gallery' => $this->gallery($cluster),
                'reviews' => [],
            ]),
        ]);
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->where('status', 'active')
            ->where(function ($query) use ($id) {
                $query->whereKey($id)->orWhere('slug', $id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'booking_type' => ['nullable', 'in:single,recurring'],
        ]);

        return response()->json($this->bookingService->getAvailabilitySchedule(
            $cluster->id,
            $validated['booking_date'],
            isset($validated['court_type_id']) ? (int) $validated['court_type_id'] : null,
            $validated['booking_type'] ?? 'single',
        ));
    }

    private function summaryPayload(VenueCluster $cluster): array
    {
        $courtTypeIds = $cluster->venueCourts->pluck('court_type_id')->unique()->values();
        $priceCourtTypeIds = $this->courtTypeIdsWithAncestors($courtTypeIds);

        $minPrice = $courtTypeIds->isEmpty()
            ? null
            : PriceSlot::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('court_type_id', $priceCourtTypeIds)
                ->where('is_active', true)
                ->min('price');

        $courtTypes = $cluster->venueCourts
            ->pluck('courtType')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'parent_id' => $type->parent_id,
            ]);

        return [
            'id' => $cluster->id,
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'province' => $cluster->province,
            'ward' => $cluster->ward,
            'address' => $cluster->address,
            'status' => $cluster->status,
            'rating_avg' => (float) $cluster->rating_avg,
            'rating_count' => (int) $cluster->rating_count,
            'court_count' => $cluster->venueCourts->count(),
            'court_types' => $courtTypes,
            'min_price' => $minPrice ? (float) $minPrice : null,
            'image_path' => $this->coverImage($cluster),
        ];
    }

    private function coverImage(VenueCluster $cluster): ?string
    {
        return DB::table('media')
            ->where('mediable_type', VenueCluster::class)
            ->where('mediable_id', $cluster->id)
            ->value('file_path');
    }

    private function gallery(VenueCluster $cluster): array
    {
        $paths = DB::table('media')
            ->where('mediable_type', VenueCluster::class)
            ->where('mediable_id', $cluster->id)
            ->pluck('file_path')
            ->all();

        return $paths ?: [];
    }

    private function courtTypeSelfAndDescendants(int $courtTypeId): array
    {
        $types = DB::table('court_types')
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id']);

        $childrenByParent = $types
            ->filter(fn ($type) => $type->parent_id !== null)
            ->groupBy(fn ($type) => (int) $type->parent_id);

        $ids = [$courtTypeId];
        $stack = [$courtTypeId];

        while ($stack !== []) {
            $parentId = array_pop($stack);

            foreach (($childrenByParent[$parentId] ?? collect()) as $child) {
                $childId = (int) $child->id;
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function courtTypeIdsWithAncestors(Collection $courtTypeIds): array
    {
        $parentByType = DB::table('court_types')
            ->whereNull('deleted_at')
            ->pluck('parent_id', 'id')
            ->all();

        $ids = $courtTypeIds
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        foreach ($ids as $courtTypeId) {
            $currentTypeId = $courtTypeId;
            $guard = 0;

            while ($guard < 20 && isset($parentByType[$currentTypeId]) && $parentByType[$currentTypeId] !== null) {
                $parentTypeId = (int) $parentByType[$currentTypeId];
                $ids[] = $parentTypeId;
                $currentTypeId = $parentTypeId;
                $guard++;
            }
        }

        return array_values(array_unique($ids));
    }
}
