<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectInactiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status === 'locked') {
            $user->revokeAllAccess();

            return response()->json([
                'message' => 'Tài khoản của bạn đang bị khóa. Vui lòng liên hệ quản trị viên.',
                'status_reason' => $user->status_reason,
                'lock_type' => $user->lock_type,
                'locked_until' => $user->locked_until,
            ], 423);
        }

        if ($user->status === 'deactivated') {
            $user->revokeAllAccess();

            return response()->json([
                'message' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.',
            ], 401);
        }

        return $next($request);
    }
}
