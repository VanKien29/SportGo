<?php

namespace App\Console\Commands;

use App\Services\Memberships\SystemVipService;
use Illuminate\Console\Command;

class IssueMonthlyVipVouchers extends Command
{
    protected $signature = 'app:issue-monthly-vip-vouchers';

    protected $description = 'Phát voucher VIP hệ thống hàng tháng cho subscription còn hiệu lực.';

    public function handle(SystemVipService $vip): int
    {
        $issued = $vip->issueMonthlyVouchers();
        $this->info("Đã phát {$issued} voucher VIP.");

        return self::SUCCESS;
    }
}
