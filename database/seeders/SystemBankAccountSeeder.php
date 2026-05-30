<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('system_bank_accounts')->insertOrIgnore([
            [
                'id'                  => Str::uuid()->toString(),
                'name'                => 'Tài khoản nhận tiền SportGo',
                'bank_name'           => 'TPBank',
                'bank_code'           => 'TPBank',
                'account_number'      => '72906999999',
                'account_holder_name' => 'NGUYEN VAN KIEN',
                'status'              => 'active',
                'is_default'          => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ]);
    }
}
