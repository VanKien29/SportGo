<?php

namespace App\Console\Commands;

use App\Services\Payments\PlatformFeePlanVersionService;
use Illuminate\Console\Command;

class ActivatePlatformFeePlans extends Command
{
    protected $signature = 'platform-fees:activate-plans';

    protected $description = 'Kích hoạt các phiên bản bảng giá đã đến ngày áp dụng.';

    public function handle(PlatformFeePlanVersionService $plans): int
    {
        $result = $plans->activateDueVersions();
        $this->info(sprintf('Đã kích hoạt %d phiên bản bảng giá.', $result['activated']));

        return self::SUCCESS;
    }
}
