<?php

namespace App\Services\Customers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class WalkInCustomerService
{
    public function resolveOrCreate(?string $customerId, ?string $name, ?string $phone): User
    {
        if ($customerId) {
            $user = User::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            $this->ensureUsableCustomer($user);
            $this->ensureWallet($user->id);

            return $user;
        }

        $normalizedPhone = $this->normalizePhone((string) $phone);
        $displayName = $this->normalizeName($name);

        if ($normalizedPhone === '') {
            throw new RuntimeException('Booking tại quầy cần số điện thoại khách hàng để tạo ví SportGo.');
        }

        $phoneCandidates = $this->phoneCandidates($normalizedPhone);

        $user = User::query()
            ->whereIn('phone', $phoneCandidates)
            ->lockForUpdate()
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'username' => $this->uniqueUsername($displayName, $normalizedPhone),
                'full_name' => $displayName,
                'phone' => $this->preferredStoredPhone($normalizedPhone),
                'phone_verified_at' => null,
                'password' => Hash::make(Str::random(32)),
                'status' => 'active',
                'verification_channel' => 'sms',
            ]);
        } elseif ($displayName !== 'Khách tại quầy' && blank($user->full_name)) {
            $user->forceFill(['full_name' => $displayName])->save();
        }

        $this->ensureUsableCustomer($user);
        $this->ensureWallet($user->id);

        return $user;
    }

    public function ensureWallet(string $userId): object
    {
        $wallet = DB::table('user_wallets')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            return $wallet;
        }

        $walletId = (string) Str::uuid();

        DB::table('user_wallets')->insert([
            'id' => $walletId,
            'user_id' => $userId,
            'balance' => 0,
            'locked_balance' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('user_wallets')
            ->where('id', $walletId)
            ->lockForUpdate()
            ->first();
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($phone));

        if (str_starts_with($phone, '84') && ! str_starts_with($phone, '+84')) {
            return '+'.$phone;
        }

        return $phone;
    }

    private function normalizeName(?string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));

        return $name !== '' ? $name : 'Khách tại quầy';
    }

    private function ensureUsableCustomer(User $user): void
    {
        if (($user->status ?? 'active') !== 'active') {
            throw new RuntimeException('Tài khoản khách hàng đang bị khóa hoặc tạm ngưng, chưa thể gắn booking/ví.');
        }
    }

    private function phoneCandidates(string $phone): array
    {
        return array_values(array_unique(array_filter([
            $phone,
            $this->toLocalPhone($phone),
            $this->toInternationalPhone($phone),
            ltrim($phone, '+'),
        ])));
    }

    private function preferredStoredPhone(string $phone): string
    {
        return $this->toLocalPhone($phone) ?? $phone;
    }

    private function toLocalPhone(string $phone): ?string
    {
        if (str_starts_with($phone, '+84')) {
            return '0'.substr($phone, 3);
        }

        if (str_starts_with($phone, '84')) {
            return '0'.substr($phone, 2);
        }

        return null;
    }

    private function toInternationalPhone(string $phone): ?string
    {
        if (str_starts_with($phone, '0')) {
            return '+84'.substr($phone, 1);
        }

        return null;
    }

    private function uniqueUsername(string $name, string $phone): string
    {
        $nameSlug = Str::slug($name, '_') ?: 'khach_tai_quay';
        $phoneSuffix = substr(preg_replace('/\D+/', '', $phone), -4) ?: Str::lower(Str::random(4));
        $base = Str::limit($nameSlug, 32, '').'_'.$phoneSuffix;

        if (! User::query()->where('username', $base)->exists()) {
            return $base;
        }

        do {
            $username = $base.'_'.Str::lower(Str::random(4));
        } while (User::query()->where('username', $username)->exists());

        return $username;
    }
}
