<?php

namespace Database\Seeders;

use App\Models\OwnerBankAccount;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OwnerBankAccountsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('owner_bank_accounts') || ! Schema::hasTable('users')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $owner = User::query()->where('username', 'owner')->first();
        $user = User::query()->where('username', 'user')->first();

        if (! $owner || ! $user) {
            return;
        }

        $approvedApplication = PartnerApplication::query()->where('venue_name', 'SportGo Cầu Giấy')->first();
        $reviewingApplication = PartnerApplication::query()->where('venue_name', 'SportGo Thanh Xuân')->first();
        $rejectedApplication = PartnerApplication::query()->where('venue_name', 'Sân Demo Hồ Tây')->first();

        $accounts = [
            [
                $owner->id,
                $approvedApplication?->id,
                'Vietcombank',
                'VCB',
                '0123456789',
                'CHU SAN SPORTGO',
                'active',
                true,
                $admin?->id,
                now()->subDays(23),
                null,
            ],
            [
                $user->id,
                $reviewingApplication?->id,
                'TPBank',
                'TPB',
                '0987654321',
                'NGUYEN VAN USER',
                'pending',
                true,
                null,
                null,
                null,
            ],
            [
                $user->id,
                $rejectedApplication?->id,
                'Techcombank',
                'TCB',
                '1122334455',
                'NGUYEN VAN USER',
                'rejected',
                false,
                $admin?->id,
                now()->subDays(10),
                'Tên chủ tài khoản không khớp với hồ sơ đăng ký.',
            ],
        ];

        foreach ($accounts as [$ownerId, $applicationId, $bankName, $bankCode, $accountNumber, $holder, $status, $isDefault, $verifiedBy, $verifiedAt, $rejectedReason]) {
            OwnerBankAccount::query()->updateOrCreate(
                [
                    'owner_id' => $ownerId,
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                ],
                [
                    'partner_application_id' => $applicationId,
                    'bank_name' => $bankName,
                    'account_holder_name' => $holder,
                    'branch_name' => null,
                    'status' => $status,
                    'is_default' => $isDefault,
                    'verified_by' => $verifiedBy,
                    'verified_at' => $verifiedAt,
                    'rejected_reason' => $rejectedReason,
                ]
            );
        }
    }
}
