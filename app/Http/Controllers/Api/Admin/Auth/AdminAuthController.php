<?php

namespace App\Http\Controllers\Api\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function __construct(private readonly RoleRedirectService $roleRedirectService) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Vui lòng nhập tên đăng nhập, email hoặc số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = $this->findUserByIdentifier($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Sai tài khoản hoặc mật khẩu.',
            ]);
        }

        if (! $this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản không có quyền truy cập trang quản trị.',
            ]);
        }

        if ($user->status === 'pending_verify') {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản chưa xác thực email.',
            ]);
        }

        if ($user->status === 'locked') {
            return response()->json([
                'message' => 'Tài khoản của bạn đang bị khóa.',
                'status_reason' => $user->status_reason,
                'lock_type' => $user->lock_type,
                'locked_until' => $user->locked_until,
            ], 423);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản không ở trạng thái hoạt động.',
            ]);
        }

        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        return response()->json(array_merge([
            'message' => 'Đăng nhập quản trị thành công',
        ], $this->roleRedirectService->payload($user, $token)));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->status === 'locked') {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Tài khoản của bạn đang bị khóa.',
                'status_reason' => $user->status_reason,
                'lock_type' => $user->lock_type,
                'locked_until' => $user->locked_until,
            ], 423);
        }

        return response()->json(array_merge([
            'message' => 'Lấy thông tin quản trị thành công.',
        ], $this->roleRedirectService->payload($user)));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất quản trị thành công.',
        ]);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    private function isAdminUser(User $user): bool
    {
        return $this->roleRedirectService->isAdminAreaUser($user);
    }
}
