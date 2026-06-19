<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Models\VenueCluster;
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
        $contract = PartnerContract::with('application')->findOrFail($id);
        
        $ownerId = $request->user()->id;
        $clusterId = $contract->venue_cluster_id ?? $contract->application?->approved_venue_cluster_id;
        
        $ownsCluster = $clusterId
            ? VenueCluster::query()
                ->whereKey($clusterId)
                ->where('owner_id', $ownerId)
                ->exists()
            : false;

        if (! $ownsCluster) {
            // Check if it's a pending contract without a cluster yet, fallback to user_id
            $ownsApplication = $contract->application && $contract->application->user_id === $ownerId;
            if (! $ownsApplication) {
                return response()->json(['message' => 'Unauthorized - Must be the owner of the venue cluster'], 403);
            }
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

        $contract = PartnerContract::with('application')->findOrFail($id);
        
        $ownerId = $request->user()->id;
        $clusterId = $contract->venue_cluster_id ?? $contract->application?->approved_venue_cluster_id;
        
        $ownsCluster = $clusterId
            ? VenueCluster::query()
                ->whereKey($clusterId)
                ->where('owner_id', $ownerId)
                ->exists()
            : false;

        if (! $ownsCluster) {
            $ownsApplication = $contract->application && $contract->application->user_id === $ownerId;
            if (! $ownsApplication) {
                return response()->json(['message' => 'Unauthorized - Must be the owner of the venue cluster'], 403);
            }
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
