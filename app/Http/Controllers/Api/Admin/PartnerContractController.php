<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    public function __construct(private readonly PartnerApplicationService $partners)
    {
    }

    public function sendEmail(Request $request, string $id): JsonResponse
    {
        $contract = PartnerContract::with('application.user')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Hợp đồng đã sẵn sàng để chủ sân ký.',
            'data' => $contract,
        ]);
    }

    public function approveSignature(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        $contract = $this->partners->signAdminContract(
            PartnerContract::with(['application.user', 'generatedDocument'])->findOrFail($id),
            $request->user(),
            $request,
            $data['signature_image']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'SportGo đã ký hợp đồng.',
            'data' => $contract,
        ]);
    }

    public function terminate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $termination = $this->partners->initiateUnilateralTermination(
            PartnerContract::with('application.user')->findOrFail($id),
            $request->user(),
            $request,
            $data['reason']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã khởi tạo chấm dứt hợp tác đơn phương.',
            'data' => $termination,
        ]);
    }

    public function approveTermination(Request $request, string $id): JsonResponse
    {
        $contract = PartnerContract::findOrFail($id);
        $termination = PartnerTerminationRequest::query()
            ->where('partner_contract_id', $contract->id)
            ->whereIn('status', ['submitted', 'reviewing'])
            ->latest()
            ->firstOrFail();

        $termination = $this->partners->confirmTermination($termination, $request->user(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xác nhận yêu cầu chấm dứt và tạo quyết toán.',
            'data' => $termination,
        ]);
    }
}
