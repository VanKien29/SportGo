<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Models\ContractSignature;
use App\Models\PartnerContract;
use App\Models\PartnerHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContractSignatureService
{
    public function processOwnerSignature(PartnerContract $contract, User $owner, string $ip, string $userAgent): void
    {
        DB::transaction(function () use ($contract, $owner, $ip, $userAgent) {
            ContractSignature::create([
                'partner_contract_id' => $contract->id,
                'user_id' => $owner->id,
                'sign_role' => 'owner',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'signed_at' => now(),
            ]);

            $contract->update(['status' => ContractStatus::SIGNED->value]);

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
            ContractSignature::create([
                'partner_contract_id' => $contract->id,
                'user_id' => $admin->id,
                'sign_role' => 'admin',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'signed_at' => now(),
            ]);

            $contract->update([
                'status' => ContractStatus::COMPLETED->value,
                'completed_at' => now(),
                'final_signed_file_path' => 'contracts/' . $contract->contract_number . '_final.pdf',
            ]);

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
                    'granted_by' => $admin->id,
                    'created_at' => now(),
                ]);
            }
        });
    }
}
