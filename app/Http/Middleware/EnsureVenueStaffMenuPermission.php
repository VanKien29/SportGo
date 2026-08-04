<?php

namespace App\Http\Middleware;

use App\Services\VenueStaffAccessService;
use App\Services\Auth\VenueStaffMenuPermissionService;
use App\Services\Auth\VenueStaffRoutePermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVenueStaffMenuPermission
{
    public function __construct(
        private readonly VenueStaffRoutePermissionResolver $resolver,
        private readonly VenueStaffMenuPermissionService $permissions,
        private readonly VenueStaffAccessService $staffAccess,
    ) {}

    public function handle(Request $request, Closure $next, string ...$explicitMenuKeys): Response
    {
        $user = $request->user();
        $roles = $user?->roles()->pluck('roles.name')->all() ?? [];

        if (! in_array('venue_staff', $roles, true)) {
            return $next($request);
        }

        $clusterId = $this->permissions->resolveClusterId($user, $request);

        if (in_array('venue_owner', $roles, true)
            && ($clusterId === null || $this->staffAccess->ownsCluster($user, $clusterId))) {
            return $next($request);
        }

        if ($explicitMenuKeys !== []) {
            if (! $this->permissions->hasAnyForRequest($user, $request, $explicitMenuKeys)) {
                return $this->forbidden();
            }

            return $next($request);
        }

        $required = $this->resolver->resolve($request);

        if ($required['owner_only'] ?? false) {
            return response()->json([
                'message' => 'Chức năng này chỉ dành cho chủ sân.',
            ], 403);
        }

        if ($required['infrastructure'] ?? false) {
            return $this->permissions->hasActiveAssignment($user)
                ? $next($request)
                : $this->forbidden();
        }

        if (! $this->permissions->hasAnyForRequest($user, $request, $required['menu_keys'] ?? [])) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function forbidden(): Response
    {
        return response()->json([
            'message' => 'Bạn chưa được chủ sân cấp quyền sử dụng chức năng này.',
        ], 403);
    }
}
