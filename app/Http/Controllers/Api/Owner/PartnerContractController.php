<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerContract;
use App\Services\Partner\PartnerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerContractController extends Controller
{
    public function __construct(private readonly PartnerApplicationService $partners)
    {
    }

    public function sign(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['nullable', 'string'],
        ]);

        $contract = $this->partners->signOwnerContract(
            PartnerContract::with(['application.user', 'generatedDocument'])->findOrFail($id),
            $request->user(),
            $request,
            $data['signature_image'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bạn đã ký hợp đồng thành công.',
            'data' => $contract,
        ]);
    }

    public function requestTermination(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'signature_image' => ['nullable', 'string'],
        ]);

        $termination = $this->partners->requestTermination(
            PartnerContract::with(['application.user'])->findOrFail($id),
            $request->user(),
            $request,
            $data['reason'],
            $data['signature_image'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi yêu cầu chấm dứt hợp tác.',
            'data' => $termination,
        ]);
    }
}
