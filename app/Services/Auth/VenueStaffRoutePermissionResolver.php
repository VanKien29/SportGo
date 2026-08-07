<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VenueStaffRoutePermissionResolver
{
    public function resolve(Request $request): array
    {
        $relative = Str::after($request->path(), 'api/owner/');
        $method = strtoupper($request->method());

        if ($method === 'GET' && ($relative === 'ui-settings' || preg_match('#^venue-clusters(?:/\d+)?$#', $relative))) {
            return ['infrastructure' => true, 'menu_keys' => []];
        }

        if ($relative === 'ui-settings') {
            return $this->menus('settings');
        }

        if (Str::startsWith($relative, 'staff-dashboard/overview')) {
            return $this->menus('dashboard');
        }

        if (Str::startsWith($relative, 'staff-shifts/my-schedules')
            || Str::contains($relative, ['/check-in', '/check-out'])) {
            return $this->menus(['dashboard', 'schedules']);
        }

        if (Str::startsWith($relative, 'venue-courts') && $method === 'GET') {
            return $this->menus(['bookings', 'counter_booking']);
        }

        if (Str::startsWith($relative, 'booking-configs') && $method === 'GET') {
            return $this->menus('counter_booking');
        }

        if (Str::startsWith($relative, 'bookings')) {
            if (Str::startsWith($relative, ['bookings/counter', 'bookings/recurring'])) {
                return $this->menus('counter_booking');
            }

            return $this->menus(['bookings', 'counter_booking']);
        }

        if (Str::startsWith($relative, 'vouchers')) {
            return $this->menus('vouchers');
        }

        if (Str::startsWith($relative, 'work-center')) {
            return $this->menus(['dashboard', 'chat']);
        }

        return ['owner_only' => true, 'menu_keys' => []];
    }

    private function menus(string|array $menuKeys): array
    {
        return ['owner_only' => false, 'infrastructure' => false, 'menu_keys' => (array) $menuKeys];
    }
}
