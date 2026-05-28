<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'system_staff'];

    public function __construct(private readonly RoleRedirectService $roleRedirectService)
    {
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = $this->findGoogleUser($googleUser->getId(), $googleUser->getEmail());
        $isNewUser = false;

        if ($user) {
            $user->forceFill([
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?: now(),
                'avatar_url' => $googleUser->getAvatar() ?: $user->avatar_url,
                'status' => $user->status === 'pending_verify' ? 'active' : $user->status,
            ])->save();
        } else {
            $isNewUser = true;
            $user = User::query()->create([
                'username' => $this->uniqueUsername($googleUser->getEmail(), $googleUser->getName()),
                'full_name' => $googleUser->getName() ?: 'SportGo User',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'phone' => null,
                'password' => Hash::make(Str::random(32)),
                'avatar_url' => $googleUser->getAvatar(),
                'status' => 'active',
                'verification_channel' => 'email',
            ]);

            $this->roleRedirectService->assignDefaultUserRole($user);
        }

        if ($this->isAdminUser($user)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tài khoản quản trị vui lòng đăng nhập tại trang Admin.'], 422)
                : redirect('/admin/login?admin_login_required=1');
        }

        if ($user->status === 'locked') {
            return $request->expectsJson()
                ? response()->json([
                    'message' => 'Tài khoản của bạn đang bị khóa.',
                    'status_reason' => $user->status_reason,
                    'lock_type' => $user->lock_type,
                    'locked_until' => $user->locked_until,
                ], 423)
                : redirect('/login?google_error=locked');
        }

        if ($user->status !== 'active') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tài khoản không ở trạng thái hoạt động.'], 422)
                : redirect('/login?google_error=inactive');
        }

        $token = $user->createToken('sportgo-google')->plainTextToken;
        $payload = array_merge([
            'message' => 'Đăng nhập Google thành công',
        ], $this->roleRedirectService->payload($user, $token));

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect('/auth/google/callback?'.http_build_query([
            'token' => $token,
            'role_group' => $payload['role_group'],
            'redirect_to' => $payload['redirect_to'],
            'needs_password_setup' => $isNewUser ? '1' : '0',
        ]));
    }

    private function findGoogleUser(string $googleId, ?string $email): ?User
    {
        $user = User::query()->where('google_id', $googleId)->first();

        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();
        }

        return $user;
    }

    private function isAdminUser(User $user): bool
    {
        $roles = $this->roleRedirectService->roles($user);

        return (bool) array_intersect($roles, self::ADMIN_ROLES);
    }

    private function uniqueUsername(?string $email, ?string $name): string
    {
        $base = $email ? Str::before($email, '@') : ($name ?: 'sportgo_user');
        $base = Str::of($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->limit(40, '')
            ->value() ?: 'sportgo_user';

        $username = $base;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (! User::query()->where('username', $username)->exists()) {
                return $username;
            }

            $username = Str::limit($base, 40, '').'_'.Str::lower(Str::random(5));
        }

        return 'sportgo_'.Str::lower(Str::random(10));
    }
}
