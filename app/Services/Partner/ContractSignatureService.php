<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Models\GeneratedDocumentSignature;
use App\Models\PartnerContract;
use App\Models\PartnerHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContractSignatureService
{
    public function processOwnerSignature(PartnerContract $contract, User $owner, string $ip, string $userAgent): void
    {
        DB::transaction(function () use ($contract, $owner, $ip, $userAgent) {
            if ($contract->generated_document_id) {
                GeneratedDocumentSignature::updateOrCreate(
                    [
                        'generated_document_id' => $contract->generated_document_id,
                        'signer_side' => 'owner',
                    ],
                    [
                        'signer_user_id' => $owner->id,
                        'signer_full_name' => $owner->full_name,
                        'signer_title' => 'Chủ sân',
                        'signer_organization' => $contract->application?->business_name,
                        'signature_method' => 'typed_confirm',
                        'signed_at' => now(),
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                        'status' => 'signed',
                    ]
                );
            }

            $contract->update([
                'status' => ContractStatus::PENDING_SPORTGO_SIGNATURE->value,
                'owner_signed_at' => now(),
            ]);
            $contract->application?->update(['status' => 'contract_pending_sportgo_signature']);

            PartnerHistory::create([
                'partner_application_id' => $contract->partner_application_id,
                'action' => 'contract_signed_by_owner',
                'actor_id' => $owner->id,
            ]);
        });
    }

    public function completeContract(PartnerContract $contract, User $admin, string $ip, string $userAgent): void
    {
        DB::transaction(function () use ($contract, $admin, $ip, $userAgent) {
            if ($contract->generated_document_id) {
                GeneratedDocumentSignature::updateOrCreate(
                    [
                        'generated_document_id' => $contract->generated_document_id,
                        'signer_side' => 'sportgo',
                    ],
                    [
                        'signer_user_id' => $admin->id,
                        'signer_full_name' => $admin->full_name,
                        'signer_title' => 'Đại diện SportGo',
                        'signer_organization' => 'SportGo',
                        'signature_method' => 'typed_confirm',
                        'signed_at' => now(),
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                        'status' => 'signed',
                    ]
                );

                $contract->generatedDocument?->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $contract->update([
                'status' => ContractStatus::SIGNED_ACTIVE->value,
                'sportgo_signed_at' => now(),
                'effective_from' => $contract->effective_from ?: now(),
            ]);
            $contract->application?->update(['status' => 'completed']);

            PartnerHistory::create([
                'partner_application_id' => $contract->partner_application_id,
                'action' => 'contract_completed',
                'actor_id' => $admin->id,
            ]);

            // Assign role
            $user = User::find($contract->application->user_id);
            if ($user) {
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PartnerRoleGrantedMail($user->full_name));
                } catch (\Exception $e) {
                    // Log or ignore email error
                }
            }

            $owner = clone $contract->application->user;
            // Assuming we use custom DB table for roles:
            $roleId = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'venue_owner')->value('id');
            if ($roleId) {
                \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $owner->id,
                    'role_id' => $roleId,
                    'scope_type' => 'system',
                    'scope_id' => '00000000-0000-0000-0000-000000000000',
                    'granted_by' => $admin->id,
                    'created_at' => now(),
                ]);
            }
        });
    }
}
