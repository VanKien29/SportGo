<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminAuditService
{
    public function log(
        Request $request,
        string $module,
        string $action,
        string $entityType,
        string $entityId,
        array $oldValues = [],
        array $newValues = [],
        array $extra = []
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        /** @var User|null $actor */
        $actor = $request->user();

        $payload = [
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'admin',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];

        $optional = [
            'actor_type' => $extra['actor_type'] ?? $this->actorType($actor),
            'module' => $module,
            'metadata' => $extra['metadata'] ?? null,
            'reason' => $extra['reason'] ?? null,
            'policy_id' => $extra['policy_id'] ?? null,
            'policy_rule_id' => $extra['policy_rule_id'] ?? null,
            'policy_evaluation_log_id' => $extra['policy_evaluation_log_id'] ?? null,
            'request_id' => $request->headers->get('X-Request-Id'),
            'severity' => $extra['severity'] ?? 'info',
        ];

        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('audit_logs', $column)) {
                $payload[$column] = $value;
            }
        }

        AuditLog::query()->create($payload);
    }

    private function actorType(?User $actor): string
    {
        if (! $actor) {
            return 'system';
        }

        $roles = $actor->roles()->pluck('roles.name')->all();

        if (in_array('super_admin', $roles, true)) {
            return 'super_admin';
        }

        if (in_array('admin', $roles, true) || in_array('system_staff', $roles, true)) {
            return 'admin';
        }

        if (in_array('venue_owner', $roles, true)) {
            return 'owner';
        }

        if (in_array('venue_staff', $roles, true)) {
            return 'venue_staff';
        }

        return 'user';
    }
}
