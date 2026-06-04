<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRoleWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect('/login');
        }

        $roles = $user->roles()->pluck('roles.name')->all() ?? [];

        if (!array_intersect($roles, ['admin', 'super_admin', 'system_staff'])) {
            return redirect('/')->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        }

        return $next($request);
    }
}
