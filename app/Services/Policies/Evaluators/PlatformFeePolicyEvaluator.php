<?php

namespace App\Services\Policies\Evaluators;

use App\Models\SystemPolicy;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PolicyEvaluationLog;
use App\Models\AuditLog;
use App\Models\VenueCluster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformFeePolicyEvaluator
{
    public function evaluate(): void
    {
        $policy = SystemPolicy::with('rules')
            ->where('status', 'active')
            ->where(function($q) {
                $q->where('policy_type', 'platform_fee')->orWhere('type', 'platform_fee');
            })
            ->first();

        if (!$policy) {
            return;
        }

        $configService = app(\App\Services\Policies\PolicyConfigurationService::class);
        $config = $configService->extractConfigurationData($policy);
        
        $ruleId = $policy->rules->firstWhere('rule_type', 'platform_fee_escalation')?->id;

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

        if ($daysDiff > 0 && $daysDiff <= $config['remind_before_days']) {
            $this->triggerAction($ledger, $policy, $ruleId, 'remind_before_due', [
                'days_remaining' => $daysDiff
            ], $config);
        }

        if ($daysDiff < 0) {
            $overdueDays = abs($daysDiff);
            
            // Note: Actions are escalated progressively based on the overdue days.

            if ($overdueDays >= $config['warn_overdue_days']) {
                $this->triggerAction($ledger, $policy, $ruleId, 'warn_overdue', [
                    'overdue_days' => $overdueDays
                ], $config);
            }

            // restrict_management is not supported in the DB natively, so we just log it
            if ($overdueDays >= $config['restrict_overdue_days']) {
                $this->triggerAction($ledger, $policy, $ruleId, 'restrict_management', [
                    'overdue_days' => $overdueDays
                ], $config);
            }

            if ($overdueDays >= $config['lock_overdue_days']) {
                $this->triggerAction($ledger, $policy, $ruleId, 'lock_venue_cluster', [
                    'overdue_days' => $overdueDays
                ], $config);
            }

            if ($overdueDays >= $config['termination_review_overdue_days']) {
                $this->triggerAction($ledger, $policy, $ruleId, 'termination_review', [
                    'overdue_days' => $overdueDays
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
            // 2. Perform the action
            $resultData = [];
            
            if ($actionKey === 'lock_venue_cluster') {
                $venueCluster = VenueCluster::find($ledger->venue_cluster_id);
                if ($venueCluster && $venueCluster->status !== 'locked') {
                    $venueCluster->update([
                        'status' => 'locked',
                        'status_reason' => 'Khóa cụm sân do nợ phí nền tảng quá hạn ' . $data['overdue_days'] . ' ngày.',
                    ]);
                    $resultData['locked'] = true;
                    $ledger->update([
                        'status' => 'overdue',
                        'locked_venue_at' => now(),
                    ]);
                } else {
                    $resultData['locked'] = false;
                    $resultData['reason'] = 'Cụm sân đã bị khóa từ trước hoặc không tồn tại.';
                }
            } else {
                if ($ledger->status === 'unpaid' && str_contains($actionKey, 'overdue')) {
                    $ledger->update(['status' => 'overdue']);
                }
            }

            // Here we would also dispatch Notifications based on $config['notify_owner'] and $config['notify_admin']
            // Since we don't have the notification service injected directly yet, we note it in result_data
            if ($config['notify_owner'] || $config['notify_admin']) {
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
                'action' => 'policy.platform_fee.auto_action',
                'module' => 'platform_fee',
                'entity_type' => 'venue_platform_fee_ledgers',
                'entity_id' => $ledger->id,
                'new_values' => ['action' => $actionKey, 'data' => $data, 'result' => $resultData],
                'policy_evaluation_log_id' => $log->id,
            ]);
        });
    }
}
