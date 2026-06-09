<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\PartnerSettlement;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GeneratedDocumentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('generated_documents') || ! Schema::hasTable('document_templates')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        if (! $admin) {
            return;
        }

        $this->seedPartnerApplicationFormDocument($admin);
        $this->seedPartnerContractDocument('DOC-HD-SG-CG-001', 'HD-SG-CG-001', 'SportGo Cầu Giấy', 'completed', $admin, now()->subDays(10), true);
        $this->seedPartnerContractDocument('DOC-HD-SG-DD-001', 'HD-SG-DD-001', 'SportGo Đống Đa', 'pending_owner_signature', $admin, now()->subDays(4), false);
        $this->seedPartnerContractDocument('DOC-HD-SG-BD-001', 'HD-SG-BD-001', 'SportGo Ba Đình', 'pending_sportgo_signature', $admin, now()->subDays(3), false);
        $this->seedPartnerContractDocument('DOC-HD-SG-CG-OLD', 'HD-SG-CG-OLD', 'SportGo Cầu Giấy', 'completed', $admin, now()->subMonths(8), true);

        $this->seedTerminationRequestDocument('DOC-DYCCD-CG-001', 'TERM-OWNER-CG-001', 'signed', $admin, now()->subDays(12), true);
        $this->seedTerminationDocument('DOC-BBTL-CG-001', 'mutual_liquidation_minutes', 'mutual_liquidation_minutes', 'TERM-MUTUAL-CG-001', 'completed', $admin, now()->subDays(6), true);
        $this->seedTerminationDocument('DOC-BBTL-CG-SETTLE', 'mutual_liquidation_minutes', 'mutual_liquidation_minutes', 'TERM-MUTUAL-CG-SETTLE', 'pending_owner_signature', $admin, now()->subDays(5), false);
        $this->seedTerminationDocument('DOC-CVCD-CG-001', 'unilateral_termination_notice', 'unilateral_notice', 'TERM-SPORTGO-CG-001', 'signed', $admin, now()->subDays(6), true);
        $this->seedTerminationDocument('DOC-CVCD-CG-DONE', 'unilateral_termination_notice', 'unilateral_notice', 'TERM-SPORTGO-CG-DONE', 'completed', $admin, now()->subDays(35), true);

        $this->seedSettlementDocument('DOC-BBQT-CG-001', 'SETTLE-CG-001', $admin);
        $this->seedSettlementDocument('DOC-BBQT-CG-DEBT', 'SETTLE-CG-DEBT', $admin);
    }

    private function seedPartnerApplicationFormDocument(User $admin): void
    {
        $template = $this->template('partner_application_form');
        $application = PartnerApplication::query()->where('venue_name', 'SportGo Cầu Giấy')->first();

        if (! $template || ! $application) {
            return;
        }

        $this->saveDocument(
            'DOC-DKDT-CG-001',
            'partner_application_form',
            $template,
            PartnerApplication::class,
            $application->id,
            'Đơn đăng ký đối tác SportGo Cầu Giấy',
            'completed',
            [
                'applicant_full_name' => $application->applicant_full_name,
                'business_name' => $application->business_name,
                'venue_name' => $application->venue_name,
                'venue_address' => $application->venue_address,
                'bank_name' => $application->bank_name,
                'account_number' => $application->account_number,
                'template_version' => 1,
            ],
            'generated-documents/DOC-DKDT-CG-001.docx',
            'generated-documents/DOC-DKDT-CG-001-final.pdf',
            $admin,
            now()->subDays(14),
            true,
            [
                'partner_application_id' => $application->id,
                'owner_id' => $application->user_id,
                'venue_cluster_id' => $application->approved_venue_cluster_id,
            ],
        );
    }

    private function seedPartnerContractDocument(
        string $documentCode,
        string $contractCode,
        string $venueName,
        string $status,
        User $admin,
        mixed $generatedAt,
        bool $completed
    ): void {
        $template = $this->template('partner_contract');
        $application = PartnerApplication::query()->where('venue_name', $venueName)->first();
        $contract = PartnerContract::query()->where('contract_code', $contractCode)->first();

        if (! $template || ! $application) {
            return;
        }

        $this->saveDocument(
            $documentCode,
            'partner_contract',
            $template,
            PartnerApplication::class,
            $application->id,
            'Hợp đồng hợp tác đối tác ' . $venueName,
            $status,
            [
                'contract_code' => $contractCode,
                'owner_full_name' => $application->user?->full_name,
                'business_name' => $application->business_name,
                'venue_name' => $application->venue_name,
                'venue_address' => $application->venue_address,
                'template_version' => 1,
            ],
            'generated-documents/' . $contractCode . '.docx',
            $completed ? 'generated-documents/' . $contractCode . '-signed.pdf' : null,
            $admin,
            $generatedAt,
            $completed,
            [
                'partner_application_id' => $application->id,
                'partner_contract_id' => $contract?->id,
                'owner_id' => $application->user_id,
                'venue_cluster_id' => $application->approved_venue_cluster_id,
            ],
        );
    }

    private function seedTerminationRequestDocument(
        string $documentCode,
        string $terminationCode,
        string $status,
        User $admin,
        mixed $generatedAt,
        bool $completed
    ): void {
        $template = $this->template('termination_request');
        $request = PartnerTerminationRequest::query()->where('termination_code', $terminationCode)->first();

        if (! $template || ! $request) {
            return;
        }

        $this->saveDocument(
            $documentCode,
            'termination_request',
            $template,
            PartnerTerminationRequest::class,
            $request->id,
            'Đơn yêu cầu chấm dứt hợp tác ' . $request->termination_code,
            $status,
            [
                'termination_code' => $request->termination_code,
                'contract_code' => $request->contract?->contract_code,
                'venue_name' => $request->application?->venue_name,
                'termination_type' => $request->termination_type,
                'reason' => $request->reason,
                'template_version' => 1,
            ],
            'generated-documents/' . $documentCode . '.docx',
            $completed ? 'generated-documents/' . $documentCode . '-signed.pdf' : null,
            $admin,
            $generatedAt,
            $completed,
            [
                'partner_application_id' => $request->partner_application_id,
                'partner_contract_id' => $request->partner_contract_id,
                'partner_termination_request_id' => $request->id,
                'owner_id' => $request->owner_id,
                'venue_cluster_id' => $request->venue_cluster_id,
            ],
        );
    }

    private function seedTerminationDocument(
        string $documentCode,
        string $templateType,
        string $documentType,
        string $terminationCode,
        string $status,
        User $admin,
        mixed $generatedAt,
        bool $completed
    ): void {
        $template = $this->template($templateType);
        $request = PartnerTerminationRequest::query()->where('termination_code', $terminationCode)->first();

        if (! $template || ! $request) {
            return;
        }

        $this->saveDocument(
            $documentCode,
            $documentType,
            $template,
            PartnerTerminationRequest::class,
            $request->id,
            match ($documentType) {
                'mutual_liquidation_minutes' => 'Biên bản thanh lý ' . $request->termination_code,
                'unilateral_notice' => 'Công văn chấm dứt đơn phương ' . $request->termination_code,
                default => 'Văn bản chấm dứt ' . $request->termination_code,
            },
            $status,
            [
                'termination_code' => $request->termination_code,
                'contract_code' => $request->contract?->contract_code,
                'venue_name' => $request->application?->venue_name,
                'reason' => $request->reason,
                'template_version' => 1,
            ],
            'generated-documents/' . $documentCode . '.docx',
            $completed ? 'generated-documents/' . $documentCode . '-signed.pdf' : null,
            $admin,
            $generatedAt,
            $completed,
            [
                'partner_application_id' => $request->partner_application_id,
                'partner_contract_id' => $request->partner_contract_id,
                'partner_termination_request_id' => $request->id,
                'owner_id' => $request->owner_id,
                'venue_cluster_id' => $request->venue_cluster_id,
            ],
        );
    }

    private function seedSettlementDocument(string $documentCode, string $settlementCode, User $admin): void
    {
        $template = $this->template('settlement_minutes');
        $settlement = PartnerSettlement::query()->where('settlement_code', $settlementCode)->first();

        if (! $template || ! $settlement) {
            return;
        }

        $this->saveDocument(
            $documentCode,
            'settlement_minutes',
            $template,
            PartnerSettlement::class,
            $settlement->id,
            'Biên bản quyết toán ' . $settlement->settlement_code,
            'completed',
            [
                'settlement_code' => $settlement->settlement_code,
                'termination_code' => $settlement->request?->termination_code,
                'final_payable_to_owner' => $settlement->final_payable_to_owner,
                'final_receivable_from_owner' => $settlement->final_receivable_from_owner,
                'template_version' => 1,
            ],
            'generated-documents/' . $documentCode . '.docx',
            'generated-documents/' . $documentCode . '-signed.pdf',
            $admin,
            now()->subDays(3),
            true,
            [
                'partner_application_id' => $settlement->request?->partner_application_id,
                'partner_contract_id' => $settlement->partner_contract_id,
                'partner_termination_request_id' => $settlement->partner_termination_request_id,
                'partner_settlement_id' => $settlement->id,
                'owner_id' => $settlement->owner_id,
                'venue_cluster_id' => $settlement->venue_cluster_id,
            ],
        );
    }

    private function saveDocument(
        string $documentCode,
        string $documentType,
        DocumentTemplate $template,
        string $referenceType,
        string $referenceId,
        string $title,
        string $status,
        array $renderData,
        string $generatedFilePath,
        ?string $finalFilePath,
        User $admin,
        mixed $generatedAt,
        bool $completed,
        array $extra
    ): void {
        $document = GeneratedDocument::query()->updateOrCreate(
            ['document_code' => $documentCode],
            [
                'document_type' => $documentType,
                'template_id' => $template->id,
                'template_version' => $template->version,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => $status,
                'render_data' => $renderData,
                'generated_file_path' => $generatedFilePath,
                'final_file_path' => $finalFilePath,
                'generated_by' => $admin->id,
                'generated_at' => $generatedAt,
                'locked_at' => $completed ? $generatedAt->copy()->addDay() : null,
                'completed_at' => $completed ? $generatedAt->copy()->addDays(2) : null,
            ],
        );

        $document->forceFill(array_merge([
            'entity_type' => $referenceType,
            'entity_id' => $referenceId,
            'title' => $title,
            'generated_file_media_id' => null,
            'signed_file_media_id' => null,
            'final_file_media_id' => null,
            'file_hash' => hash('sha256', $documentCode . '|' . $template->id . '|' . json_encode($renderData, JSON_UNESCAPED_UNICODE)),
        ], $extra))->save();
    }

    private function template(string $documentType): ?DocumentTemplate
    {
        return DocumentTemplate::query()
            ->where('document_type', $documentType)
            ->where('version', 1)
            ->where('is_active', true)
            ->first();
    }
}
