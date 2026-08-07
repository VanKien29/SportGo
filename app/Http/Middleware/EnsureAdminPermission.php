<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminRoutePermissionResolver;
use App\Services\Auth\SystemPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function __construct(
        private readonly AdminRoutePermissionResolver $resolver,
        private readonly SystemPermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $required = $this->resolver->resolve($request);
        $codes = $required['permissions'] ?? [];

        if ($codes === []) {
            return response()->json([
                'message' => 'Chức năng quản trị này chưa được cấu hình quyền truy cập.',
            ], 403);
        }

        if (($required['mode'] ?? 'all') === 'any') {
            $this->permissions->authorizeAny($request->user(), $codes);
        } else {
            $this->permissions->authorizeAll($request->user(), $codes);
        }

        return $next($request);
    }
}
