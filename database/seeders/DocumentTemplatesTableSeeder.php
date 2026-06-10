<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DocumentTemplatesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $sourceDir = base_path('database/seeders/templates/partner-documents');
        $targetDir = storage_path('app/private/document-templates');

        File::ensureDirectoryExists($targetDir);

        $templates = [
            [
                'partner_application_form',
                'Đơn đề nghị đăng ký đối tác chủ sân',
                'Mau_01_Don_de_nghi_dang_ky_doi_tac_chu_san_SportGo.docx',
                $this->partnerApplicationVariables(),
            ],
            [
                'partner_contract',
                'Hợp đồng hợp tác đối tác SportGo',
                'Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx',
                $this->partnerContractVariables(),
            ],
            [
                'termination_request',
                'Đơn yêu cầu chấm dứt hợp tác',
                'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo.docx',
                $this->terminationRequestVariables(),
            ],
            [
                'mutual_liquidation_minutes',
                'Biên bản thanh lý hợp đồng hai bên',
                'Mau_04_Bien_ban_thanh_ly_hop_dong_hai_ben_SportGo.docx',
                $this->liquidationVariables(),
            ],
            [
                'unilateral_termination_notice',
                'Công văn chấm dứt hợp đồng đơn phương',
                'Mau_05_Cong_van_cham_dut_hop_dong_don_phuong_SportGo.docx',
                $this->unilateralNoticeVariables(),
            ],
            [
                'settlement_minutes',
                'Biên bản quyết toán chấm dứt hợp tác',
                'Mau_06_Bien_ban_quyet_toan_cham_dut_hop_tac_SportGo.docx',
                $this->settlementVariables(),
            ],
        ];

        foreach ($templates as [$documentType, $name, $fileName, $variables]) {
            $this->seedTemplate($sourceDir, $targetDir, $documentType, $name, $fileName, 1, true, $variables, $admin?->id);
        }

        $this->seedTemplate(
            $sourceDir,
            $targetDir,
            'partner_contract',
            'Hợp đồng hợp tác đối tác SportGo - bản nháp v2',
            'Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx',
            2,
            false,
            $this->partnerContractVariables(),
            $admin?->id,
        );
    }

    private function seedTemplate(
        string $sourceDir,
        string $targetDir,
        string $documentType,
        string $name,
        string $fileName,
        int $version,
        bool $active,
        array $variables,
        ?string $adminId
    ): void {
        $source = $sourceDir . DIRECTORY_SEPARATOR . $fileName;
        $targetFileName = $documentType . '_v' . $version . '.docx';
        $target = $targetDir . DIRECTORY_SEPARATOR . $targetFileName;

        if (File::exists($source)) {
            File::copy($source, $target);
        }

        $template = DocumentTemplate::query()->updateOrCreate(
            ['document_type' => $documentType, 'version' => $version],
            [
                'template_code' => strtoupper($documentType) . '_V' . $version,
                'template_name' => $name,
                'file_name' => $targetFileName,
                'file_path' => 'document-templates/' . $targetFileName,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'storage_disk' => 'local',
                'template_variables' => $variables,
                'render_engine' => 'docx_placeholder',
                'status' => $active ? 'active' : 'inactive',
                'is_active' => $active,
                'uploaded_by' => $adminId,
                'activated_at' => $active ? now()->subDays(5) : null,
                'note' => $active
                    ? 'Template DOCX được seed từ bộ biểu mẫu đối tác SportGo 07/06.'
                    : 'Template v2 inactive để kiểm tra versioning, không dùng cho văn bản đã sinh từ v1.',
            ],
        );

        $template->forceFill([
            'output_format' => 'docx',
            'required_fields' => $variables,
            'created_by' => $adminId,
        ])->save();
    }

    private function partnerApplicationVariables(): array
    {
        return [
            'applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_address',
            'applicant_type', 'representative_identity_type', 'representative_identity_number',
            'business_name', 'business_code', 'tax_code', 'venue_name', 'venue_address',
            'venue_province', 'venue_district', 'venue_ward', 'venue_phone', 'venue_description',
            'court_type_name_snapshot', 'expected_court_count', 'bank_name', 'account_number',
            'account_holder_name', 'attachments',
        ];
    }

    private function partnerContractVariables(): array
    {
        return [
            'contract_code', 'contract_title', 'effective_from', 'effective_to',
            'sportgo_company_name', 'sportgo_tax_code', 'sportgo_representative_name',
            'owner_full_name', 'owner_phone', 'owner_email', 'identity_number',
            'business_name', 'tax_code', 'bank_name', 'account_number', 'venue_name',
            'venue_address', 'court_types_summary', 'platform_fee_amount',
            'payment_due_rule', 'overdue_lock_rule', 'refund_policy_summary',
            'owner_signer_full_name', 'sportgo_signer_full_name',
        ];
    }

    private function terminationRequestVariables(): array
    {
        return [
            'termination_code', 'requested_at', 'requested_by', 'owner_full_name',
            'contract_code', 'venue_name', 'termination_type', 'requested_effective_date',
            'reason', 'owner_bank_account_snapshot', 'owner_signed_at',
        ];
    }

    private function liquidationVariables(): array
    {
        return [
            'liquidation_minutes_code', 'contract_code', 'termination_request_code',
            'termination_reason', 'agreed_termination_date', 'venue_name', 'court_count_total',
            'owner_wallet_available_amount', 'unpaid_platform_fee_amount',
            'final_payable_to_owner', 'final_receivable_from_owner',
            'owner_access_revocation_date',
        ];
    }

    private function unilateralNoticeVariables(): array
    {
        return [
            'notice_code', 'issued_at', 'issuer_side', 'receiver_name', 'contract_code',
            'venue_name', 'legal_basis_text', 'termination_reason', 'effective_termination_date',
            'transition_end_at', 'required_actions', 'settlement_deadline',
            'issuer_representative_name',
        ];
    }

    private function settlementVariables(): array
    {
        return [
            'settlement_code', 'settlement_date', 'contract_code', 'termination_request_code',
            'owner_full_name', 'venue_name', 'owner_wallet_available_amount',
            'platform_fee_remaining_refund_amount', 'unpaid_platform_fee_amount',
            'penalty_amount', 'adjustment_amount', 'final_payable_to_owner',
            'final_receivable_from_owner', 'settlement_items', 'bank_name',
            'account_number', 'account_holder_name', 'withdrawal_code', 'withdrawal_status',
        ];
    }
}
