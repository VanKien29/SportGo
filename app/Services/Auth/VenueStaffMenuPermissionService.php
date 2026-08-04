<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\VenueStaffMenuPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VenueStaffMenuPermissionService
{
    public function permissionMap(User $user): array
    {
        if (! Schema::hasTable('venue_staff_menu_permissions')) {
            return [];
        }

        return DB::table('venue_staff_menu_permissions')
            ->join('venue_staff_assignments', function ($join): void {
                $join->on('venue_staff_assignments.user_id', '=', 'venue_staff_menu_permissions.user_id')
                    ->on('venue_staff_assignments.venue_cluster_id', '=', 'venue_staff_menu_permissions.venue_cluster_id')
                    ->where('venue_staff_assignments.status', '=', 'active');
            })
            ->where('venue_staff_menu_permissions.user_id', $user->id)
            ->select('venue_staff_menu_permissions.venue_cluster_id', 'venue_staff_menu_permissions.menu_key')
            ->distinct()
            ->get()
            ->groupBy(fn ($item) => (string) $item->venue_cluster_id)
            ->map(fn ($items) => $items->pluck('menu_key')->unique()->sort()->values()->all())
            ->all();
    }

    public function keysForCluster(User $user, string $clusterId): array
    {
        return $this->permissionMap($user)[(string) $clusterId] ?? [];
    }

    public function hasAnyForCluster(User $user, string $clusterId, array $menuKeys): bool
    {
        if (! $this->hasActiveAssignment($user, $clusterId)) {
            return false;
        }

        $allowed = collect($this->keysForCluster($user, $clusterId));

        return $allowed->intersect($menuKeys)->isNotEmpty();
    }

    public function hasAnyForRequest(User $user, Request $request, array $menuKeys): bool
    {
        $clusterId = $this->resolveClusterId($user, $request);

        if ($clusterId !== null) {
            return $this->hasAnyForCluster($user, $clusterId, $menuKeys);
        }

        return collect($this->permissionMap($user))
            ->contains(fn (array $keys): bool => collect($keys)->intersect($menuKeys)->isNotEmpty());
    }

    public function hasActiveAssignment(User $user, ?string $clusterId = null): bool
    {
        $query = DB::table('venue_staff_assignments')
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if ($clusterId !== null) {
            $query->where('venue_cluster_id', $clusterId);
        }

        return $query->exists();
    }

    public function sync(User $staff, string $clusterId, array $menuKeys, User $grantedBy): void
    {
        $menuKeys = collect($menuKeys)->filter()->unique()->values()->all();
        $invalidKeys = array_diff($menuKeys, VenueStaffMenuCatalog::keys());

        if ($invalidKeys !== []) {
            throw ValidationException::withMessages([
                'menu_keys' => 'Danh sách quyền menu có giá trị không hợp lệ.',
            ]);
        }

        $hasAllClusterAssignment = DB::table('venue_staff_assignments')
            ->where('user_id', $staff->id)
            ->where('venue_cluster_id', $clusterId)
            ->where('scope_type', 'all_cluster')
            ->where('status', 'active')
            ->exists();

        $requiresAllCluster = collect($menuKeys)->contains(
            fn (string $key): bool => VenueStaffMenuCatalog::requiresAllCluster($key)
        );

        if ($requiresAllCluster && ! $hasAllClusterAssignment) {
            throw ValidationException::withMessages([
                'menu_keys' => 'Quyền Quản lý voucher sân chỉ áp dụng cho nhân viên được phân công toàn bộ cụm sân.',
            ]);
        }

        VenueStaffMenuPermission::query()
            ->where('user_id', $staff->id)
            ->where('venue_cluster_id', $clusterId)
            ->delete();

        foreach ($menuKeys as $menuKey) {
            VenueStaffMenuPermission::query()->create([
                'user_id' => $staff->id,
                'venue_cluster_id' => $clusterId,
                'menu_key' => $menuKey,
                'granted_by' => $grantedBy->id,
            ]);
        }
    }

    public function resolveClusterId(User $user, Request $request): ?string
    {
        $relative = preg_replace('#^api/owner/#', '', $request->path());

        if (preg_match('#^bookings/(\d+)(?:/|$)#', $relative, $matches)) {
            return $this->scalarId(DB::table('bookings')->where('id', $matches[1])->value('venue_cluster_id'));
        }

        if (preg_match('#^bookings/recurring-groups/([^/]+)/payments/collect$#', $relative, $matches)) {
            return $this->scalarId(DB::table('bookings')->where('recurring_group_code', $matches[1])->value('venue_cluster_id'));
        }

        if (preg_match('#^vouchers/(\d+)(?:/|$)#', $relative, $matches)) {
            return $this->scalarId(DB::table('vouchers')
                ->where('id', $matches[1])
                ->where('owner_type', 'venue')
                ->value('owner_id'));
        }

        if (preg_match('#^staff-shifts/schedules/(\d+)(?:/|$)#', $relative, $matches)) {
            return $this->scalarId(DB::table('venue_staff_shift_schedules')->where('id', $matches[1])->value('venue_cluster_id'));
        }

        if (preg_match('#^venue-courts/(\d+)(?:/|$)#', $relative, $matches)) {
            return $this->scalarId(DB::table('venue_courts')->where('id', $matches[1])->value('venue_cluster_id'));
        }

        if (preg_match('#^venue-clusters/(\d+)(?:/|$)#', $relative, $matches)) {
            return $matches[1];
        }

        $explicit = $request->route('clusterId')
            ?? $request->route('venueClusterId')
            ?? $request->input('venue_cluster_id')
            ?? $request->query('venue_cluster_id')
            ?? $request->header('X-Venue-Cluster-Id');

        if ($explicit !== null && $explicit !== '') {
            return (string) $explicit;
        }

        $assignedClusterIds = DB::table('venue_staff_assignments')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('venue_cluster_id')
            ->unique()
            ->values();

        return $assignedClusterIds->count() === 1 ? (string) $assignedClusterIds->first() : null;
    }

    private function scalarId(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
