<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Models\ContractTemplate;
use App\Models\PartnerContract;
use App\Models\PartnerHistory;
use App\Repositories\PartnerContractRepository;
use Illuminate\Support\Str;

class ContractGenerationService
{
    protected $contractRepo;

    public function __construct(PartnerContractRepository $contractRepo)
    {
        $this->contractRepo = $contractRepo;
    }

    public function generate(string $profileId, string $templateId): PartnerContract
    {
        // For simplicity, we create a mock file path. In a real scenario, we use PHPWord to generate DOCX.
        $template = ContractTemplate::findOrFail($templateId);
        
        $contractNumber = 'HD-' . strtoupper(Str::random(6)) . '-' . date('Y');
        
        $contractData = [
            'partner_application_id' => $profileId,
            'contract_template_id' => $templateId,
            'contract_number' => $contractNumber,
            'status' => ContractStatus::DRAFT->value,
            'generated_file_path' => 'contracts/' . $contractNumber . '.docx', // Using DOCX
        ];

        // Copy the real template from seeders to simulate contract generation
        $sourceFile = base_path('database/seeders/templates/partner-documents/Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx');
        if (file_exists($sourceFile)) {
            \Illuminate\Support\Facades\Storage::disk('public')->put($contractData['generated_file_path'], file_get_contents($sourceFile));
        }

        $contract = $this->contractRepo->create($contractData);

        PartnerHistory::create([
            'partner_application_id' => $profileId,
            'action' => 'contract_generated',
            'new_values' => $contractData,
        ]);

        return $contract;
    }

    public function sendEmail(PartnerContract $contract): void
    {
        $contract->update(['status' => ContractStatus::WAITING_SIGNATURE->value]);
        
        // Push to queue
        \App\Jobs\SendContractEmailJob::dispatch($contract);

        PartnerHistory::create([
            'partner_application_id' => $contract->partner_application_id,
            'action' => 'contract_email_sent',
        ]);
    }
}
