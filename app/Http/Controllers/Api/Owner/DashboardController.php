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
        $selectedClusterId = $request->query('venue_cluster_id');

        if ($selectedClusterId) {
            if (! $clusterIds->contains($selectedClusterId)) {
                return response()->json([
                    'message' => 'Bạn không có quyền xem dữ liệu của cụm sân này.',
                ], 403);
            }

            $clusterIds = collect([$selectedClusterId]);
        }

        $walletQuery = DB::table('owner_wallets')
            ->where('owner_id', $request->user()->id);

        if ($selectedClusterId) {
            $walletQuery->where('venue_cluster_id', $selectedClusterId);
        }

        $wallet = $walletQuery
            ->selectRaw('COALESCE(SUM(available_balance), 0) as available_balance')
            ->selectRaw('COALESCE(SUM(pending_withdrawal_balance), 0) as pending_withdrawal_balance')
            ->selectRaw('COALESCE(SUM(total_earned), 0) as total_earned')
            ->selectRaw('COALESCE(SUM(total_withdrawn), 0) as total_withdrawn')
            ->first();

        $legacyPendingQuery = DB::table('owner_withdrawal_requests')
            ->where('owner_withdrawal_requests.owner_id', $request->user()->id)
            ->whereIn('owner_withdrawal_requests.status', ['pending', 'reviewing', 'approved'])
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('owner_wallet_ledgers')
                    ->whereColumn('owner_wallet_ledgers.reference_id', 'owner_withdrawal_requests.id')
                    ->where('owner_wallet_ledgers.reference_type', 'withdrawal')
                    ->where('owner_wallet_ledgers.type', 'hold');
            });

        if ($selectedClusterId) {
            $legacyPendingQuery
                ->join('owner_wallets', 'owner_wallets.id', '=', 'owner_withdrawal_requests.owner_wallet_id')
                ->where('owner_wallets.venue_cluster_id', $selectedClusterId);
        }

        $legacyPendingAmount = (float) $legacyPendingQuery->sum('owner_withdrawal_requests.amount');

        $walletData = [
            'available_balance' => $wallet ? max(0, (float) $wallet->available_balance - $legacyPendingAmount) : 0.0,
            'pending_withdrawal_balance' => $wallet ? (float) $wallet->pending_withdrawal_balance + $legacyPendingAmount : 0.0,
            'total_earned' => $wallet ? (float) $wallet->total_earned : 0.0,
            'total_withdrawn' => $wallet ? (float) $wallet->total_withdrawn : 0.0,
        ];

        if ($clusterIds->isEmpty()) {
            return response()->json([
                'bookings' => 0,
                'revenue' => 0,
                'rating' => 0,
                'venue_cluster_id' => null,
                'wallet' => $walletData,
                'golden_hours' => [],
                'court_revenues' => [],
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

        $goldenHours = DB::table('bookings')
            ->select(DB::raw("CONCAT(SUBSTRING(start_time, 1, 5), ' - ', SUBSTRING(end_time, 1, 5)) as time_slot"), DB::raw('count(*) as count'))
            ->whereIn('venue_cluster_id', $clusterIds)
            ->where('status', '!=', 'cancelled')
            ->groupBy('time_slot')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $courtRevenuesRaw = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('venue_courts', 'bookings.venue_court_id', '=', 'venue_courts.id')
            ->select('venue_courts.name as court_name', DB::raw('sum(payments.amount) as revenue'))
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->where('payments.status', 'paid')
            ->groupBy('venue_courts.id', 'venue_courts.name')
            ->orderByDesc('revenue')
            ->get();

        $courtRevenues = collect($courtRevenuesRaw)->map(function ($item) {
            return [
                'court_name' => $item->court_name,
                'revenue' => (float) $item->revenue,
            ];
        });

        return response()->json([
            'bookings' => $bookingsCount,
            'revenue' => (float) $revenue,
            'rating' => round((float) $rating, 2),
            'venue_cluster_id' => $selectedClusterId,
            'wallet' => $walletData,
            'golden_hours' => $goldenHours,
            'court_revenues' => $courtRevenues,
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
