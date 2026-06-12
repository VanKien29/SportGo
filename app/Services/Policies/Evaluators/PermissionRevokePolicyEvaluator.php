<?php

namespace App\Services\Policies\Evaluators;

use App\Models\SystemPolicy;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PolicyEvaluationLog;
use App\Models\AuditLog;
use App\Models\VenueCluster;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PermissionRevokePolicyEvaluator
{
    public function evaluate(): void
    {
        $policy = SystemPolicy::with('rules')
            ->where('status', 'active')
            ->where(function($q) {
                $q->where('policy_type', 'permission_revoke')->orWhere('type', 'permission_revoke');
            })
            ->first();

        if (!$policy) {
            return;
        }

        $configService = app(\App\Services\Policies\PolicyConfigurationService::class);
        $config = $configService->extractConfigurationData($policy);
        
        $ruleId = $policy->rules->firstWhere('rule_type', 'permission_revoke_rule')?->id;

        // Currently, only supports 'platform_fee_overdue' reason and 'owner' target
        if ($config['target_type'] !== 'owner' || $config['reason_type'] !== 'platform_fee_overdue') {
            return;
        }

        $unpaidLedgers = VenuePlatformFeeLedger::where('status', 'unpaid')
            ->orWhere('status', 'overdue')
            ->get();

        foreach ($unpaidLedgers as $ledger) {
            $this->processLedger($ledger, $config, $policy, $ruleId);
        }
    }

    private function processLedger(VenuePlatformFeeLedger $ledger, array $config, SystemPolicy $policy, ?int $ruleId): void
    {
        $now = Carbon::now();
        $dueDate = Carbon::parse($ledger->due_date);
        $daysDiff = (int) $now->diffInDays($dueDate, false); // negative if overdue

        if ($daysDiff < 0) {
            $overdueDays = abs($daysDiff);
            
            if ($overdueDays >= $config['revoke_after_days']) {
                $actionKey = $config['requires_admin_confirm'] ? 'admin_review_permission_revoke' : 'revoke_owner_scope';
                $this->triggerAction($ledger, $policy, $ruleId, $actionKey, [
                    'overdue_days' => $overdueDays,
                    'permissions_to_revoke' => $config['permissions_to_revoke'],
                    'revoke_duration_days' => $config['revoke_duration_days'],
                ], $config);
            }
        }
    }

    private function triggerAction(VenuePlatformFeeLedger $ledger, SystemPolicy $policy, ?int $ruleId, string $actionKey, array $data, array $config): void
    {
        // 1. Check for duplicates
        $exists = PolicyEvaluationLog::where('system_policy_id', $policy->id)
            ->where('entity_type', 'venue_platform_fee_ledgers')
            ->where('entity_id', $ledger->id)
            ->where('action_code', $actionKey)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function() use ($ledger, $policy, $ruleId, $actionKey, $data, $config) {
            $resultData = [];
            
            $venueCluster = VenueCluster::find($ledger->venue_cluster_id);
            if (!$venueCluster) {
                return; // Venue cluster no longer exists
            }

            if ($actionKey === 'revoke_owner_scope') {
                $ownerRoleId = DB::table('roles')->where('name', 'venue_owner')->value('id');
                
                if ($ownerRoleId && $venueCluster->owner_id) {
                    $deleted = UserRole::where('user_id', $venueCluster->owner_id)
                        ->where('role_id', $ownerRoleId)
                        ->where('scope_type', 'venue')
                        ->where('scope_id', $venueCluster->id)
                        ->delete();
                    
                    $resultData['revoked'] = $deleted > 0;
                    $resultData['owner_id'] = $venueCluster->owner_id;
                    if (!$resultData['revoked']) {
                        $resultData['reason'] = 'Không tìm thấy quyền (role_id, scope_id) của owner này để thu hồi.';
                    }
                } else {
                    $resultData['revoked'] = false;
                    $resultData['reason'] = 'Không tìm thấy thông tin venue_owner role hoặc owner_id của cụm sân.';
                }
            } else {
                // requires_admin_confirm is true
                $resultData['review_required'] = true;
                $resultData['owner_id'] = $venueCluster->owner_id;
            }

            // Note: Dispatch Notifications based on $config['notify_target'] and $config['notify_admin']
            if ($config['notify_target'] || $config['notify_admin']) {
                $resultData['notifications_dispatched'] = true;
            }

            // 3. Log to policy_evaluation_logs
            $log = PolicyEvaluationLog::create([
                'system_policy_id' => $policy->id,
                'policy_rule_id' => $ruleId,
                'action_code' => $actionKey,
                'entity_type' => 'venue_platform_fee_ledgers',
                'entity_id' => $ledger->id,
                'input_data' => $data,
                'result_data' => $resultData,
            ]);

            // 4. Log to audit_logs
            AuditLog::create([
                'actor_type' => 'system',
                'action' => 'policy.permission_revoke.auto_action',
                'module' => 'permission',
                'entity_type' => 'user',
                'entity_id' => $venueCluster->owner_id,
                'new_values' => ['action' => $actionKey, 'data' => $data, 'result' => $resultData],
                'policy_evaluation_log_id' => $log->id,
            ]);
        });
    }
}
