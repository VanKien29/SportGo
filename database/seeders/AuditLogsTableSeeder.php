<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerApplication;
use App\Models\Report;
use App\Models\User;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AuditLogsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();

        $this->audit($admin?->id, 'partner_application.approved', PartnerApplication::class, PartnerApplication::query()->where('venue_name', 'SportGo Cầu Giấy')->value('id'), 'admin', ['status' => 'reviewing'], ['status' => 'approved']);
        $this->audit($staff?->id, 'report.resolved', Report::class, Report::query()->where('status', 'resolved')->value('id'), 'moderation', ['status' => 'reviewing'], ['status' => 'resolved', 'action_taken' => 'content_hidden']);
        $this->audit($staff?->id, 'complaint.processing', Complaint::class, Complaint::query()->where('status', 'processing')->value('id'), 'admin', ['status' => 'open'], ['status' => 'processing']);
        $this->audit($admin?->id, 'withdrawal.completed', OwnerWithdrawalRequest::class, OwnerWithdrawalRequest::query()->where('request_code', 'WRADMCOMP1')->value('id'), 'payment', ['status' => 'approved'], ['status' => 'completed']);
        $this->audit($admin?->id, 'platform_fee.payment_confirmed', VenuePlatformFeeLedger::class, VenuePlatformFeeLedger::query()->where('status', 'paid')->value('id'), 'platform_fee', ['status' => 'pending'], ['status' => 'paid']);
    }

    private function audit(?string $actorId, string $action, string $entityType, ?string $entityId, string $context, array $oldValues, array $newValues): void
    {
        if (! $entityId) {
            return;
        }

        AuditLog::query()->updateOrCreate(
            [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'actor_id' => $actorId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'context' => $context,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SportGo Seeder',
            ]
        );
    }
}
