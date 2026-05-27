<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AuthOtpMail;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'system_staff'];

    public function __construct(
        private readonly OtpService $otpService,
        private readonly RoleRedirectService $roleRedirectService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->prepareRegisterAccount($data);

        $user = User::query()->create([
            'username' => $data['username'],
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'pending_verify',
            'verification_channel' => 'email',
        ]);

        $this->roleRedirectService->assignDefaultUserRole($user);
        $this->sendRegisterOtp($user);

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để lấy mã xác thực.',
            'email' => $user->email,
        ], 201);
    }

    public function resendRegisterOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Tài khoản không tồn tại.']);
        }

        if ($user->status !== 'pending_verify') {
            throw ValidationException::withMessages(['email' => 'Tài khoản này đã được xác thực hoặc không hợp lệ.']);
        }

        $this->sendRegisterOtp($user);

        return response()->json([
            'message' => 'Đã gửi lại mã xác thực. Vui lòng kiểm tra email của bạn.',
        ]);
    }

    public function verifyRegisterOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $code = $this->otpService->verify($data['email'], 'register', $data['otp'], true);
        $user = $code->user;

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Tài khoản không tồn tại.']);
        }

        $user->forceFill([
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Xác thực tài khoản thành công. Vui lòng đăng nhập.',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Vui lòng nhập tài khoản.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = $this->findUserByIdentifier($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Sai tài khoản hoặc mật khẩu.']);
        }

        if ($this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản quản trị vui lòng đăng nhập tại trang Admin.',
            ]);
        }

        if ($user->status === 'pending_verify') {
            throw ValidationException::withMessages(['login' => 'Tài khoản chưa xác thực email.']);
        }

        if ($user->status === 'locked') {
            return $this->lockedUserResponse($user);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['login' => 'Tài khoản không ở trạng thái hoạt động.']);
        }

        $token = $user->createToken('sportgo-api')->plainTextToken;

        return response()->json(array_merge([
            'message' => 'Đăng nhập thành công',
        ], $this->roleRedirectService->payload($user, $token)));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->status === 'locked') {
            $user->currentAccessToken()?->delete();

            return $this->lockedUserResponse($user);
        }

        return response()->json(array_merge([
            'message' => 'Lấy thông tin tài khoản thành công.',
        ], $this->roleRedirectService->payload($user)));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    private function prepareRegisterAccount(array $data): void
    {
        $conflictingUsers = User::query()
            ->where('username', $data['username'])
            ->orWhere('phone', $data['phone'])
            ->orWhere('email', $data['email'])
            ->get();

        $activeConflicts = $conflictingUsers->where('status', '!=', 'pending_verify');

        if ($activeConflicts->isNotEmpty()) {
            $errors = [];

            if ($activeConflicts->contains('username', $data['username'])) {
                $errors['username'] = 'Tên tài khoản đã tồn tại.';
            }

            if ($activeConflicts->contains('phone', $data['phone'])) {
                $errors['phone'] = 'Số điện thoại đã được sử dụng.';
            }

            if ($activeConflicts->contains('email', $data['email'])) {
                $errors['email'] = 'Email đã được sử dụng.';
            }

            throw ValidationException::withMessages($errors);
        }

        $conflictingUsers
            ->where('status', 'pending_verify')
            ->each(fn (User $pendingUser) => $pendingUser->delete());
    }

    private function sendRegisterOtp(User $user): void
    {
        $otp = $this->otpService->generate();
        $this->otpService->create($user, $user->email, 'register', $otp);
        Mail::to($user->email)->send(new AuthOtpMail($user, $otp, 'register', OtpService::EXPIRE_MINUTES));
    }

    private function lockedUserResponse(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'Tài khoản của bạn đang bị khóa.',
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_until' => $user->locked_until,
        ], 423);
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
        $roles = $this->roleRedirectService->roles($user);

        return (bool) array_intersect($roles, self::ADMIN_ROLES);
    }
}
