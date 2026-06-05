<?php

namespace App\Http\Controllers\Api\Admin\Auth;

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

class AdminForgotPasswordController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly RoleRedirectService $roleRedirectService,
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
        ], [
            'identifier.required' => 'Vui lòng nhập username, email hoặc số điện thoại quản trị.',
        ]);

        $user = $this->findAdminUserByIdentifier($data['identifier']);

        if (! $user) {
            return $this->neutralOtpResponse();
        }

        if (! $user->email) {
            throw ValidationException::withMessages([
                'identifier' => 'Tài khoản quản trị chưa có email nên không thể đặt lại mật khẩu.',
            ]);
        }

        $otp = $this->otpService->generate();
        $this->otpService->create($user, $user->email, 'reset_password', $otp);
        Mail::to($user->email)->send(new AuthOtpMail($user, $otp, 'reset_password', OtpService::EXPIRE_MINUTES));

        return $this->neutralOtpResponse();
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->findAdminUserByIdentifier($data['identifier']);

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages([
                'identifier' => 'Không tìm thấy tài khoản quản trị có email để đặt lại mật khẩu.',
            ]);
        }

        $this->otpService->verify($user->email, 'reset_password', $data['otp']);

        return response()->json([
            'message' => 'OTP hợp lệ. Vui lòng đặt mật khẩu quản trị mới.',
            'reset_verified' => true,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->findAdminUserByIdentifier($data['identifier']);

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages([
                'identifier' => 'Không tìm thấy tài khoản quản trị có email để đặt lại mật khẩu.',
            ]);
        }

        $this->otpService->verify($user->email, 'reset_password', $data['otp'], true);

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Đặt lại mật khẩu quản trị thành công. Vui lòng đăng nhập lại.',
        ]);
    }

    private function neutralOtpResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Nếu tài khoản quản trị tồn tại, mã OTP sẽ được gửi về email đăng ký.',
        ]);
    }

    private function findAdminUserByIdentifier(string $identifier): ?User
    {
        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $user) {
            return null;
        }

        return $this->roleRedirectService->isAdminAreaUser($user) ? $user : null;
    }
}
