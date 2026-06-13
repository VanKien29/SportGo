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
        $isMutual = in_array($request->termination_type, ['mutual_agreement', TerminationType::MUTUAL->value], true);
        $documentType = $isMutual ? 'mutual_liquidation_minutes' : 'unilateral_notice';
        $prefix = $isMutual ? 'LIQ-' : 'UNI-';
        $contractCode = $contract->contract_code ?: $contract->contract_number ?: $contract->id;
        $fileName = $prefix . $contractCode . '-' . time() . '.docx';
        $filePath = 'liquidations/' . $fileName;

        // In a real application, we would use PHPWord or similar to generate a dynamic document.
        // Here we simulate the generation process by copying a template or creating a placeholder file.
        $templatePath = base_path('database/seeders/templates/partner-documents/Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx');
        
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
