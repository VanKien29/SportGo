<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = $request->user()?->roles()->pluck('roles.name')->all() ?? [];

        if (! array_intersect($roles, ['venue_owner', 'venue_staff'])) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập khu vực chủ sân.',
            ], 403);
        }

        return $next($request);
    }
}
