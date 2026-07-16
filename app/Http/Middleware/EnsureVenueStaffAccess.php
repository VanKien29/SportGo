<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenueStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = $request->user()?->roles()->pluck('roles.name')->all() ?? [];

        if (in_array('venue_owner', $roles, true) || ! in_array('venue_staff', $roles, true)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === 'api/owner/staff-dashboard/overview') {
            return $request->isMethod('GET') ? $next($request) : $this->deny();
        }

        if ($path === 'api/owner/ui-settings') {
            return in_array($request->method(), ['GET', 'POST'], true) ? $next($request) : $this->deny();
        }

        if (preg_match('#^api/owner/(venue-clusters|venue-courts)(/[^/]+)?$#', $path)) {
            return $request->isMethod('GET') ? $next($request) : $this->deny();
        }

        if (in_array($path, ['api/owner/court-types', 'api/owner/amenities'], true)) {
            return $request->isMethod('GET') ? $next($request) : $this->deny();
        }

        if (Str::startsWith($path, 'api/owner/bookings')) {
            return $this->isOperationalBookingRequest($request, $path) ? $next($request) : $this->deny();
        }

        if (Str::startsWith($path, 'api/owner/staff-shifts/my-schedules')) {
            return $request->isMethod('GET') ? $next($request) : $this->deny();
        }

        if (preg_match('#^api/owner/staff-shifts/schedules/[^/]+/(check-in|check-out)$#', $path)) {
            return $request->isMethod('POST') ? $next($request) : $this->deny();
        }

        if ($path === 'api/owner/work-center' || Str::startsWith($path, 'api/owner/work-center/notifications/')) {
            return in_array($request->method(), ['GET', 'PATCH'], true) ? $next($request) : $this->deny();
        }

        return $this->deny();
    }

    private function isOperationalBookingRequest(Request $request, string $path): bool
    {
        if ($request->isMethod('GET')) {
            return true;
        }

        if ($request->isMethod('POST')) {
            return $path === 'api/owner/bookings/counter'
                || Str::contains($path, '/recurring')
                || Str::contains($path, '/payments/collect');
        }

        return $request->isMethod('PATCH')
            && preg_match('#^api/owner/bookings/[^/]+/(status|court)$#', $path) === 1;
    }

    private function deny(): Response
    {
        return response()->json([
            'message' => 'Tài khoản nhân viên sân không có quyền thực hiện thao tác này.',
        ], 403);
    }
}
