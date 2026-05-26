<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AuthOtpMail;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        $user = $this->findUserByIdentifier($data['identifier']);

        if (! $user) {
            return response()->json([
                'message' => 'Nếu tài khoản tồn tại, mã OTP sẽ được gửi về email đăng ký.',
            ]);
        }

        if (! $user->email) {
            throw ValidationException::withMessages([
                'identifier' => 'Tài khoản chưa có email nên không thể reset mật khẩu bằng email.',
            ]);
        }

        $otp = $this->otpService->generate();
        $this->otpService->create($user, $user->email, 'reset_password', $otp);
        Mail::to($user->email)->send(new AuthOtpMail($user, $otp, 'reset_password', OtpService::EXPIRE_MINUTES));

        return response()->json([
            'message' => 'Nếu tài khoản tồn tại, mã OTP sẽ được gửi về email đăng ký.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->findUserByIdentifier($data['identifier']);

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages(['identifier' => 'Không tìm thấy tài khoản có email để reset mật khẩu.']);
        }

        $this->otpService->verify($user->email, 'reset_password', $data['otp']);

        return response()->json([
            'message' => 'OTP hợp lệ. Vui lòng đặt mật khẩu mới.',
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

        $user = $this->findUserByIdentifier($data['identifier']);

        if (! $user || ! $user->email) {
            throw ValidationException::withMessages(['identifier' => 'Không tìm thấy tài khoản có email để reset mật khẩu.']);
        }

        $this->otpService->verify($user->email, 'reset_password', $data['otp'], true);

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.',
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
}
