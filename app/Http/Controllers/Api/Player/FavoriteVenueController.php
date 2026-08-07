<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\FavoriteVenue;
use App\Models\VenueCluster;
use Illuminate\Http\Request;

class FavoriteVenueController extends Controller
{
    public function index(Request $request)
    {
        $favorites = FavoriteVenue::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('venueCluster', fn ($query) => $query->where('status', 'active'))
            ->with(['venueCluster.venueCourts.courtType'])
            ->latest('created_at')
            ->paginate(12);

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
