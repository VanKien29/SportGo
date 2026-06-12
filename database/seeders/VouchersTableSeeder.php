<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VouchersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('vouchers') || ! Schema::hasTable('voucher_scopes')) {
            return;
        }

        $adminId = DB::table('users')->where('username', 'admin')->value('id');
        $systemVoucherId = $this->upsertVoucher([
            'code' => 'SPORTGO10',
            'name' => 'SportGo giảm 10%',
            'description' => 'Voucher hệ thống giảm 10%, tối đa 50.000đ cho đơn từ 200.000đ.',
            'owner_type' => 'system',
            'owner_id' => null,
            'funded_by' => 'system',
            'stacking_rule' => 'exclusive',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_discount_amount' => 50000,
            'min_order_amount' => 200000,
            'total_quantity' => 1000,
            'used_quantity' => 0,
            'per_user_limit' => 1,
            'valid_from' => now(),
            'valid_to' => now()->addMonths(3),
            'status' => 'active',
            'created_by' => $adminId,
        ]);
        $this->upsertScope($systemVoucherId, 'all', null);

        if (! Schema::hasTable('venue_clusters')) {
            return;
        }

        $venueCluster = DB::table('venue_clusters')->where('status', 'active')->orderBy('created_at')->first();

        if (! $venueCluster) {
            return;
        }

        $venueVoucherId = $this->upsertVoucher([
            'code' => 'VENUE20',
            'name' => 'Sân giảm 20.000đ',
            'description' => 'Voucher demo của cụm sân, giảm cố định 20.000đ.',
            'owner_type' => 'venue',
            'owner_id' => $venueCluster->id,
            'funded_by' => 'venue',
            'stacking_rule' => 'exclusive',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'max_discount_amount' => 20000,
            'min_order_amount' => 100000,
            'total_quantity' => 200,
            'used_quantity' => 0,
            'per_user_limit' => 1,
            'valid_from' => now(),
            'valid_to' => now()->addMonths(2),
            'status' => 'active',
            'created_by' => $venueCluster->owner_id ?? $adminId,
        ]);
        $this->upsertScope($venueVoucherId, 'venue_cluster', $venueCluster->id);
    }

    private function upsertVoucher(array $voucher): string
    {
        $existingId = DB::table('vouchers')->where('code', $voucher['code'])->value('id');
        $payload = [
            'code' => $voucher['code'],
            'name' => $voucher['name'],
            'description' => $voucher['description'],
            'owner_type' => $voucher['owner_type'],
            'owner_id' => $voucher['owner_id'],
            'funded_by' => $voucher['funded_by'],
            'stacking_rule' => $voucher['stacking_rule'],
            'discount_type' => $voucher['discount_type'],
            'discount_value' => $voucher['discount_value'],
            'max_discount_amount' => $voucher['max_discount_amount'],
            'min_order_amount' => $voucher['min_order_amount'],
            'total_quantity' => $voucher['total_quantity'],
            'used_quantity' => $voucher['used_quantity'],
            'per_user_limit' => $voucher['per_user_limit'],
            'valid_from' => $voucher['valid_from'],
            'valid_to' => $voucher['valid_to'],
            'status' => $voucher['status'],
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

    private function upsertScope(string $voucherId, string $scopeType, ?string $scopeId): void
    {
        $existingId = DB::table('voucher_scopes')
            ->where('voucher_id', $voucherId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->value('id');

        $payload = [
            'voucher_id' => $voucherId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('voucher_scopes', 'scope_key')) {
            $payload['scope_key'] = $scopeId ?: '__all__';
        }

        if ($existingId) {
            DB::table('voucher_scopes')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('voucher_scopes')->insert($payload);
    }
}
