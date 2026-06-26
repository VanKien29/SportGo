<?php

namespace App\Console\Commands;

use App\Services\Memberships\VenueMembershipService;
use Illuminate\Console\Command;

class EvaluateCourtMembershipMaintenance extends Command
{
    protected $signature = 'app:evaluate-court-membership-maintenance';

    protected $description = 'Evaluate court membership maintenance periods and downgrade inactive members.';

    public function handle(VenueMembershipService $memberships): int
    {
        $count = $memberships->evaluateMaintenance();
        $this->info("Processed {$count} membership downgrade(s).");

        return self::SUCCESS;
    }
}
