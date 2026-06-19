<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnforceVenueAccessRestrictions
{
    public function handle(Request $request, Closure $next): Response
    {
        // Exclude lock appeals and resume route from restrictions check
        if ($request->is('*owner/lock-appeals*') || $request->is('*owner/venue-clusters/*/resume*')) {
            return $next($request);
        }

        // Only enforce restrictions on write/mutating methods
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $clusterId = $this->resolveClusterId($request);

        if ($clusterId) {
            $cluster = DB::table('venue_clusters')->where('id', $clusterId)->first();
            if ($cluster && in_array($cluster->status, ['locked', 'pending_contract'], true)) {
                $message = $cluster->status === 'locked' 
                    ? 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.' 
                    : 'Cụm sân đang chờ hoàn tất ký kết hợp đồng đối tác.';
                
                throw ValidationException::withMessages([
                    'venue_cluster_id' => $message,
                ]);
            }
        }

        return $next($request);
    }

    private function resolveClusterId(Request $request): ?string
    {
        // 1. Check direct venue_cluster_id in inputs/query
        $clusterId = $request->input('venue_cluster_id') ?? $request->query('venue_cluster_id');
        if ($clusterId && Str::isUuid($clusterId)) {
            return $clusterId;
        }

        // 2. Check route parameters dynamically by key
        $route = $request->route();
        if ($route) {
            foreach ($route->parameters() as $key => $val) {
                if ($val && Str::isUuid($val)) {
                    $keyLower = strtolower($key);
                    
                    if (Str::contains($keyLower, 'cluster')) {
                        return $val;
                    }
                    
                    if (Str::contains($keyLower, 'court')) {
                        $clusterIdFromCourt = DB::table('venue_courts')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromCourt) {
                            return $clusterIdFromCourt;
                        }
                    }
                    
                    if (Str::contains($keyLower, 'slot')) {
                        $clusterIdFromSlot = DB::table('price_slots')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromSlot) {
                            return $clusterIdFromSlot;
                        }
                    }
                    
                    if (Str::contains($keyLower, 'lock')) {
                        $clusterIdFromLock = DB::table('slot_locks')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromLock) {
                            return $clusterIdFromLock;
                        }
                    }
                    
                    if (Str::contains($keyLower, 'voucher')) {
                        $voucher = DB::table('vouchers')->where('id', $val)->first();
                        if ($voucher && $voucher->owner_type === 'venue') {
                            return $voucher->owner_id;
                        }
                    }
                    
                    if (Str::contains($keyLower, 'staff') || Str::contains($keyLower, 'user')) {
                        $clusterIdFromStaff = DB::table('venue_staff_assignments')
                            ->where('user_id', $val)
                            ->value('venue_cluster_id');
                        if ($clusterIdFromStaff) {
                            return $clusterIdFromStaff;
                        }
                    }
                    
                    if ($keyLower === 'id') {
                        // Check if it's a cluster ID
                        if (DB::table('venue_clusters')->where('id', $val)->exists()) {
                            return $val;
                        }
                        
                        // Otherwise try other tables in order
                        $clusterIdFromCourt = DB::table('venue_courts')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromCourt) return $clusterIdFromCourt;

                        $clusterIdFromSlot = DB::table('price_slots')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromSlot) return $clusterIdFromSlot;

                        $clusterIdFromLock = DB::table('slot_locks')->where('id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromLock) return $clusterIdFromLock;

                        $voucher = DB::table('vouchers')->where('id', $val)->first();
                        if ($voucher && $voucher->owner_type === 'venue') {
                            return $voucher->owner_id;
                        }

                        $clusterIdFromStaff = DB::table('venue_staff_assignments')->where('user_id', $val)->value('venue_cluster_id');
                        if ($clusterIdFromStaff) return $clusterIdFromStaff;
                    }
                }
            }
        }

        // 3. Check indirect input parameters
        $courtId = $request->input('venue_court_id');
        if ($courtId && Str::isUuid($courtId)) {
            $clusterIdFromCourt = DB::table('venue_courts')->where('id', $courtId)->value('venue_cluster_id');
            if ($clusterIdFromCourt) {
                return $clusterIdFromCourt;
            }
        }

        return null;
    }
}
