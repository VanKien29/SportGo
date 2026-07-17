<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VenuePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'today_booking_summary' => $this->emptyTodayBookingSummary(),
                'today_bookings' => [],
                'pending_bookings' => [],
                'cancelled_today' => [],
                'golden_hours' => [],
                'court_revenues' => [],
                'published_posts' => [],
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

        $today = now()->toDateString();
        $todayBookingQuery = Booking::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereDate('booking_date', $today);

        $todayRevenue = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->whereDate('bookings.booking_date', $today)
            ->where('payments.status', 'paid')
            ->sum('payments.amount') ?? 0;

        $todaySummary = [
            'date' => $today,
            'total' => (clone $todayBookingQuery)->count(),
            'pending_approval' => (clone $todayBookingQuery)->where('status', 'pending_approval')->count(),
            'pending_payment' => (clone $todayBookingQuery)->where('status', 'pending_payment')->count(),
            'paid' => (clone $todayBookingQuery)
                ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
                ->whereHas('payments', fn ($query) => $query->where('status', 'paid'))
                ->count(),
            'cancelled' => (clone $todayBookingQuery)->whereIn('status', ['cancelled', 'rejected', 'expired'])->count(),
            'revenue' => (float) $todayRevenue,
        ];

        $todayBookings = $this->bookingDashboardQuery($clusterIds)
            ->whereDate('booking_date', $today)
            ->orderBy('start_time')
            ->limit(12)
            ->get()
            ->map(fn (Booking $booking): array => $this->bookingDashboardPayload($booking));

        $pendingBookings = $this->bookingDashboardQuery($clusterIds)
            ->whereDate('booking_date', '>=', $today)
            ->whereIn('status', ['pending_approval', 'pending_payment'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit(10)
            ->get()
            ->map(fn (Booking $booking): array => $this->bookingDashboardPayload($booking));

        $cancelledToday = $this->bookingDashboardQuery($clusterIds)
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['cancelled', 'rejected', 'expired'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Booking $booking): array => $this->bookingDashboardPayload($booking));

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

        $publishedPosts = VenuePost::query()
            ->with('venueCluster:id,name')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->where('status', 'published')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(function (VenuePost $post): array {
                return [
                    'id' => $post->id,
                    'venue_cluster_id' => $post->venue_cluster_id,
                    'venue_cluster_name' => $post->venueCluster?->name,
                    'title' => $post->title ?: Str::limit(strip_tags($post->content), 80),
                    'slug' => $post->slug,
                    'short_description' => $post->short_description,
                    'post_type' => $post->post_type,
                    'view_count' => (int) $post->view_count,
                    'like_count' => (int) $post->like_count,
                    'comment_count' => (int) $post->comment_count,
                    'reviewed_at' => $post->reviewed_at,
                    'created_at' => $post->created_at,
                ];
            });

        return response()->json([
            'bookings' => $bookingsCount,
            'revenue' => (float) $revenue,
            'rating' => round((float) $rating, 2),
            'venue_cluster_id' => $selectedClusterId,
            'wallet' => $walletData,
            'today_booking_summary' => $todaySummary,
            'today_bookings' => $todayBookings,
            'pending_bookings' => $pendingBookings,
            'cancelled_today' => $cancelledToday,
            'golden_hours' => $goldenHours,
            'court_revenues' => $courtRevenues,
            'published_posts' => $publishedPosts,
        ]);
    }

    private function bookingDashboardQuery($clusterIds)
    {
        return Booking::query()
            ->with([
                'customer:id,username,full_name,phone,email',
                'venueCourt:id,name,court_type_id',
                'venueCourt.courtType:id,name',
                'items:id,booking_id,venue_court_id,start_time,end_time,subtotal,status',
                'items.venueCourt:id,name,court_type_id',
                'payments:id,booking_id,amount,status,method,payment_kind,paid_at',
            ])
            ->whereIn('venue_cluster_id', $clusterIds);
    }

    private function bookingDashboardPayload(Booking $booking): array
    {
        $paidAmount = (float) $booking->payments->where('status', 'paid')->sum('amount');
        $totalPrice = (float) $booking->total_price;
        $outstandingAmount = max($totalPrice - $paidAmount, 0);
        $items = $booking->items->isNotEmpty()
            ? $booking->items
            : collect([(object) [
                'venueCourt' => $booking->venueCourt,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]]);

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_date' => $booking->booking_date?->toDateString(),
            'time_label' => $this->bookingTimeLabel($items),
            'court_label' => $items
                ->map(fn ($item) => $item->venueCourt?->name)
                ->filter()
                ->unique()
                ->values()
                ->implode(', ') ?: ($booking->venueCourt?->name ?: 'Sân'),
            'court_type_label' => $booking->venueCourt?->courtType?->name,
            'customer_name' => $booking->customer?->full_name
                ?: $booking->customer?->username
                ?: $booking->walk_in_name
                ?: 'Khách hàng',
            'customer_phone' => $booking->customer?->phone ?: $booking->walk_in_phone,
            'status' => $booking->status,
            'status_label' => $this->bookingStatusLabel($booking->status),
            'payment_option' => $booking->payment_option,
            'payment_option_label' => $this->paymentOptionLabel($booking->payment_option),
            'payment_state' => $this->paymentState($paidAmount, $totalPrice),
            'payment_state_label' => $this->paymentStateLabel($paidAmount, $totalPrice),
            'source' => $booking->source,
            'source_label' => $booking->source === 'online' ? 'Online' : 'Tại quầy',
            'total_price' => $totalPrice,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
        ];
    }

    private function bookingTimeLabel($items): string
    {
        return $items
            ->map(fn ($item) => sprintf('%s - %s', substr((string) $item->start_time, 0, 5), substr((string) $item->end_time, 0, 5)))
            ->unique()
            ->values()
            ->implode(', ');
    }

    private function bookingStatusLabel(?string $status): string
    {
        return [
            'pending_approval' => 'Chờ xác nhận',
            'pending_payment' => 'Chờ thanh toán',
            'confirmed' => 'Đã xác nhận',
            'checked_in' => 'Đang chơi',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'rejected' => 'Từ chối',
            'expired' => 'Quá hạn',
        ][$status] ?? ($status ?: 'Không rõ');
    }

    private function paymentOptionLabel(?string $option): string
    {
        return [
            'no_prepay' => 'Thu sau',
            'deposit' => 'Đặt cọc',
            'full_payment' => 'Thanh toán đủ',
        ][$option] ?? ($option ?: 'Không rõ');
    }

    private function paymentState(float $paidAmount, float $totalPrice): string
    {
        if ($totalPrice <= 0 || $paidAmount >= $totalPrice) {
            return 'paid';
        }

        return $paidAmount > 0 ? 'partial' : 'unpaid';
    }

    private function paymentStateLabel(float $paidAmount, float $totalPrice): string
    {
        return [
            'paid' => 'Đã thanh toán',
            'partial' => 'Thanh toán một phần',
            'unpaid' => 'Chưa thanh toán',
        ][$this->paymentState($paidAmount, $totalPrice)];
    }

    private function emptyTodayBookingSummary(): array
    {
        return [
            'date' => now()->toDateString(),
            'total' => 0,
            'pending_approval' => 0,
            'pending_payment' => 0,
            'paid' => 0,
            'cancelled' => 0,
            'revenue' => 0,
        ];
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
