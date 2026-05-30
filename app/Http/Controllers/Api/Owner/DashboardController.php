<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->visibleClusterIds($request->user()->id);

        if ($clusterIds->isEmpty()) {
            return response()->json([
                'bookings' => 0,
                'revenue' => 0,
                'rating' => 0,
            ]);
        }

        $bookingsCount = DB::table('bookings')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->count();

        $revenue = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->where('payments.status', 'paid')
            ->sum('payments.amount') ?? 0;

        $rating = DB::table('venue_clusters')
            ->whereIn('id', $clusterIds)
            ->avg('rating_avg') ?? 0;

        return response()->json([
            'bookings' => $bookingsCount,
            'revenue' => (float) $revenue,
            'rating' => round((float) $rating, 2),
        ]);
    }

    private function visibleClusterIds(string $userId)
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
