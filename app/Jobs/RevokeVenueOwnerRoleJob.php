<?php

namespace App\Jobs;

use App\Mail\Partner\PartnerRoleRevokedMail;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerMailDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RevokeVenueOwnerRoleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $terminationRequestId)
    {
    }

    public function handle(PartnerApplicationService $service, PartnerMailDispatcher $mail): void
    {
        $termination = PartnerTerminationRequest::with(['contract.application.user', 'settlement.withdrawalRequests'])
            ->find($this->terminationRequestId);

        if (! $termination || $termination->owner_access_revoked_at) {
            return;
        }

        $owner = $termination->contract?->application?->user;
        $service->revokeOwnerRole($termination);

        if (! $owner) {
            return;
        }

        $mail->queue($owner, new PartnerRoleRevokedMail([
            'owner_name' => $owner->full_name,
            'revoked_at' => now()->format('d/m/Y H:i:s'),
            'refund_status' => $termination->settlement?->withdrawalRequests?->last()?->status ?? 'Chưa phát sinh yêu cầu rút tiền',
        ]));
    }
}
