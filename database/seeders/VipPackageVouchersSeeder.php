<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VipPackageVouchersSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('vouchers') || ! Schema::hasTable('voucher_scopes')) {
            return;
        }

        $adminId = DB::table('users')->where('username', 'admin')->value('id');

        foreach ($this->vouchers() as $voucher) {
            $voucherId = $this->upsertVoucher([
                ...$voucher,
                'created_by' => $adminId,
            ]);

            $this->syncVipPackageScope($voucherId, $voucher['package_type']);
        }
    }

    private function vouchers(): array
    {
        return [
            [
                'code' => 'SVIP05',
                'name' => 'Tiết kiệm giảm 5%',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm 5% cho booking đủ điều kiện.',
                'package_type' => 'saving',
                'discount_type' => 'percent',
                'discount_value' => 5,
                'max_discount_amount' => 20000,
                'min_order_amount' => 100000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'SVIP10',
                'name' => 'Tiết kiệm giảm 10%',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm 10% cho booking đủ điều kiện.',
                'package_type' => 'saving',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 35000,
                'min_order_amount' => 150000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'SVIP15',
                'name' => 'Tiết kiệm giảm 15%',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm 15% cho booking đủ điều kiện.',
                'package_type' => 'saving',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount_amount' => 50000,
                'min_order_amount' => 200000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'SVIP20K',
                'name' => 'Tiết kiệm giảm 20.000đ',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm cố định 20.000đ.',
                'package_type' => 'saving',
                'discount_type' => 'fixed',
                'discount_value' => 20000,
                'max_discount_amount' => null,
                'min_order_amount' => 100000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'SVIP30K',
                'name' => 'Tiết kiệm giảm 30.000đ',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm cố định 30.000đ.',
                'package_type' => 'saving',
                'discount_type' => 'fixed',
                'discount_value' => 30000,
                'max_discount_amount' => null,
                'min_order_amount' => 150000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'SVIP50K',
                'name' => 'Tiết kiệm giảm 50.000đ',
                'description' => 'Voucher dành cho gói Tiết kiệm, giảm cố định 50.000đ.',
                'package_type' => 'saving',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount_amount' => null,
                'min_order_amount' => 250000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP10',
                'name' => 'Pro giảm 10%',
                'description' => 'Voucher dành cho gói Pro, giảm 10% cho booking đủ điều kiện.',
                'package_type' => 'pro',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 50000,
                'min_order_amount' => 100000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP15',
                'name' => 'Pro giảm 15%',
                'description' => 'Voucher dành cho gói Pro, giảm 15% cho booking đủ điều kiện.',
                'package_type' => 'pro',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount_amount' => 80000,
                'min_order_amount' => 150000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP20',
                'name' => 'Pro giảm 20%',
                'description' => 'Voucher dành cho gói Pro, giảm 20% cho booking đủ điều kiện.',
                'package_type' => 'pro',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_discount_amount' => 120000,
                'min_order_amount' => 250000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP50K',
                'name' => 'Pro giảm 50.000đ',
                'description' => 'Voucher dành cho gói Pro, giảm cố định 50.000đ.',
                'package_type' => 'pro',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount_amount' => null,
                'min_order_amount' => 150000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP80K',
                'name' => 'Pro giảm 80.000đ',
                'description' => 'Voucher dành cho gói Pro, giảm cố định 80.000đ.',
                'package_type' => 'pro',
                'discount_type' => 'fixed',
                'discount_value' => 80000,
                'max_discount_amount' => null,
                'min_order_amount' => 250000,
                'per_user_limit' => 1,
            ],
            [
                'code' => 'PVIP120K',
                'name' => 'Pro giảm 120.000đ',
                'description' => 'Voucher dành cho gói Pro, giảm cố định 120.000đ.',
                'package_type' => 'pro',
                'discount_type' => 'fixed',
                'discount_value' => 120000,
                'max_discount_amount' => null,
                'min_order_amount' => 400000,
                'per_user_limit' => 1,
            ],
        ];
    }

    private function upsertVoucher(array $voucher): string
    {
        $existingId = DB::table('vouchers')->where('code', $voucher['code'])->value('id');
        $payload = [
            'code' => $voucher['code'],
            'name' => $voucher['name'],
            'description' => $voucher['description'],
            'owner_type' => 'system',
            'owner_id' => null,
            'funded_by' => 'system',
            'stacking_rule' => 'exclusive',
            'discount_type' => $voucher['discount_type'],
            'discount_value' => $voucher['discount_value'],
            'max_discount_amount' => $voucher['max_discount_amount'],
            'min_order_amount' => $voucher['min_order_amount'],
            'total_quantity' => null,
            'used_quantity' => 0,
            'per_user_limit' => $voucher['per_user_limit'],
            'valid_from' => now(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'created_by' => $voucher['created_by'],
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('vouchers')->where('id', $existingId)->update($payload);
            return $existingId;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('vouchers')->insert($payload);

        return $payload['id'];
    }

    private function syncVipPackageScope(string $voucherId, string $packageType): void
    {
        DB::table('voucher_scopes')->where('voucher_id', $voucherId)->delete();

        DB::table('voucher_scopes')->insert([
            'id' => (string) Str::uuid(),
            'voucher_id' => $voucherId,
            'scope_type' => 'vip_package',
            'scope_id' => $packageType,
            'scope_key' => 'vip_package:' . $packageType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
