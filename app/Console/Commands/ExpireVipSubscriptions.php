<?php

namespace App\Console\Commands;

use App\Services\Memberships\SystemVipService;
use Illuminate\Console\Command;

class ExpireVipSubscriptions extends Command
{
    protected $signature = 'app:expire-vip-subscriptions';

    protected $description = 'Hết hạn gói VIP hệ thống và vô hiệu hóa voucher VIP còn lại.';

    public function handle(SystemVipService $vip): int
    {
        $expired = $vip->expireSubscriptions();
        $this->info("Đã hết hạn {$expired} gói VIP.");

        return self::SUCCESS;
    }
}
