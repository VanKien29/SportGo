<?php

namespace App\Console\Commands;

use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerTerminationFlowService;
use Illuminate\Console\Command;

class ProcessPartnerTerminations extends Command
{
    protected $signature = 'sportgo:process-partner-terminations';

    protected $description = 'Refresh partner termination requests and revoke owner scope after the configured grace period.';

    public function handle(PartnerTerminationFlowService $terminations): int
    {
        $count = 0;

        PartnerTerminationRequest::query()
            ->whereIn('status', [
                PartnerTerminationFlowService::STATUS_IN_PROGRESS,
                PartnerTerminationFlowService::STATUS_FUTURE_BOOKINGS,
                PartnerTerminationFlowService::STATUS_WAITING_SETTLEMENT,
                PartnerTerminationFlowService::STATUS_WAITING_FINAL_SIGNATURE,
                PartnerTerminationFlowService::STATUS_TERMINATING,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($terminations, &$count): void {
                foreach ($requests as $request) {
                    $terminations->refreshProgress($request);
                    $count++;
                }
            });

        $this->info("Processed {$count} partner termination request(s).");

        return self::SUCCESS;
    }
}
