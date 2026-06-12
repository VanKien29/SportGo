<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Services\Partner\ContractSignatureService;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    protected $signatureService;

    public function __construct(ContractSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
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
}
