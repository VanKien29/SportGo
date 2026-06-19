<?php

namespace App\Jobs;

use App\Mail\Partner\PartnerRoleRevocationReminderMail;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerMailDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRevocationReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $terminationRequestId)
    {
    }

    public function handle(PartnerMailDispatcher $mail): void
    {
        $termination = PartnerTerminationRequest::with(['contract.application.user'])->find($this->terminationRequestId);
        if (! $termination || $termination->status !== 'transition_period') {
            return;
        }

        $owner = $termination->contract?->application?->user;
        if (! $owner) {
            return;
        }

        $mail->queue($owner, new PartnerRoleRevocationReminderMail([
            'owner_name' => $owner->full_name,
            'revocation_date' => $termination->transition_end_at?->format('d/m/Y H:i:s'),
            'contract_code' => $termination->contract?->contract_code,
        ]));
    }
}
