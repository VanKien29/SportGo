<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Enums\PartnerProfileStatus;
use App\Enums\TerminationType;
use App\Models\PartnerContract;
use App\Models\PartnerHistory;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerTerminationService
{
    public function requestTermination(string $profileId, User $requester, string $type, string $reason): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($profileId, $requester, $type, $reason) {
            $contract = PartnerContract::query()
                ->where('partner_application_id', $profileId)
                ->whereIn('status', [
                    ContractStatus::PENDING_OWNER_SIGNATURE->value,
                    ContractStatus::PENDING_SPORTGO_SIGNATURE->value,
                    ContractStatus::SIGNED_ACTIVE->value,
                ])
                ->latest()
                ->firstOrFail();

            $request = PartnerTerminationRequest::create([
                'partner_application_id' => $profileId,
                'requested_by' => $requester->id,
                'type' => $type === TerminationType::MUTUAL->value ? 'mutual_agreement' : ($type === 'unilateral_by_admin' ? 'unilateral_by_admin' : 'unilateral_by_owner'),
                'reason' => $reason,
                'status' => 'submitted',
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

            $contract->update([
                'status' => ContractStatus::TERMINATED->value,
                'terminated_at' => now(),
            ]);
            $contract->application->update([
                'terminated_at' => now(),
            ]);

            app(TerminationDocumentService::class)->generateDocument($request, $contract, $admin->id);

            PartnerHistory::create([
                'partner_application_id' => $contract->partner_application_id,
                'action' => 'termination_processed',
                'actor_id' => $admin->id,
                'new_values' => ['type' => $request->type],
            ]);

            // Call Wallet Refund Service
            app(WalletRefundService::class)->initiateRefund(
                $contract->application->user_id,
                $contract->application->approved_venue_cluster_id,
                $admin
            );

            // Lock Venue Cluster if exists
            $clusterId = $contract->application->approved_venue_cluster_id;
            if ($clusterId) {
                \App\Models\VenueCluster::where('id', $clusterId)->update([
                    'status' => 'inactive',
                    'locked_at' => now(),
                    'locked_by' => $admin->id,
                    'status_reason' => 'Hợp đồng đối tác đã bị thanh lý/chấm dứt',
                ]);
            }

            // Revoke owner role if no other active applications/clusters exist
            $user = $contract->application->user;
            if ($user) {
                $hasOtherActiveApps = \App\Models\PartnerApplication::where('user_id', $user->id)
                    ->where('id', '!=', $contract->partner_application_id)
                    ->whereIn('status', ['pending', 'reviewing', 'approved', 'completed'])
                    ->exists();

                if (!$hasOtherActiveApps) {
                    $ownerRole = \App\Models\Role::where('name', 'venue_owner')->first();
                    if ($ownerRole) {
                        $user->roles()->detach($ownerRole->id);
                    }
                }

                // Send Termination Email
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PartnerTerminatedMail($user->full_name));
                } catch (\Exception $e) {
                    // Ignore email error
                }
            }
        });
    }
}
