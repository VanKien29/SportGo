<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Services\Partner\ContractSignatureService;
use App\Services\Partner\PartnerTerminationService;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    protected $signatureService;
    protected $terminationService;

    public function __construct(
        ContractSignatureService $signatureService,
        PartnerTerminationService $terminationService
    ) {
        $this->signatureService = $signatureService;
        $this->terminationService = $terminationService;
    }

    public function sign(Request $request, $id)
    {
        $contract = PartnerContract::findOrFail($id);
        
        // Authorization check
        if ($contract->application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->signatureService->processOwnerSignature(
            $contract, 
            $request->user(), 
            $request->ip(), 
            $request->userAgent()
        );

        return response()->json(['message' => 'Contract signed successfully']);
    }

    public function requestTermination(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'type' => 'required|in:unilateral_by_owner,mutual',
        ]);

        $contract = PartnerContract::findOrFail($id);
        
        // Authorization check
        if ($contract->application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $termRequest = $this->terminationService->requestTermination(
            $contract->partner_application_id, 
            $request->user(), 
            $request->type, 
            $request->reason
        );

        return response()->json(['message' => 'Termination requested successfully', 'data' => $termRequest]);
    }
}
