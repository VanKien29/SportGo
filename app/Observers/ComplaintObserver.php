<?php

namespace App\Observers;

use App\Models\Complaint;
use App\Models\SystemPolicy;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\Memberships\SystemVipService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ComplaintObserver
{
    public function __construct(private readonly SystemVipService $vip)
    {
    }

    public function creating(Complaint $complaint): void
    {
        $complaint->is_vip_priority = $complaint->customer_id
            ? $this->vip->hasPriorityComplaint($complaint->customer_id)
            : false;
    }

    public function created(Complaint $complaint): void
    {
        try {
            $this->evaluateAutoResolve($complaint);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function evaluateAutoResolve(Complaint $complaint): void
    {
        // 1. Find active moderation policy
        $activePolicy = SystemPolicy::where('policy_type', 'moderation')->where('is_active', true)->first();
        if (!$activePolicy) {
            $activePolicy = SystemPolicy::where('key', 'moderation')->orderByDesc('version')->first();
        }
        if (!$activePolicy) {
            return;
        }

        // 2. Find rule for this complaint type
        $rule = $activePolicy->rules()->where('rule_code', 'complaint_auto_resolve_' . $complaint->complaint_type)->first();
        if (!$rule) {
            return;
        }

        $resultJson = $rule->result_json ?? [];
        $isAutoResolveEnabled = $resultJson['is_auto_resolve_enabled'] ?? false;
        $reason = $resultJson['reason'] ?? 'Hệ thống tự động xử lý khiếu nại.';

        if ($isAutoResolveEnabled && $complaint->status === 'open') {
            $oldValues = $complaint->toArray();
            
            $complaint->forceFill([
                'status' => 'resolved',
                'resolve_note' => $reason,
                'resolved_by' => null, // resolved by system
                'resolved_at' => now(),
            ])->save();

            // Log audit
            if (Schema::hasTable('audit_logs')) {
                AuditLog::query()->create([
                    'actor_id' => null,
                    'actor_type' => 'system',
                    'module' => 'moderation',
                    'action' => 'complaint.resolved',
                    'entity_type' => 'complaints',
                    'entity_id' => $complaint->id,
                    'old_values' => $oldValues,
                    'new_values' => $complaint->fresh()->toArray(),
                    'context' => 'policy_evaluator',
                    'severity' => 'info',
                    'reason' => $reason,
                    'policy_id' => $activePolicy->id,
                    'policy_rule_id' => $rule->id,
                    'metadata' => [
                        'auto_resolved' => true,
                    ]
                ]);
            }

            // Notify customer
            $customer = $complaint->customer;
            if ($customer && Schema::hasTable('notifications')) {
                Notification::query()->create([
                    'user_id' => $customer->id,
                    'type' => 'complaint_updated',
                    'title' => 'Khiếu nại đã được cập nhật',
                    'body' => $reason,
                    'reference_type' => Complaint::class,
                    'reference_id' => $complaint->id,
                    'data' => ['status' => 'resolved', 'auto_resolved' => true],
                    'is_read' => false,
                ]);
            }

            // Notify venue owner if venue type
            if ($complaint->complaint_type === 'venue') {
                $owner = $complaint->venueCluster?->owner;
                if ($owner && Schema::hasTable('notifications')) {
                    Notification::query()->create([
                        'user_id' => $owner->id,
                        'type' => 'complaint_updated',
                        'title' => 'Cập nhật khiếu nại liên quan cụm sân',
                        'body' => $reason,
                        'reference_type' => Complaint::class,
                        'reference_id' => $complaint->id,
                        'data' => ['status' => 'resolved', 'auto_resolved' => true],
                        'is_read' => false,
                    ]);
                }
            }
        }
    }
}
