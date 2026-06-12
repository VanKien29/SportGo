<?php

namespace App\Services\Policies\Evaluators;

use App\Models\SystemPolicy;
use App\Models\PolicyEvaluationLog;
use App\Models\AuditLog;
use App\Models\VenueCluster;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PartnerContractPolicyEvaluator
{
    public function evaluate(): void
    {
        $policy = SystemPolicy::with('rules')
            ->where('status', 'active')
            ->where(function($q) {
                $q->where('policy_type', 'partner_contract')->orWhere('type', 'partner_contract');
            })
            ->first();

        if (!$policy) {
            return;
        }

        $configService = app(\App\Services\Policies\PolicyConfigurationService::class);
        $config = $configService->extractConfigurationData($policy);
        
        $ruleId = $policy->rules->firstWhere('rule_type', 'partner_contract_rule')?->id;

        if (empty($config) || !isset($config['warn_before_days'])) {
            return;
        }

        $contracts = DB::table('partner_contracts')
            ->whereIn('status', ['active', 'expired'])
            ->get();

        foreach ($contracts as $contract) {
            $this->processContract($contract, $config, $policy, $ruleId);
        }
    }

    private function processContract($contract, array $config, SystemPolicy $policy, ?int $ruleId): void
    {
        if (!$contract->effective_to) {
            return;
        }

        $now = Carbon::now();
        $endDate = Carbon::parse($contract->effective_to);
        $daysDiff = (int) $now->diffInDays($endDate, false); // positive if future, negative if past

        if ($daysDiff > 0 && $daysDiff <= $config['warn_before_days']) {
            $this->triggerAction($contract, $policy, $ruleId, 'warn_contract_expiration', [
                'days_remaining' => $daysDiff
            ], $config);
        }

        if ($daysDiff < 0) {
            $overdueDays = abs($daysDiff);
            
            if ($overdueDays >= $config['lock_after_days']) {
                $actionKey = $config['requires_admin_confirm'] ? 'admin_review_contract_lock' : 'lock_venue_cluster_contract';
                $this->triggerAction($contract, $policy, $ruleId, $actionKey, [
                    'overdue_days' => $overdueDays
                ], $config);
            }

            if ($overdueDays >= $config['revoke_after_days']) {
                $actionKey = $config['requires_admin_confirm'] ? 'admin_review_contract_revoke' : 'revoke_owner_contract';
                $this->triggerAction($contract, $policy, $ruleId, $actionKey, [
                    'overdue_days' => $overdueDays
                ], $config);
            }
        }
    }

    private function triggerAction($contract, SystemPolicy $policy, ?int $ruleId, string $actionKey, array $data, array $config): void
    {
        // 1. Check for duplicates
        $exists = PolicyEvaluationLog::where('system_policy_id', $policy->id)
            ->where('entity_type', 'partner_contracts')
            ->where('entity_id', $contract->id)
            ->where('action_code', $actionKey)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function() use ($contract, $policy, $ruleId, $actionKey, $data, $config) {
            $resultData = [];
            
            $venueCluster = VenueCluster::find($contract->venue_cluster_id);
            if (!$venueCluster) {
                return;
            }

            if ($actionKey === 'lock_venue_cluster_contract') {
                if ($venueCluster->status !== 'locked') {
                    $venueCluster->update([
                        'status' => 'locked',
                        'status_reason' => 'Khóa cụm sân do hợp đồng đối tác hết hạn ' . $data['overdue_days'] . ' ngày.',
                    ]);
                    $resultData['locked'] = true;
                    DB::table('partner_contracts')->where('id', $contract->id)->update(['status' => 'expired']);
                }
            } elseif ($actionKey === 'revoke_owner_contract') {
                $ownerRoleId = DB::table('roles')->where('name', 'venue_owner')->value('id');
                if ($ownerRoleId && $contract->owner_id) {
                    $deleted = UserRole::where('user_id', $contract->owner_id)
                        ->where('role_id', $ownerRoleId)
                        ->where('scope_type', 'venue')
                        ->where('scope_id', $venueCluster->id)
                        ->delete();
                    
                    $resultData['revoked'] = $deleted > 0;
                    $resultData['owner_id'] = $contract->owner_id;
                    DB::table('partner_contracts')->where('id', $contract->id)->update(['status' => 'terminated', 'terminated_at' => now()]);
                }
            } elseif (str_starts_with($actionKey, 'admin_review')) {
                $resultData['review_required'] = true;
            }

            if ($config['notify_target'] || $config['notify_admin']) {
                $resultData['notifications_dispatched'] = true;
            }

            // 3. Log to policy_evaluation_logs
            $log = PolicyEvaluationLog::create([
                'system_policy_id' => $policy->id,
                'policy_rule_id' => $ruleId,
                'action_code' => $actionKey,
                'entity_type' => 'partner_contracts',
                'entity_id' => $contract->id,
                'input_data' => $data,
                'result_data' => $resultData,
            ]);

            // 4. Log to audit_logs
            AuditLog::create([
                'actor_type' => 'system',
                'action' => 'policy.partner_contract.auto_action',
                'module' => 'contract',
                'entity_type' => 'partner_contracts',
                'entity_id' => $contract->id,
                'new_values' => ['action' => $actionKey, 'data' => $data, 'result' => $resultData],
                'policy_evaluation_log_id' => $log->id,
            ]);
        });
    }
}
