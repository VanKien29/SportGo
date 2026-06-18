<?php

namespace App\Services\Partner;

use App\Models\PartnerContract;
use App\Models\PartnerTerminationDocument;
use App\Models\PartnerTerminationRequest;
use App\Enums\TerminationType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class TerminationDocumentService
{
    public function generateDocument(PartnerTerminationRequest $request, PartnerContract $contract, string $adminId): ?PartnerTerminationDocument
    {
        $isMutual = in_array($request->type, ['mutual_agreement', TerminationType::MUTUAL->value], true);
        $documentType = $isMutual ? 'mutual_liquidation_minutes' : 'unilateral_notice';
        $prefix = $isMutual ? 'LIQ-' : 'UNI-';
        $contractCode = $contract->contract_code ?: $contract->contract_number ?: $contract->id;
        $fileName = $prefix . $contractCode . '-' . time() . '.pdf';
        $filePath = 'liquidations/' . $fileName;

        // Choose the correct template based on termination type
        $templateFile = 'Mau_04_Bien_ban_thanh_ly_hop_dong_hai_ben_SportGo.pdf';
        if ($request->type === 'unilateral_by_owner') {
            $templateFile = 'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo.pdf';
        } elseif ($request->type === 'unilateral_by_admin') {
            $templateFile = 'Mau_05_Cong_van_cham_dut_hop_dong_don_phuong_SportGo.pdf';
        }

        $templatePath = base_path('database/seeders/templates/partner-documents/' . $templateFile);
        
        if (file_exists($templatePath)) {
            Storage::disk('public')->put($filePath, file_get_contents($templatePath));
        } else {
            Storage::disk('public')->put($filePath, 'Mock Termination Document for ' . $contractCode);
        }

        return PartnerTerminationDocument::create([
            'partner_termination_request_id' => $request->id,
            'document_type' => $documentType,
            'file_path' => $filePath,
            'status' => 'generated',
            'generated_by' => $adminId,
            'generated_at' => now(),
        ]);
    }
}
