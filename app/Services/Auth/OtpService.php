<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const EXPIRE_MINUTES = 10;

    public function generate(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function create(User $user, string $identifier, string $type, string $otp, int $minutes = self::EXPIRE_MINUTES): VerificationCode
    {
        $identifier = $this->normalizeIdentifier($identifier);

        VerificationCode::query()
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('channel', 'email')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return VerificationCode::query()->create([
            'user_id' => $user->id,
            'identifier' => $identifier,
            'type' => $type,
            'channel' => 'email',
            'code' => Hash::make($otp),
            'attempt_count' => 0,
            'max_attempts' => 5,
            'is_used' => false,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function verify(
        string $identifier,
        string $type,
        string $otp,
        bool $markUsed = false,
        ?int $expectedUserId = null,
    ): VerificationCode
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $code = VerificationCode::query()
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('channel', 'email')
            ->latest('created_at')
            ->first();

        if (! $code) {
            throw ValidationException::withMessages(['otp' => 'Không tìm thấy mã OTP.']);
        }

        if ($code->is_used) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP đã được sử dụng.']);
        }

        if ($code->expires_at->isPast()) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP đã hết hạn.']);
        }

        if ($code->attempt_count >= $code->max_attempts) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP đã vượt quá số lần thử.']);
        }

        if ($expectedUserId !== null && (int) $code->user_id !== $expectedUserId) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP không thuộc yêu cầu của tài khoản này.']);
        }

        if (! Hash::check($otp, $code->code)) {
            VerificationCode::query()
                ->whereKey($code->id)
                ->where('is_used', false)
                ->whereColumn('attempt_count', '<', 'max_attempts')
                ->increment('attempt_count');

            throw ValidationException::withMessages(['otp' => 'Mã OTP không đúng.']);
        }

        if ($markUsed) {
            $consumed = VerificationCode::query()
                ->whereKey($code->id)
                ->where('is_used', false)
                ->update(['is_used' => true]);

            if ($consumed !== 1) {
                throw ValidationException::withMessages(['otp' => 'Mã OTP đã được sử dụng.']);
            }

            $code->is_used = true;
        }

        return $code;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? strtolower($identifier)
            : $identifier;
    }
}
