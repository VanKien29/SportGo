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

        $this->audit(
            $admin?->id,
            'partner_application.approved',
            PartnerApplication::class,
            PartnerApplication::query()->where('venue_name', 'Green Sport Ba Đình')->value('id'),
            'admin',
            ['status' => 'reviewing'],
            ['status' => 'completed', 'contract_code' => 'CONTRACT_GREEN_0001'],
        );

        $this->audit(
            $staff?->id,
            'report.pending_review',
            Report::class,
            Report::query()->where('status', 'pending')->value('id'),
            'moderation',
            [],
            ['status' => 'pending'],
        );

        $this->audit(
            $staff?->id,
            'complaint.resolved',
            Complaint::class,
            Complaint::query()->where('status', 'resolved')->value('id'),
            'admin',
            ['status' => 'open'],
            ['status' => 'resolved'],
        );

        $this->audit(
            $admin?->id,
            'withdrawal.completed',
            OwnerWithdrawalRequest::class,
            OwnerWithdrawalRequest::query()->where('request_code', 'WD_OWNER_0002')->value('id'),
            'payment',
            ['status' => 'approved'],
            ['status' => 'completed', 'transfer_reference' => 'MB-WD-OWNER-0002'],
        );

        $this->audit(
            $admin?->id,
            'platform_fee.payment_confirmed',
            VenuePlatformFeeLedger::class,
            VenuePlatformFeeLedger::query()->where('status', 'paid')->value('id'),
            'platform_fee',
            ['status' => 'pending'],
            ['status' => 'paid'],
        );
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
                'actor_type' => $actorId ? 'admin' : 'system',
                'module' => $context,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'context' => $context,
                'metadata' => ['source' => 'seed'],
                'severity' => 'info',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SportGo Seeder',
            ],
        );
    }
}
