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

        $accounts = [
            [$owner, 'SportGo Cầu Giấy', 'Vietcombank', 'VCB', '0123456789', 'CHU SAN SPORTGO', 'active', true, null],
            [$user, 'SportGo Thanh Xuân', 'TPBank', 'TPB', '0987654321', 'NGUYEN VAN USER', 'pending', true, null],
            [$user, 'SportGo Long Biên', 'Techcombank', 'TCB', '1122334455', 'NGUYEN VAN USER', 'rejected', false, 'Tên chủ tài khoản không khớp với hồ sơ đăng ký.'],
        ];

        foreach ($accounts as [$accountOwner, $venueName, $bankName, $bankCode, $accountNumber, $holder, $status, $isDefault, $rejectedReason]) {
            $application = PartnerApplication::query()->where('venue_name', $venueName)->first();

            OwnerBankAccount::query()->updateOrCreate(
                [
                    'owner_id' => $accountOwner->id,
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                ],
                [
                    'partner_application_id' => $application?->id,
                    'bank_name' => $bankName,
                    'account_holder_name' => $holder,
                    'branch_name' => 'Hà Nội',
                    'status' => $status,
                    'is_default' => $isDefault,
                    'verified_by' => in_array($status, ['active', 'rejected'], true) ? $admin?->id : null,
                    'verified_at' => in_array($status, ['active', 'rejected'], true) ? now()->subDays(10) : null,
                    'rejected_reason' => $rejectedReason,
                ],
            );
        }
    }
}
