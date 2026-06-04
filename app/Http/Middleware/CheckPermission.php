<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permissionCode)
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Vui lòng đăng nhập.'], 401);
            }
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        // 1. Kiểm tra nếu là super_admin thì cho phép qua luôn không cần xét chi tiết
        $isSuperAdmin = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', 'super_admin')
            ->exists();

        if ($isSuperAdmin) {
            return $next($request);
        }

        // 2. Kiểm tra xem user có được gán quyền này thông qua role không
        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $user->id)
            ->where('permissions.code', $permissionCode)
            ->exists();

        // 3. (Tính năng xịn) Kiểm tra xem quyền này có đang bị "thu hồi" đối với riêng user này không
        $isRevoked = DB::table('user_permission_revokes')
            ->join('permissions', 'user_permission_revokes.permission_id', '=', 'permissions.id')
            ->where('user_permission_revokes.user_id', $user->id)
            ->where('permissions.code', $permissionCode)
            ->where('user_permission_revokes.scope_type', 'system') // Chỉ xét phạm vi hệ thống cho admin
            ->exists();

        if (!$hasPermission || $isRevoked) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện hành động này.'], 403);
            }
            abort(403, 'Bạn không có quyền thực hiện hành động này.');
        }

        return $next($request);
    }
}