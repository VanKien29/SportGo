<?php

namespace App\Http\Middleware;

use App\Models\VenueStaffShiftSchedule;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits operational booking actions to the staff member's active shift.
 *
 * This is deliberately applied to mutation routes only. Reading schedules or
 * bookings and checking in/out of a shift must remain available so a staff
 * member can prepare for and start their assigned shift.
 */
class EnsureActiveStaffShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roles = $user?->roles()->pluck('roles.name')->all() ?? [];

        // Owners are not bound by the staff rota, even if an account also has
        // the venue_staff role for compatibility with existing permissions.
        if (! $user || ! in_array('venue_staff', $roles, true) || in_array('venue_owner', $roles, true)) {
            return $next($request);
        }

        if (! Schema::hasTable('venue_staff_shift_schedules')) {
            return $this->forbidden(
                'Chưa thể xác định ca trực của bạn. Vui lòng liên hệ chủ sân.',
                'STAFF_SHIFT_UNAVAILABLE',
            );
        }

        $clusterId = $this->resolveClusterId($request);
        if ($clusterId === null) {
            return $this->forbidden(
                'Không xác định được cụm sân của thao tác. Vui lòng chọn đúng cụm sân rồi thử lại.',
                'STAFF_SHIFT_CONTEXT_MISSING',
            );
        }

        $timezone = (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);
        $today = $now->toDateString();

        $hasActiveShift = VenueStaffShiftSchedule::query()
            ->where('user_id', $user->id)
            ->where('venue_cluster_id', $clusterId)
            ->whereDate('date', $today)
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->get(['date', 'start_time', 'end_time', 'status', 'check_out_at'])
            ->contains(function (VenueStaffShiftSchedule $schedule) use ($now, $today, $timezone): bool {
                if ($schedule->check_out_at) {
                    return false;
                }

                try {
                    $start = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $today.' '.substr((string) $schedule->start_time, 0, 8),
                        $timezone,
                    );
                    $end = Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $today.' '.substr((string) $schedule->end_time, 0, 8),
                        $timezone,
                    );
                } catch (\Throwable) {
                    return false;
                }

                return $now->greaterThanOrEqualTo($start) && $now->lessThan($end);
            });

        if (! $hasActiveShift) {
            return $this->forbidden(
                'Bạn hiện không có ca trực đang hoạt động tại cụm sân này. Chỉ có thể thực hiện thao tác trong thời gian ca được phân công.',
                'STAFF_OUTSIDE_SHIFT',
                [
                    'venue_cluster_id' => (int) $clusterId,
                    'business_date' => $today,
                    'business_time' => $now->format('H:i:s'),
                ],
            );
        }

        return $next($request);
    }

    private function resolveClusterId(Request $request): ?string
    {
        // Existing booking actions must be resolved from the booking itself,
        // not from a caller-supplied cluster, so a staff member cannot switch
        // the context to another cluster in the request body.
        $bookingId = $request->route('id');
        if (is_numeric($bookingId)) {
            $clusterId = DB::table('bookings')
                ->where('id', $bookingId)
                ->value('venue_cluster_id');

            if ($clusterId !== null) {
                return (string) $clusterId;
            }
        }

        $groupCode = $request->route('groupCode');
        if (is_string($groupCode) && $groupCode !== '') {
            $clusterId = DB::table('bookings')
                ->where('recurring_group_code', $groupCode)
                ->value('venue_cluster_id');

            if ($clusterId !== null) {
                return (string) $clusterId;
            }
        }

        $courtIds = collect([
            $request->input('venue_court_id'),
            ...collect($request->input('time_ranges', []))
                ->pluck('venue_court_id')
                ->all(),
        ])->filter(fn ($id): bool => is_numeric($id));

        foreach ($courtIds as $courtId) {
            $clusterId = DB::table('venue_courts')
                ->where('id', $courtId)
                ->value('venue_cluster_id');

            if ($clusterId !== null) {
                return (string) $clusterId;
            }
        }

        $clusterId = $request->input('venue_cluster_id')
            ?? $request->query('venue_cluster_id')
            ?? $request->header('X-Venue-Cluster-Id');

        if (is_numeric($clusterId)) {
            return (string) $clusterId;
        }

        return null;
    }

    private function forbidden(string $message, string $code, array $data = []): Response
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ], 403);
    }
}
