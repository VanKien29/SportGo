<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Enums\PartnerProfileStatus;
use App\Enums\TerminationType;
use App\Models\PartnerContract;
use App\Models\PartnerHistory;
use App\Models\PartnerLiquidation;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PartnerTerminationService
{
    public function requestTermination(string $profileId, User $requester, string $type, string $reason): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($profileId, $requester, $type, $reason) {
            $request = PartnerTerminationRequest::create([
                'partner_application_id' => $profileId,
                'requested_by' => $requester->id,
                'type' => $type,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            PartnerHistory::create([
                'partner_application_id' => $profileId,
                'action' => 'termination_requested',
                'actor_id' => $requester->id,
                'new_values' => ['type' => $type, 'reason' => $reason],
            ]);

            return $request;
        });
    }

    public function processTermination(PartnerTerminationRequest $request, PartnerContract $contract, User $admin): void
    {
        DB::transaction(function () use ($request, $contract, $admin) {
            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            $isMutual = $request->type === TerminationType::MUTUAL->value;
            $newStatus = $isMutual ? ContractStatus::LIQUIDATED->value : ContractStatus::TERMINATED->value;

            $contract->update(['status' => $newStatus]);
            $contract->application->update([
                'terminated_at' => now(),
            ]);

            app(TerminationDocumentService::class)->generateDocument($request, $contract, $admin->id);

            PartnerHistory::create([
                'partner_application_id' => $contract->partner_application_id,
                'action' => 'termination_processed',
                'actor_id' => $admin->id,
                'new_values' => ['termination_type' => $request->type],
            ]);

            // Call Wallet Refund Service
            app(WalletRefundService::class)->initiateRefund(
                $contract->application->user_id,
                $contract->application->approved_venue_cluster_id,
                $admin
            );

            // Send Termination Email
            $user = $contract->application->user;
            if ($user) {
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PartnerTerminatedMail($user->full_name));
                } catch (\Exception $e) {
                    // Ignore email error
                }
            }
        });
    }
}
