<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $terminationLocked = $cluster->status === 'locked'
            && Str::contains(Str::lower((string) $cluster->status_reason), ['cham dut', 'chấm dứt']);

        if ($terminationLocked || in_array($cluster->status, ['termination_processing', 'termination_locked', 'partner_terminated'], true)) {
            if ($this->isAllowedDuringTermination($request)) {
                return $next($request);
            }

            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cum san dang trong quy trinh cham dut hop dong. Ban chi duoc xu ly booking, hoan tien, rut tien va ho so cham dut.',
            ]);
        }

        if (in_array($cluster->status, ['locked', 'pending'], true)) {
            $message = $cluster->status === 'locked'
                ? 'Cum san dang bi khoa. Vui long lien he quan tri vien.'
                : 'Cum san dang cho hoan tat ky ket hop dong doi tac.';

            throw ValidationException::withMessages([
                'venue_cluster_id' => $message,
            ]);
        }

        return $next($request);
    }

    private function resolveClusterId(Request $request): ?string
    {
        $path = $request->path();
        $routeId = $request->route('id');

        if ($this->isResolvableId($routeId) && Str::contains($path, 'schedule-locks')) {
            $fromScheduleLock = DB::table('slot_locks')->where('id', $routeId)->value('venue_cluster_id');
            if ($fromScheduleLock) {
                return (string) $fromScheduleLock;
            }
        }

        $clusterId = $request->input('venue_cluster_id') ?? $request->query('venue_cluster_id');
        if ($this->isResolvableId($clusterId)) {
            return (string) $clusterId;
        }

        if ($this->isResolvableId($routeId)) {
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
                    $fromCluster = DB::table('venue_clusters')->where('id', $value)->value('id');
                    if ($fromCluster) {
                        return (string) $fromCluster;
                    }

                    foreach ([
                        'venue_courts',
                        'price_slots',
                        'slot_locks',
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

        return null;
    }

    private function isResolvableId(mixed $value): bool
    {
        return is_numeric($value) || (is_string($value) && Str::isUuid($value));
    }

    private function isAllowedDuringTermination(Request $request): bool
    {
        return Str::startsWith(trim($request->path(), '/'), [
            'api/owner/termination-requests',
            'api/owner/refunds',
            'api/owner/finance/withdrawals',
            'api/owner/wallet',
            'api/owner/bookings',
        ]);
    }
}
