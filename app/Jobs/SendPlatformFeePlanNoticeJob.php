<?php

namespace App\Jobs;

use App\Models\PlatformFeePlanVersion;
use App\Services\Payments\PlatformFeeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPlatformFeePlanNoticeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $planVersionId,
        public readonly string $event,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(PlatformFeeNotificationService $notifications): void
    {
        $plan = PlatformFeePlanVersion::query()->find($this->planVersionId);
        if ($plan) {
            $notifications->queuePlanEvent($plan, $this->event);
        }
    }
}
