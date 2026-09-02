<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnforceVenueAccessRestrictions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $clusterId = $this->resolveClusterId($request);
        if (! $clusterId) {
            return $next($request);
        }

        $cluster = DB::table('venue_clusters')->where('id', $clusterId)->first();
        if (! $cluster) {
            return $next($request);
        }

        $activeRestrictions = Schema::hasTable('venue_access_restrictions')
            ? DB::table('venue_access_restrictions')
                ->where('venue_cluster_id', $clusterId)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->orderByRaw("CASE WHEN access_mode = 'blocked' THEN 0 ELSE 1 END")
                ->orderByDesc('starts_at')
                ->get()
            : collect();

        $blockedRestriction = $activeRestrictions->firstWhere('access_mode', 'blocked');
        $limitedRestriction = $activeRestrictions->firstWhere('access_mode', 'limited');

        $terminationLocked = $cluster->status === 'locked'
            && Str::contains(Str::lower((string) $cluster->status_reason), ['cham dut', 'chấm dứt']);
        $terminationLocked = $terminationLocked
            || $blockedRestriction?->restriction_type === 'contract_termination';

        if ($terminationLocked || in_array($cluster->status, ['termination_processing', 'termination_locked', 'partner_terminated'], true)) {
            if ($this->isAllowedDuringTermination($request)) {
                return $next($request);
            }

            $this->deny(
                $cluster,
                $blockedRestriction,
                'Cụm sân đang bị khóa vì đang trong quy trình chấm dứt hợp đồng đối tác. Chỉ các luồng booking hiện hữu, hoàn tiền, rút tiền và hồ sơ chấm dứt được phép tiếp tục.',
                'contract_termination',
            );
        }

        // A limited restriction is deliberately not the same as a locked
        // cluster. Keep this compatibility check for clusters marked locked
        // by an older scheduler run while their only restriction is limited.
        $isLimitedOnly = $limitedRestriction
            && ! $blockedRestriction
            && ($cluster->status !== 'locked'
                || trim((string) $cluster->status_reason) === trim((string) $limitedRestriction->reason));

        if ($isLimitedOnly && $this->isLimitedAction($request)) {
            $this->deny(
                $cluster,
                $limitedRestriction,
                'Cụm sân đang bị giới hạn quyền quản lý theo chính sách phí nền tảng. Vui lòng xử lý phí nền tảng trước khi thực hiện thao tác này.',
                'platform_fee_limited',
            );
        }

        if ($blockedRestriction || in_array($cluster->status, ['locked', 'pending'], true)) {
            if ($blockedRestriction
                && $blockedRestriction->restriction_type === 'platform_fee_overdue'
                && $this->isPlatformFeeResolutionAction($request)) {
                return $next($request);
            }

            // A cluster lock must not strand operational work that is already
            // in progress. New setup/booking actions remain blocked, while
            // collecting an existing booking, processing refunds and making
            // withdrawals stay available.
            if ($this->isAllowedOperationalAction($request)) {
                return $next($request);
            }

            $message = $cluster->status === 'pending' && ! $blockedRestriction
                ? 'Cụm sân chưa sẵn sàng nhận booking vì đang chờ hoàn tất ký kết hợp đồng đối tác.'
                : $this->lockedMessage($cluster, $blockedRestriction);

            $code = $cluster->status === 'pending' && ! $blockedRestriction
                ? 'cluster_pending'
                : ($blockedRestriction?->restriction_type ?: 'cluster_locked');

            $this->deny(
                $cluster,
                $blockedRestriction,
                $message,
                $code,
            );
        }

        return $next($request);
    }

    private function deny(object $cluster, ?object $restriction, string $message, string $code): void
    {
        $reason = trim((string) ($restriction?->reason ?: $cluster->status_reason));
        if ($reason === '' && ! Str::contains(Str::lower($message), ['lý do', 'ly do'])) {
            $message .= ' Lý do chưa được cập nhật.';
        }
        $fullMessage = $reason !== '' && ! Str::contains(Str::lower($message), Str::lower($reason))
            ? $message.' Lý do: '.rtrim($reason, " .").'.'
            : $message;

        throw ValidationException::withMessages([
            'venue_cluster_id' => $fullMessage,
            'access_restriction_code' => $code,
            'access_restriction_reason' => $reason !== '' ? $reason : 'Lý do chưa được cập nhật.',
        ]);
    }

    private function lockedMessage(object $cluster, ?object $restriction): string
    {
        if ($restriction?->restriction_type === 'platform_fee_overdue') {
            return 'Cụm sân đang bị khóa vì phí nền tảng quá hạn thanh toán.';
        }

        if ($restriction?->restriction_type === 'contract_termination'
            || Str::contains(Str::lower((string) $cluster->status_reason), ['cham dut', 'chấm dứt'])) {
            return 'Cụm sân đang bị khóa vì đang trong quy trình chấm dứt hợp đồng đối tác.';
        }

        if ($restriction?->restriction_type === 'admin_manual') {
            return 'Cụm sân đang bị quản trị viên khóa.';
        }

        return 'Cụm sân đang bị khóa và không thể thực hiện thao tác quản lý này.';
    }

    private function resolveClusterId(Request $request): ?string
    {
        $path = $request->path();
        $routeId = $request->route('id');
        $skipGenericRouteIdLookup = Str::contains($path, 'base-prices')
            || (Str::contains($path, 'api/owner/staff/') && ! Str::contains($path, 'staff-shifts'));

        if ($this->isResolvableId($routeId) && Str::contains($path, 'schedule-locks')) {
            $fromScheduleLock = DB::table('slot_locks')->where('id', $routeId)->value('venue_cluster_id');
            if ($fromScheduleLock) {
                return (string) $fromScheduleLock;
            }
        }

        // Resolve resource IDs before the generic cluster-ID fallback. Numeric
        // IDs can overlap across tables (for example price slot 1 and cluster
        // 1), and resolving the wrong cluster would enforce the wrong policy.
        foreach ([
            'price-slots' => 'price_slots',
            'holiday-prices' => 'holiday_prices',
            'venue-services' => 'venue_cluster_services',
        ] as $pathPart => $table) {
            if ($this->isResolvableId($routeId) && Str::contains($path, $pathPart)) {
                $fromResource = DB::table($table)->where('id', $routeId)->value('venue_cluster_id');
                if ($fromResource) {
                    return (string) $fromResource;
                }
            }
        }

        if ($this->isResolvableId($routeId) && Str::contains($path, 'staff-shifts')) {
            $table = Str::contains($path, '/schedules')
                ? 'venue_staff_shift_schedules'
                : 'venue_staff_shifts';
            $fromShiftResource = DB::table($table)->where('id', $routeId)->value('venue_cluster_id');
            if ($fromShiftResource) {
                return (string) $fromShiftResource;
            }
        }

        if (Str::contains($path, 'finance/withdrawals')) {
            $walletId = $request->input('owner_wallet_id');
            if ($this->isResolvableId($walletId)) {
                $fromWallet = DB::table('owner_wallets')->where('id', $walletId)->value('venue_cluster_id');
                if ($fromWallet) {
                    return (string) $fromWallet;
                }
            }
        }

        if ($this->isResolvableId($routeId) && ! $skipGenericRouteIdLookup) {
            if (Str::contains($path, 'termination-requests')) {
                $fromTermination = DB::table('partner_termination_requests')->where('id', $routeId)->value('venue_cluster_id');
                if ($fromTermination) {
                    return (string) $fromTermination;
                }
            }

            if (Str::contains($path, 'bookings')) {
                $fromBooking = DB::table('bookings')->where('id', $routeId)->value('venue_cluster_id');
                if ($fromBooking) {
                    return (string) $fromBooking;
                }
            }

            if (Str::contains($path, 'finance/withdrawals')) {
                $walletId = $request->input('owner_wallet_id');
                if ($this->isResolvableId($walletId)) {
                    $fromWallet = DB::table('owner_wallets')->where('id', $walletId)->value('venue_cluster_id');
                    if ($fromWallet) {
                        return (string) $fromWallet;
                    }
                }

                $fromWithdrawal = DB::table('owner_withdrawal_requests')
                    ->join('owner_wallets', 'owner_wallets.id', '=', 'owner_withdrawal_requests.owner_wallet_id')
                    ->where('owner_withdrawal_requests.id', $routeId)
                    ->value('owner_wallets.venue_cluster_id');
                if ($fromWithdrawal) {
                    return (string) $fromWithdrawal;
                }
            }

            if (Str::contains($path, 'refunds')) {
                $fromRefund = DB::table('refunds')
                    ->join('bookings', 'bookings.id', '=', 'refunds.booking_id')
                    ->where('refunds.id', $routeId)
                    ->value('bookings.venue_cluster_id');
                if ($fromRefund) {
                    return (string) $fromRefund;
                }
            }

            if (Str::contains($path, 'platform-fees')) {
                $fromLedger = DB::table('venue_platform_fee_ledgers')
                    ->where('id', $routeId)
                    ->value('venue_cluster_id');
                if ($fromLedger) {
                    return (string) $fromLedger;
                }

                $fromArrangement = DB::table('platform_fee_payment_arrangements')
                    ->where('id', $routeId)
                    ->value('venue_cluster_id');
                if ($fromArrangement) {
                    return (string) $fromArrangement;
                }
            }

            if (Str::contains($path, 'matchmaking-posts')) {
                $fromPost = DB::table('player_posts')
                    ->join('bookings', 'bookings.id', '=', 'player_posts.booking_id')
                    ->where('player_posts.id', $routeId)
                    ->value('bookings.venue_cluster_id');
                if ($fromPost) {
                    return (string) $fromPost;
                }
            }
        }

        // A recurring-group payment route uses a group code instead of a
        // numeric resource id. Resolve it from one of the group's bookings so
        // a locked cluster cannot be bypassed by omitting the cluster header.
        $groupCode = $request->route('groupCode');
        if (Str::contains($path, 'recurring-groups') && is_string($groupCode) && $groupCode !== '') {
            $fromRecurringGroup = DB::table('bookings')
                ->where('recurring_group_code', $groupCode)
                ->value('venue_cluster_id');
            if ($fromRecurringGroup) {
                return (string) $fromRecurringGroup;
            }
        }

        $route = $request->route();
        if ($route) {
            foreach ($route->parameters() as $key => $value) {
                if (! $this->isResolvableId($value)) {
                    continue;
                }

                $keyLower = strtolower($key);
                if (Str::contains($keyLower, 'cluster')) {
                    return (string) $value;
                }

                if (Str::contains($keyLower, 'court')) {
                    $fromCourt = DB::table('venue_courts')->where('id', $value)->value('venue_cluster_id');
                    if ($fromCourt) {
                        return (string) $fromCourt;
                    }
                }

                if (Str::contains($keyLower, 'slot')) {
                    $fromSlot = DB::table('price_slots')->where('id', $value)->value('venue_cluster_id');
                    if ($fromSlot) {
                        return (string) $fromSlot;
                    }
                }

                if (Str::contains($keyLower, 'lock')) {
                    $fromLock = DB::table('slot_locks')->where('id', $value)->value('venue_cluster_id');
                    if ($fromLock) {
                        return (string) $fromLock;
                    }
                }

                if (Str::contains($keyLower, 'voucher')) {
                    $voucher = DB::table('vouchers')->where('id', $value)->first();
                    if ($voucher && $voucher->owner_type === 'venue') {
                        return (string) $voucher->owner_id;
                    }
                }

                if (Str::contains($keyLower, 'staff') || Str::contains($keyLower, 'user')) {
                    $fromStaff = DB::table('venue_staff_assignments')->where('user_id', $value)->value('venue_cluster_id');
                    if ($fromStaff) {
                        return (string) $fromStaff;
                    }
                }

                if ($keyLower === 'id') {
                    if ($skipGenericRouteIdLookup) {
                        continue;
                    }

                    $fromCluster = DB::table('venue_clusters')->where('id', $value)->value('id');
                    if ($fromCluster) {
                        return (string) $fromCluster;
                    }

                    foreach ([
                        'venue_courts',
                        'price_slots',
                        'holiday_prices',
                        'slot_locks',
                        'venue_cluster_services',
                        'venue_posts',
                        'venue_staff_shifts',
                        'venue_staff_shift_schedules',
                        'venue_platform_fee_ledgers',
                        'platform_fee_payment_arrangements',
                    ] as $table) {
                        $fromResource = DB::table($table)->where('id', $value)->value('venue_cluster_id');
                        if ($fromResource) {
                            return (string) $fromResource;
                        }
                    }

                    $voucher = DB::table('vouchers')->where('id', $value)->first();
                    if ($voucher && $voucher->owner_type === 'venue') {
                        return (string) $voucher->owner_id;
                    }

                    $fromStaff = DB::table('venue_staff_assignments')->where('user_id', $value)->value('venue_cluster_id');
                    if ($fromStaff) {
                        return (string) $fromStaff;
                    }
                }
            }
        }

        $courtId = $request->input('venue_court_id');
        if ($this->isResolvableId($courtId)) {
            $fromCourt = DB::table('venue_courts')->where('id', $courtId)->value('venue_cluster_id');
            if ($fromCourt) {
                return (string) $fromCourt;
            }
        }

        // Creation endpoints commonly send the cluster in the body. Resolve
        // this after route resources so a stale body/header cannot bypass the
        // lock of the resource being edited.
        $clusterId = $request->input('venue_cluster_id') ?? $request->query('venue_cluster_id');
        if ($this->isResolvableId($clusterId)) {
            return (string) $clusterId;
        }

        $headerClusterId = $request->header('X-Venue-Cluster-Id');
        if ($this->isResolvableId($headerClusterId)) {
            return (string) $headerClusterId;
        }

        return null;
    }

    private function isResolvableId(mixed $value): bool
    {
        return is_numeric($value) || (is_string($value) && Str::isUuid($value));
    }

    private function isLimitedAction(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (Str::startsWith($path, [
            'api/owner/booking-configs',
            'api/owner/base-prices',
            'api/owner/price-slots',
            'api/owner/holiday-prices',
            'api/owner/vouchers',
            'api/owner/venue-posts',
            'api/owner/matchmaking-posts',
            'api/owner/staff',
            'api/owner/staff-shifts',
            'api/owner/venue-services',
        ])) {
            return true;
        }

        if (preg_match('#^api/owner/venue-clusters/[^/]+/services(?:/|$)#', $path) === 1) {
            return true;
        }

        // Adding a new booking is restricted, while collecting or managing an
        // existing booking remains available for operational continuity.
        return preg_match('#^api/owner/bookings/(counter|recurring(?:/preview)?)$#', $path) === 1;
    }

    private function isPlatformFeeResolutionAction(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return preg_match('#^api/owner/platform-fees/[^/]+/(?:payment|pay-from-balance)$#', $path) === 1
            || $path === 'api/owner/platform-fees/prepay'
            || preg_match('#^api/owner/platform-fees/arrangements/[^/]+/(?:accept|reject)$#', $path) === 1;
    }

    private function isAllowedDuringTermination(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return Str::startsWith($path, [
            'api/owner/termination-requests',
            'api/owner/refunds',
            'api/owner/finance/withdrawals',
            'api/owner/wallet',
        ]) || $this->isAllowedBookingMaintenance($path);
    }

    private function isAllowedOperationalAction(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return Str::startsWith($path, [
            'api/owner/refunds',
            'api/owner/finance/withdrawals',
            'api/owner/wallet',
        ]) || $this->isAllowedBookingMaintenance($path);
    }

    private function isAllowedBookingMaintenance(string $path): bool
    {
        return preg_match('#^api/owner/bookings/(?:[^/]+/(?:payments/collect|status)|recurring-groups/[^/]+/payments/collect)$#', $path) === 1;
    }
}
