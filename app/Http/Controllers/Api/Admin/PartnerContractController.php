<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Services\Partner\ContractGenerationService;
use App\Services\Partner\ContractSignatureService;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    protected $generationService;
    protected $signatureService;

    public function __construct(
        ContractGenerationService $generationService,
        ContractSignatureService $signatureService
    ) {
        $this->generationService = $generationService;
        $this->signatureService = $signatureService;
    }

    public function store(Request $request, $applicationId)
    {
        $request->validate(['template_id' => 'required|exists:contract_templates,id']);
        $contract = $this->generationService->generate($applicationId, $request->template_id);

        return response()->json(['message' => 'Contract generated successfully', 'data' => $contract]);
    }

    public function sendEmail(Request $request, $id)
    {
        $contract = PartnerContract::findOrFail($id);
        $this->generationService->sendEmail($contract);

        return response()->json(['message' => 'Contract email sent successfully']);
    }

    public function approveSignature(Request $request, $id)
    {
        $contract = PartnerContract::with('application.user')->findOrFail($id);
        $this->signatureService->completeContract($contract, $request->user(), $request->ip(), $request->userAgent());

        return response()->json(['message' => 'Contract completed successfully']);
    }
}
