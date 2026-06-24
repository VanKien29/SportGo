<?php

namespace App\Services\Moderation;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\ModerationThreshold;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\ViolationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PenaltyEscalationService
{
    public function getCurrentViolationCount(string $targetType, string $targetId): int
    {
        return (int) (ViolationRecord::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->value('violation_count') ?? 0);
    }

    public function getSuggestedPenalty(string $targetType): ?ModerationThreshold
    {
        $policy = $this->activeModerationPolicy();

        return ModerationThreshold::query()
            ->when($policy, fn ($builder) => $builder->where('system_policy_id', $policy->id))
            ->where('target_type', $targetType)
            ->first();
    }

    public function applyPenalty(string $targetType, string $targetId, string $actionType, ?int $durationDays = null, ?User $actor = null, ?string $reason = null): ViolationRecord
    {
        if (in_array($actionType, ['lock_temp', 'limit_venue', 'block_venue'], true) && ! $durationDays) {
            throw ValidationException::withMessages([
                'duration_days' => 'Hình phạt tạm thời phải có số ngày áp dụng.',
            ]);
        }

        $target = $this->resolveTarget($targetType, $targetId);
        $oldValues = $target?->toArray() ?? [];
        $expiresAt = $durationDays ? now()->addDays($durationDays) : null;

        if ($target) {
            $this->applyActionToTarget($target, $targetType, $actionType, $expiresAt, $actor, $reason);
        }

        $record = ViolationRecord::query()->firstOrCreate(
            ['target_type' => $targetType, 'target_id' => $targetId],
            ['violation_count' => 0]
        );

        $record->forceFill([
            'violation_count' => $record->violation_count + 1,
            'last_violation_at' => now(),
            'last_action_type' => $actionType,
            'last_action_expires_at' => $expiresAt,
        ])->save();

        $this->notifyTarget($target, $actionType, $durationDays);
        $this->auditPenalty($targetType, $targetId, $oldValues, $target?->fresh()?->toArray() ?? [], $actionType, $durationDays, $actor, $reason);

        return $record;
    }

    private function activeModerationPolicy(): ?SystemPolicy
    {
        return SystemPolicy::query()
            ->whereIn('key', ['content_moderation', 'moderation'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }

    private function resolveTarget(string $targetType, string $targetId): ?Model
    {
        return match ($targetType) {
            'user' => User::query()->find($targetId),
            'venue_cluster' => VenueCluster::query()->find($targetId),
            default => null,
        };
    }

    private function applyActionToTarget(Model $target, string $targetType, string $actionType, $expiresAt, ?User $actor, ?string $reason): void
    {
        if ($target instanceof User && in_array($actionType, ['lock_temp', 'lock_permanent'], true)) {
            $target->forceFill([
                'status' => 'locked',
                'lock_type' => $actionType === 'lock_temp' ? 'temporary' : 'permanent',
                'status_reason' => $reason ?: 'Khóa tài khoản theo chính sách vi phạm.',
                'locked_at' => now(),
                'locked_until' => $expiresAt,
                'locked_by' => $actor?->id,
            ])->save();
            $target->tokens()->delete();
            return;
        }

        if ($target instanceof VenueCluster && in_array($actionType, ['limit_venue', 'block_venue'], true)) {
            $target->forceFill([
                'status' => 'locked',
                'status_reason' => $reason ?: 'Giới hạn cụm sân theo chính sách vi phạm.',
                'locked_at' => now(),
                'locked_until' => $expiresAt,
                'locked_by' => $actor?->id,
            ])->save();
        }
    }

    private function notifyTarget(?Model $target, string $actionType, ?int $durationDays): void
    {
        if (! $target || ! Schema::hasTable('notifications')) {
            return;
        }

        $userId = $target instanceof User ? $target->id : ($target->owner_id ?? null);
        if (! $userId) {
            return;
        }

        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'penalty_applied',
            'title' => 'Tài khoản/cụm sân bị áp dụng xử lý vi phạm',
            'body' => $durationDays ? "Hành động {$actionType} được áp dụng trong {$durationDays} ngày." : "Hành động {$actionType} đã được áp dụng.",
            'reference_type' => $target->getTable(),
            'reference_id' => (string) $target->getKey(),
            'data' => [
                'action_type' => $actionType,
                'duration_days' => $durationDays,
            ],
        ]);
    }

    private function auditPenalty(string $targetType, string $targetId, array $oldValues, array $newValues, string $actionType, ?int $durationDays, ?User $actor, ?string $reason): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
            'module' => 'moderation',
            'action' => 'penalty.applied',
            'entity_type' => $targetType,
            'entity_id' => $targetId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'moderation',
            'metadata' => [
                'action_type' => $actionType,
                'duration_days' => $durationDays,
            ],
            'reason' => $reason,
            'severity' => 'warning',
        ]);
    }
}
