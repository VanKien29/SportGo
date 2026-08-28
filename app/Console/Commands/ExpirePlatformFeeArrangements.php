<?php

namespace App\Console\Commands;

use App\Services\Payments\PlatformFeeArrangementService;
use Illuminate\Console\Command;

class ExpirePlatformFeeArrangements extends Command
{
    protected $signature = 'platform-fees:expire-arrangements';

    protected $description = 'Đánh dấu hết hạn các đề nghị trả chậm chưa được chủ sân phản hồi.';

    public function handle(PlatformFeeArrangementService $arrangements): int
    {
        $expired = $arrangements->expirePending();
        $this->info(sprintf('Đã đánh dấu hết hạn %d đề nghị trả chậm.', $expired));

        return self::SUCCESS;
    }
}
