<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VenuePost;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venue_cluster_id' => ['nullable', 'integer'],
            'period' => ['nullable', Rule::in(['today', '7_days', '30_days', 'this_month', 'custom'])],
            'date_from' => ['nullable', 'date', 'required_if:period,custom'],
            'date_to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:date_from'],
        ]);
        $period = $this->resolvePeriod(
            $validated['period'] ?? 'today',
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );
        $clusterIds = $this->visibleClusterIds($request->user()->id);
        $selectedClusterId = $validated['venue_cluster_id'] ?? null;

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
            ->whereIn('owner_withdrawal_requests.status', ['pending', 'approved'])
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
            'uncollected_booking_count' => 0,
            'uncollected_booking_amount' => 0.0,
        ];

        if ($clusterIds->isNotEmpty()) {
            $uncollectedBookings = Booking::query()
                ->with('payments:id,booking_id,amount,status')
                ->whereIn('venue_cluster_id', $clusterIds)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->get()
                ->map(function (Booking $booking): float {
                    $paidAmount = (float) $booking->payments->where('status', 'paid')->sum('amount');

                    return max((float) $booking->total_price - $paidAmount, 0);
                })
                ->filter(fn (float $amount): bool => $amount > 0.009);
            $walletData['uncollected_booking_count'] = $uncollectedBookings->count();
            $walletData['uncollected_booking_amount'] = round($uncollectedBookings->sum(), 2);
        }

        if ($clusterIds->isEmpty()) {
            return response()->json([
                'bookings' => 0,
                'revenue' => 0,
                'rating' => 0,
                'venue_cluster_id' => null,
                'period' => $period,
                'period_summary' => $this->emptyPeriodSummary(),
                'booking_statuses' => $this->emptyBookingStatuses(),
                'revenue_trend' => $this->emptyRevenueTrend($period),
                'operations' => $this->emptyOperations(),
                'recent_bookings' => [],
                'court_statuses' => ['total' => 0, 'active' => 0, 'maintenance' => 0, 'inactive' => 0],
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

        $today = Carbon::now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'))->toDateString();
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

        $periodBookingQuery = Booking::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereBetween('booking_date', [$period['date_from'], $period['date_to']]);
        $periodBookingsCount = (clone $periodBookingQuery)->count();
        $periodRevenue = (float) DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->whereBetween('bookings.booking_date', [$period['date_from'], $period['date_to']])
            ->where('payments.status', 'paid')
            ->sum('payments.amount');
        $periodStatusCounts = (clone $periodBookingQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $periodSummary = [
            'bookings' => $periodBookingsCount,
            'revenue' => $periodRevenue,
            'average_booking_value' => $periodBookingsCount > 0 ? round($periodRevenue / $periodBookingsCount, 2) : 0,
            'completed' => (int) ($periodStatusCounts['completed'] ?? 0),
            'cancelled' => collect(['cancelled', 'rejected', 'expired'])
                ->sum(fn (string $status): int => (int) ($periodStatusCounts[$status] ?? 0)),
            'online_bookings' => (clone $periodBookingQuery)->where('source', 'online')->count(),
            'counter_bookings' => (clone $periodBookingQuery)->where('source', '!=', 'online')->count(),
        ];
        $onlineRevenue = (float) DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->whereBetween('bookings.booking_date', [$period['date_from'], $period['date_to']])
            ->where('bookings.source', 'online')
            ->where('payments.status', 'paid')
            ->sum('payments.amount');

        $counterRevenue = max(0, $periodRevenue - $onlineRevenue);

        $channelDistribution = [
            'online' => [
                'label' => 'Đặt qua App / Web SportGo',
                'count' => (int) ($periodSummary['online_bookings'] ?? 0),
                'revenue' => $onlineRevenue,
                'percent' => $periodBookingsCount > 0 ? round(($periodSummary['online_bookings'] / $periodBookingsCount) * 100, 1) : 0,
            ],
            'counter' => [
                'label' => 'Đặt trực tiếp tại quầy',
                'count' => (int) ($periodSummary['counter_bookings'] ?? 0),
                'revenue' => $counterRevenue,
                'percent' => $periodBookingsCount > 0 ? round(($periodSummary['counter_bookings'] / $periodBookingsCount) * 100, 1) : 0,
            ],
        ];

        $hourlyCounts = DB::table('bookings')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereBetween('booking_date', [$period['date_from'], $period['date_to']])
            ->whereNotIn('status', ['cancelled', 'rejected', 'expired'])
            ->selectRaw("SUBSTRING(start_time, 1, 2) as hour_slot, COUNT(*) as total")
            ->groupBy('hour_slot')
            ->pluck('total', 'hour_slot');

        $hourlyDistribution = [];
        for ($h = 6; $h <= 23; $h++) {
            $hourKey = sprintf('%02d', $h);
            $hourlyDistribution[] = [
                'hour' => $h,
                'slot' => sprintf('%02d:00', $h),
                'label' => sprintf('%02dh', $h),
                'count' => (int) ($hourlyCounts[$hourKey] ?? 0),
            ];
        }

        $bookingStatuses = $this->bookingStatusBreakdown($periodStatusCounts);
        $revenueTrend = $this->revenueTrend($clusterIds, $period);

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

        $recentBookings = $this->bookingDashboardQuery($clusterIds)
            ->whereBetween('booking_date', [$period['date_from'], $period['date_to']])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->limit(8)
            ->get()
            ->map(fn (Booking $booking): array => $this->bookingDashboardPayload($booking));

        $refundBase = DB::table('refunds')
            ->join('bookings', 'refunds.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds);
        $pendingRefundBase = (clone $refundBase)
            ->where('refunds.status', 'pending_owner_confirmation');
        $latestRefunds = (clone $refundBase)
            ->select([
                'refunds.id',
                'refunds.amount',
                'refunds.status',
                'refunds.created_at',
                'bookings.booking_code',
            ])
            ->orderByDesc('refunds.created_at')
            ->limit(4)
            ->get()
            ->map(fn ($refund): array => [
                'id' => $refund->id,
                'booking_code' => $refund->booking_code,
                'amount' => (float) $refund->amount,
                'status' => $refund->status,
                'status_label' => $this->refundStatusLabel($refund->status),
                'created_at' => $refund->created_at,
            ]);

        $withdrawalBase = DB::table('owner_withdrawal_requests')
            ->join('owner_wallets', 'owner_wallets.id', '=', 'owner_withdrawal_requests.owner_wallet_id')
            ->where('owner_withdrawal_requests.owner_id', $request->user()->id)
            ->when(
                $selectedClusterId,
                fn ($query, $clusterId) => $query->where('owner_wallets.venue_cluster_id', $clusterId)
            );
        $pendingWithdrawalStatuses = ['pending', 'approved'];
        $latestWithdrawals = (clone $withdrawalBase)
            ->select([
                'owner_withdrawal_requests.id',
                'owner_withdrawal_requests.request_code',
                'owner_withdrawal_requests.amount',
                'owner_withdrawal_requests.status',
                'owner_withdrawal_requests.requested_at',
            ])
            ->orderByDesc('owner_withdrawal_requests.requested_at')
            ->limit(4)
            ->get()
            ->map(fn ($withdrawal): array => [
                'id' => $withdrawal->id,
                'request_code' => $withdrawal->request_code,
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'status_label' => $this->withdrawalStatusLabel($withdrawal->status),
                'requested_at' => $withdrawal->requested_at,
            ]);

        $operations = [
            'pending_bookings' => (clone $this->bookingDashboardQuery($clusterIds))
                ->whereDate('booking_date', '>=', $today)
                ->whereIn('status', ['pending_approval', 'pending_payment'])
                ->count(),
            'pending_refunds' => (clone $pendingRefundBase)->count(),
            'pending_refund_amount' => (float) (clone $pendingRefundBase)->sum('refunds.amount'),
            'pending_withdrawals' => (clone $withdrawalBase)
                ->whereIn('owner_withdrawal_requests.status', $pendingWithdrawalStatuses)
                ->count(),
            'pending_withdrawal_amount' => (float) (clone $withdrawalBase)
                ->whereIn('owner_withdrawal_requests.status', $pendingWithdrawalStatuses)
                ->sum('owner_withdrawal_requests.amount'),
            'open_complaints' => DB::table('complaints')
                ->whereIn('venue_cluster_id', $clusterIds)
                ->whereIn('status', ['open', 'processing'])
                ->count(),
            'latest_refunds' => $latestRefunds,
            'latest_withdrawals' => $latestWithdrawals,
        ];

        $courtStatusCounts = DB::table('venue_courts')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $courtStatuses = [
            'total' => (int) $courtStatusCounts->sum(),
            'active' => (int) ($courtStatusCounts['active'] ?? 0),
            'maintenance' => (int) ($courtStatusCounts['maintenance'] ?? 0),
            'inactive' => (int) ($courtStatusCounts['inactive'] ?? 0),
        ];

        $goldenHours = DB::table('bookings')
            ->select(DB::raw("CONCAT(SUBSTRING(start_time, 1, 5), ' - ', SUBSTRING(end_time, 1, 5)) as time_slot"), DB::raw('count(*) as count'))
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereBetween('booking_date', [$period['date_from'], $period['date_to']])
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
            ->whereBetween('bookings.booking_date', [$period['date_from'], $period['date_to']])
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
            'period' => $period,
            'period_summary' => $periodSummary,
            'booking_statuses' => $bookingStatuses,
            'revenue_trend' => $revenueTrend,
            'operations' => $operations,
            'recent_bookings' => $recentBookings,
            'court_statuses' => $courtStatuses,
            'wallet' => $walletData,
            'today_booking_summary' => $todaySummary,
            'today_bookings' => $todayBookings,
            'pending_bookings' => $pendingBookings,
            'cancelled_today' => $cancelledToday,
            'golden_hours' => $goldenHours,
            'court_revenues' => $courtRevenues,
            'published_posts' => $publishedPosts,
            'channel_distribution' => $channelDistribution,
            'hourly_distribution' => $hourlyDistribution,
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

    private function resolvePeriod(string $key, ?string $dateFrom, ?string $dateTo): array
    {
        $today = Carbon::now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'))->startOfDay();

        [$from, $to, $label] = match ($key) {
            '7_days' => [$today->copy()->subDays(6), $today->copy(), '7 ngày gần nhất'],
            '30_days' => [$today->copy()->subDays(29), $today->copy(), '30 ngày gần nhất'],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy(), 'Tháng này'],
            'custom' => [
                Carbon::parse($dateFrom, (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'))->startOfDay(),
                Carbon::parse($dateTo, (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'))->startOfDay(),
                'Khoảng tùy chọn',
            ],
            default => [$today->copy(), $today->copy(), 'Hôm nay'],
        };

        if ($from->diffInDays($to) > 366) {
            abort(422, 'Khoảng thời gian thống kê không được vượt quá 366 ngày.');
        }

        return [
            'key' => $key,
            'label' => $label,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ];
    }

    private function bookingStatusBreakdown($statusCounts): array
    {
        $groups = [
            ['key' => 'pending', 'label' => 'Đang chờ xử lý', 'statuses' => ['pending_approval', 'pending_payment']],
            ['key' => 'confirmed', 'label' => 'Đã xác nhận', 'statuses' => ['confirmed']],
            ['key' => 'playing', 'label' => 'Đang chơi', 'statuses' => ['checked_in']],
            ['key' => 'completed', 'label' => 'Hoàn thành', 'statuses' => ['completed']],
            ['key' => 'cancelled', 'label' => 'Hủy / từ chối / no-show', 'statuses' => ['cancelled', 'rejected', 'expired', 'no_show']],
        ];

        return collect($groups)->map(function (array $group) use ($statusCounts): array {
            $count = collect($group['statuses'])
                ->sum(fn (string $status): int => (int) ($statusCounts[$status] ?? 0));

            return [
                'key' => $group['key'],
                'label' => $group['label'],
                'count' => $count,
            ];
        })->all();
    }

    private function revenueTrend($clusterIds, array $period): array
    {
        $bookingCounts = DB::table('bookings')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereBetween('booking_date', [$period['date_from'], $period['date_to']])
            ->selectRaw('DATE(booking_date) as trend_date, COUNT(*) as total')
            ->groupBy('trend_date')
            ->pluck('total', 'trend_date');

        $revenues = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.venue_cluster_id', $clusterIds)
            ->whereBetween('bookings.booking_date', [$period['date_from'], $period['date_to']])
            ->where('payments.status', 'paid')
            ->selectRaw('DATE(bookings.booking_date) as trend_date, SUM(payments.amount) as revenue')
            ->groupBy('trend_date')
            ->pluck('revenue', 'trend_date');

        return collect(CarbonPeriod::create($period['date_from'], $period['date_to']))
            ->map(function (Carbon $date) use ($bookingCounts, $revenues): array {
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'label' => $date->format('d/m'),
                    'bookings' => (int) ($bookingCounts[$key] ?? 0),
                    'revenue' => (float) ($revenues[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function emptyRevenueTrend(array $period): array
    {
        return collect(CarbonPeriod::create($period['date_from'], $period['date_to']))
            ->map(fn (Carbon $date): array => [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'bookings' => 0,
                'revenue' => 0,
            ])
            ->values()
            ->all();
    }

    private function refundStatusLabel(?string $status): string
    {
        return [
            'pending_owner_confirmation' => 'Chờ chủ sân',
            'owner_rejected' => 'Đã từ chối',
            'completed' => 'Hoàn tất',
            'completed_cash' => 'Đã hoàn tiền mặt',
        ][$status] ?? ($status ?: 'Không rõ');
    }

    private function withdrawalStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
            'completed' => 'Đã chuyển tiền',
            'cancelled' => 'Đã hủy',
        ][$status] ?? ($status ?: 'Không rõ');
    }

    private function emptyPeriodSummary(): array
    {
        return [
            'bookings' => 0,
            'revenue' => 0,
            'average_booking_value' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'online_bookings' => 0,
            'counter_bookings' => 0,
        ];
    }

    private function emptyBookingStatuses(): array
    {
        return $this->bookingStatusBreakdown(collect());
    }

    private function emptyOperations(): array
    {
        return [
            'pending_bookings' => 0,
            'pending_refunds' => 0,
            'pending_refund_amount' => 0,
            'pending_withdrawals' => 0,
            'pending_withdrawal_amount' => 0,
            'open_complaints' => 0,
            'latest_refunds' => [],
            'latest_withdrawals' => [],
        ];
    }

    private function emptyTodayBookingSummary(): array
    {
        return [
            'date' => Carbon::now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'))->toDateString(),
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
