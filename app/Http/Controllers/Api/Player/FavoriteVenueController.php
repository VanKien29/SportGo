<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\FavoriteVenue;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use App\Models\VenueBasePrice;
use Illuminate\Http\Request;

class FavoriteVenueController extends Controller
{
    public function index(Request $request)
    {
        $favorites = FavoriteVenue::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('venueCluster', fn ($query) => $query->where('status', 'active'))
            ->with([
                'venueCluster.venueCourts.courtType',
                'venueCluster.media',
            ])
            ->latest('created_at')
            ->paginate(12);

        $venueIds = $favorites->getCollection()
            ->pluck('venue_cluster_id')
            ->filter()
            ->values();

        $slotPrices = PriceSlot::query()
            ->whereIn('venue_cluster_id', $venueIds)
            ->where('is_active', true)
            ->get(['venue_cluster_id', 'price'])
            ->groupBy('venue_cluster_id');

        $basePrices = VenueBasePrice::query()
            ->whereIn('venue_cluster_id', $venueIds)
            ->get(['venue_cluster_id', 'price'])
            ->groupBy('venue_cluster_id');

        $favorites->setCollection($favorites->getCollection()->map(function (FavoriteVenue $favorite) use ($slotPrices, $basePrices) {
            $cluster = $favorite->venueCluster;
            $activeCourts = $cluster?->venueCourts
                ?->where('status', 'active')
                ->values() ?? collect();
            $courtTypes = $activeCourts
                ->pluck('courtType')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                ])
                ->all();
            $prices = collect([
                $slotPrices->get($cluster?->id, collect()),
                $basePrices->get($cluster?->id, collect()),
            ])->flatten(1)->pluck('price')->filter(fn ($price) => $price !== null)->map(fn ($price) => (float) $price);
            $image = $cluster?->media
                ?->first(fn ($media) => str_starts_with((string) $media->mime_type, 'image/'));

            return [
                'id' => $favorite->id,
                'created_at' => $favorite->created_at,
                'venue_cluster' => [
                    'id' => $cluster?->id,
                    'name' => $cluster?->name,
                    'slug' => $cluster?->slug,
                    'address' => $cluster?->address,
                    'ward' => $cluster?->ward,
                    'province' => $cluster?->province,
                    'latitude' => $cluster?->latitude,
                    'longitude' => $cluster?->longitude,
                    'rating_avg' => (float) ($cluster?->rating_avg ?? 0),
                    'rating_count' => (int) ($cluster?->rating_count ?? 0),
                    'court_count' => $activeCourts->count(),
                    'court_types' => $courtTypes,
                    'min_price' => $prices->isEmpty() ? null : $prices->min(),
                    'image_path' => $image?->file_path,
                    'has_map' => $cluster?->latitude !== null && $cluster?->longitude !== null,
                ],
            ];
        }));

        return response()->json($favorites);
    }

    public function toggle(Request $request, string $venueClusterId)
    {
        $cluster = VenueCluster::query()->where('status', 'active')->findOrFail($venueClusterId);
        $favorite = FavoriteVenue::query()
            ->where('user_id', $request->user()->id)
            ->where('venue_cluster_id', $cluster->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['favorited' => false, 'message' => 'Đã bỏ sân khỏi danh sách yêu thích.']);
        }

        FavoriteVenue::query()->create([
            'user_id' => $request->user()->id,
            'venue_cluster_id' => $cluster->id,
        ]);

        return response()->json(['favorited' => true, 'message' => 'Đã lưu sân vào danh sách yêu thích.']);
    }

    public function status(Request $request, string $venueClusterId)
    {
        return response()->json([
            'favorited' => FavoriteVenue::query()
                ->where('user_id', $request->user()->id)
                ->where('venue_cluster_id', $venueClusterId)
                ->exists(),
        ]);
    }
}
