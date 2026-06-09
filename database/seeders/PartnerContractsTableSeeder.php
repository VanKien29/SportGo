<?php

namespace Database\Seeders;

use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerContractsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_contracts')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();

        if (! $owner || ! $admin) {
            return;
        }

        $this->seedContract('HD-SG-CG-001', 'SportGo Cầu Giấy', 'signed_active', 'DOC-HD-SG-CG-001', $owner, $admin, now()->subDays(9), now()->subDays(8), now()->subDays(8), null);
        $this->seedContract('HD-SG-DD-001', 'SportGo Đống Đa', 'pending_owner_signature', 'DOC-HD-SG-DD-001', $owner, $admin, null, null, null, null);
        $this->seedContract('HD-SG-BD-001', 'SportGo Ba Đình', 'pending_sportgo_signature', 'DOC-HD-SG-BD-001', $owner, $admin, now()->subDays(2), null, null, null);
        $this->seedContract('HD-SG-CG-OLD', 'SportGo Cầu Giấy', 'terminated', 'DOC-HD-SG-CG-OLD', $owner, $admin, now()->subMonths(8)->addDay(), now()->subMonths(8)->addDays(2), now()->subMonths(8), now()->subDay());
    }

    private function seedContract(
        string $contractCode,
        string $venueName,
        string $status,
        string $documentCode,
        User $owner,
        User $admin,
        mixed $ownerSignedAt,
        mixed $sportgoSignedAt,
        mixed $effectiveFrom,
        mixed $terminatedAt
    ): void {
        $application = PartnerApplication::query()->where('venue_name', $venueName)->first();
        $document = GeneratedDocument::query()->where('document_code', $documentCode)->first();

        if (! $application || ! $document) {
            return;
        }

        PartnerContract::query()->updateOrCreate(
            ['contract_code' => $contractCode],
            [
                'partner_application_id' => $application->id,
                'owner_id' => $owner->id,
                'venue_cluster_id' => $application->approved_venue_cluster_id,
                'contract_title' => 'Hợp đồng hợp tác đối tác ' . $venueName,
                'status' => $status,
                'generated_document_id' => $document->id,
                'generated_by' => $admin->id,
                'approved_by' => $admin->id,
                'owner_signed_at' => $ownerSignedAt,
                'sportgo_signed_at' => $sportgoSignedAt,
                'effective_from' => $effectiveFrom,
                'effective_to' => $terminatedAt ?: ($effectiveFrom ? now()->addYear()->endOfDay() : null),
                'terminated_at' => $terminatedAt,
                'note' => match ($status) {
                    'signed_active' => 'Hợp đồng đã đủ chữ ký hai bên và đang hiệu lực.',
                    'pending_owner_signature' => 'Hợp đồng đã sinh, đang chờ chủ sân ký.',
                    'pending_sportgo_signature' => 'Chủ sân đã ký, đang chờ SportGo ký xác nhận.',
                    'terminated' => 'Hợp đồng cũ đã chấm dứt để test luồng quyết toán.',
                    default => null,
                },
            ],
        );
    }
}
