<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerTerminationRequestsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_termination_requests') || ! Schema::hasTable('partner_contracts')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();
        $application = PartnerApplication::query()->where('venue_name', 'SportGo Cầu Giấy')->first();
        $legacyContract = PartnerContract::query()->where('contract_code', 'HD-SG-CG-OLD')->first();
        $blockedApplication = PartnerApplication::query()->where('venue_name', 'SportGo Ba Đình')->first();
        $blockedContract = PartnerContract::query()->where('contract_code', 'HD-SG-BD-001')->first();

        if (! $owner || ! $admin || ! $application || ! $legacyContract || ! $blockedApplication || ! $blockedContract) {
            return;
        }

        $this->seedRequest(
            'TERM-MUTUAL-CG-001',
            $legacyContract,
            $application,
            $owner,
            $owner,
            $admin,
            'mutual_agreement',
            'settlement_completed',
            'Hai bên thống nhất chấm dứt hợp tác để ký lại hợp đồng mới.',
            now()->subDays(20),
            now()->subDays(18),
            now()->subDays(10),
            now()->subDay(),
            now()->subDay(),
        );

        $this->seedRequest(
            'TERM-MUTUAL-CG-SETTLE',
            $legacyContract,
            $application,
            $owner,
            $owner,
            $admin,
            'mutual_agreement',
            'settlement_processing',
            'Hai bên đã duyệt chấm dứt, đang đối soát quyết toán.',
            now()->subDays(9),
            now()->subDays(8),
            now()->addDays(20),
            now()->addDays(22),
            null,
        );

        $this->seedRequest(
            'TERM-OWNER-CG-001',
            $legacyContract,
            $application,
            $owner,
            $owner,
            $admin,
            'unilateral_by_owner',
            'approved',
            'Chủ sân gửi đơn chấm dứt đơn phương vì ngừng kinh doanh địa điểm.',
            now()->subDays(12),
            now()->subDays(11),
            now()->addDays(19),
            now()->addDays(19),
            null,
        );

        $this->seedRequest(
            'TERM-SPORTGO-CG-001',
            $legacyContract,
            $application,
            $owner,
            $admin,
            $admin,
            'unilateral_by_sportgo',
            'transition_period',
            'SportGo gửi công văn chấm dứt do vi phạm nghĩa vụ phí duy trì.',
            now()->subDays(7),
            now()->subDays(6),
            now()->addDays(24),
            now()->addDays(24),
            null,
        );

        $this->seedRequest(
            'TERM-SPORTGO-CG-DONE',
            $blockedContract,
            $blockedApplication,
            $owner,
            $admin,
            $admin,
            'unilateral_by_sportgo',
            'completed',
            'SportGo chấm dứt đơn phương, đã hết thời gian chuyển tiếp và hoàn tất quyết toán.',
            now()->subDays(40),
            now()->subDays(39),
            now()->subDays(9),
            now()->subDays(9),
            now()->subDays(8),
        );
    }

    private function seedRequest(
        string $code,
        PartnerContract $contract,
        PartnerApplication $application,
        User $owner,
        User $requestedBy,
        User $approvedBy,
        string $terminationType,
        string $status,
        string $reason,
        mixed $requestedAt,
        mixed $approvedAt,
        mixed $effectiveDate,
        mixed $transitionEndAt,
        mixed $ownerAccessRevokedAt
    ): void {
        PartnerTerminationRequest::query()->updateOrCreate(
            ['termination_code' => $code],
            [
                'partner_contract_id' => $contract->id,
                'partner_application_id' => $application->id,
                'owner_id' => $owner->id,
                'venue_cluster_id' => $contract->venue_cluster_id,
                'termination_type' => $terminationType,
                'requested_by' => $requestedBy->id,
                'requested_at' => $requestedAt,
                'reason' => $reason,
                'requested_effective_date' => $effectiveDate->toDateString(),
                'status' => $status,
                'approved_by' => in_array($status, ['approved', 'pending_signature', 'settlement_processing', 'settlement_completed', 'transition_period', 'completed'], true)
                    ? $approvedBy->id
                    : null,
                'approved_at' => in_array($status, ['approved', 'pending_signature', 'settlement_processing', 'settlement_completed', 'transition_period', 'completed'], true)
                    ? $approvedAt
                    : null,
                'reject_reason' => null,
                'effective_termination_date' => $effectiveDate,
                'transition_end_at' => $transitionEndAt,
                'owner_access_revoked_at' => $ownerAccessRevokedAt,
            ],
        );
    }
}
