<?php

namespace App\Services\Partner;

use App\Enums\ContractStatus;
use App\Models\ContractTemplate;
use App\Models\PartnerContract;
use App\Models\PartnerApplication;
use App\Models\PartnerHistory;
use App\Repositories\PartnerContractRepository;
use Illuminate\Support\Facades\Storage;
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
        $application = PartnerApplication::findOrFail($profileId);
        $template = ContractTemplate::findOrFail($templateId);

        $contractCode = 'HD-' . strtoupper(Str::random(6)) . '-' . date('Y');
        $filePath = 'contracts/' . $contractCode . '.pdf';

        $contractData = [
            'partner_application_id' => $profileId,
            'owner_id' => $application->user_id,
            'venue_cluster_id' => $application->approved_venue_cluster_id,
            'contract_number' => $contractCode,
            'contract_title' => 'Hợp đồng hợp tác đối tác ' . ($application->venue_name ?: $application->business_name ?: $contractCode),
            'status' => ContractStatus::GENERATED->value,
            'note' => 'Sinh từ mẫu hợp đồng: ' . $template->name,
            'generated_file_path' => $template->file_path,
        ];

        $contract = $this->contractRepo->create($contractData);

        $application->update([
            'current_contract_id' => $contract->id,
            'status' => 'approved_pending_contract',
        ]);

        PartnerHistory::create([
            'partner_application_id' => $profileId,
            'action' => 'contract_generated',
            'new_values' => $contractData,
        ]);

        return $contract;
    }

    public function sendEmail(PartnerContract $contract): void
    {
        $contract->update(['status' => ContractStatus::PENDING_OWNER_SIGNATURE->value]);
        $contract->application?->update(['status' => 'contract_pending_owner_signature']);

        // Push to queue
        \App\Jobs\SendContractEmailJob::dispatch($contract);

        PartnerHistory::create([
            'partner_application_id' => $contract->partner_application_id,
            'action' => 'contract_email_sent',
        ]);
    }
}
