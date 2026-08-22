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

        // 1. Basic Infrastructure (Cluster listing & basic layout settings)
        if ($method === 'GET' && ($relative === 'ui-settings' || preg_match('#^venue-clusters(?:/\d+)?$#', $relative))) {
            return ['infrastructure' => true, 'menu_keys' => []];
        }

        if ($relative === 'ui-settings') {
            return $this->menus('settings');
        }

        // 2. Staff Dashboard & Overview
        if (Str::startsWith($relative, 'staff-dashboard/overview')) {
            return $this->menus('dashboard');
        }

        // 3. Staff Attendance, Schedules, Check-in, Check-out & Handover Summary
        if (Str::startsWith($relative, 'staff-shifts/my-schedules')
            || Str::startsWith($relative, 'staff-shifts/attendance-report')
            || Str::contains($relative, ['/check-in', '/check-out', '/handover-summary'])) {
            return $this->menus(['dashboard', 'schedules', 'bookings', 'counter_booking']);
        }

        // 4. Venue Courts & Layouts for POS
        if (Str::startsWith($relative, 'venue-courts') && $method === 'GET') {
            return $this->menus(['bookings', 'counter_booking']);
        }

        // 5. Booking Configurations
        if (Str::startsWith($relative, 'booking-configs') && $method === 'GET') {
            return $this->menus(['counter_booking', 'bookings']);
        }

        // 6. Schedule Locks (Viewing blocked slots in matrix)
        if (Str::startsWith($relative, 'schedule-locks') && $method === 'GET') {
            return $this->menus(['bookings', 'counter_booking', 'schedules']);
        }

        // 7. On-site F&B Services and Retail Orders for POS
        if ($method === 'GET' && preg_match('#^venue-clusters/\d+/services#', $relative)) {
            return $this->menus(['bookings', 'counter_booking', 'services']);
        }

        if (Str::startsWith($relative, 'retail-orders')) {
            return $this->menus(['bookings', 'counter_booking', 'services']);
        }

        // 8. Bookings & POS Counter Operations
        if (Str::startsWith($relative, 'bookings')) {
            if (Str::startsWith($relative, ['bookings/counter', 'bookings/recurring'])) {
                return $this->menus(['counter_booking', 'bookings']);
            }

            return $this->menus(['bookings', 'counter_booking']);
        }

        // 9. Vouchers & Promotion validation
        if (Str::startsWith($relative, 'vouchers')) {
            return $this->menus(['vouchers', 'counter_booking', 'bookings']);
        }

        // 10. Work Center (Internal messages & Notifications)
        if (Str::startsWith($relative, 'work-center')) {
            return $this->menus(['dashboard', 'chat']);
        }

        // 11. Matchmaking Posts
        if (Str::startsWith($relative, 'matchmaking-posts')) {
            return $this->menus(['dashboard', 'matchmaking']);
        }

        return ['owner_only' => true, 'menu_keys' => []];
    }

    private function menus(string|array $menuKeys): array
    {
        return ['owner_only' => false, 'infrastructure' => false, 'menu_keys' => (array) $menuKeys];
    }
}
