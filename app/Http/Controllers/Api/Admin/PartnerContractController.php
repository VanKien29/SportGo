<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Services\Partner\ContractGenerationService;
use App\Services\Partner\ContractSignatureService;
use App\Services\Partner\PartnerTerminationService;
use App\Models\PartnerTerminationRequest;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    protected $generationService;
    protected $signatureService;
    protected $terminationService;

    public function __construct(
        ContractGenerationService $generationService,
        ContractSignatureService $signatureService,
        PartnerTerminationService $terminationService
    ) {
        $this->generationService = $generationService;
        $this->signatureService = $signatureService;
        $this->terminationService = $terminationService;
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

    public function terminate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'type' => 'required|in:unilateral_by_admin,mutual',
        ]);

        $contract = PartnerContract::findOrFail($id);
        
        $termRequest = $this->terminationService->requestTermination(
            $contract->partner_application_id, 
            $request->user(), 
            $request->type, 
            $request->reason
        );

        // Auto approve if Admin is the one initiating
        $this->terminationService->processTermination($termRequest, $contract, $request->user());

        return response()->json(['message' => 'Contract terminated successfully', 'data' => $termRequest]);
    }

    public function approveTermination(Request $request, $id)
    {
        $contract = PartnerContract::findOrFail($id);
        
        $termRequest = PartnerTerminationRequest::where('partner_application_id', $contract->partner_application_id)
            ->where('status', 'submitted')
            ->firstOrFail();

        $this->terminationService->processTermination($termRequest, $contract, $request->user());

        return response()->json(['message' => 'Termination request approved successfully']);
    }
}
