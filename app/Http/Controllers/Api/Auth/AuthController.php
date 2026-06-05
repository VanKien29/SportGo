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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly RoleRedirectService $roleRedirectService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

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

        $otp = $this->otpService->generate();
        $this->otpService->create($user, $user->email, 'register', $otp);
        Mail::to($user->email)->send(new AuthOtpMail($user, $otp, 'register', OtpService::EXPIRE_MINUTES));

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để lấy mã xác thực.',
            'email' => $user->email,
        ], 201);
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
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Vui lòng nhập tên tài khoản.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = User::query()->where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => 'Sai tài khoản hoặc mật khẩu.']);
        }

        if ($user->status === 'pending_verify') {
            throw ValidationException::withMessages(['username' => 'Tài khoản chưa xác thực email.']);
        }

        if ($user->status === 'locked') {
            if ($user->lock_type === 'temporary' && $user->locked_until && $user->locked_until->isPast()) {
                $user->forceFill([
                    'status' => 'active',
                    'lock_type' => null,
                    'status_reason' => null,
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                ])->save();
            } else {
                return response()->json([
                    'message' => 'Tài khoản của bạn đang bị khóa.',
                    'status_reason' => $user->status_reason,
                    'lock_type' => $user->lock_type,
                    'locked_until' => $user->locked_until,
                ], 423);
            }
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

        return response()->json(array_merge([
            'message' => $user->status === 'locked'
                ? 'Tài khoản của bạn đang bị khóa.'
                : 'Lấy thông tin tài khoản thành công.',
        ], $this->roleRedirectService->payload($user)));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

}
