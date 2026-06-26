<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentSigningRequest;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerDocumentSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerContractController extends Controller
{
    public function __construct(
        private readonly PartnerApplicationService $partners,
        private readonly PartnerDocumentSigningService $signing,
    )
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

    public function requestApproveSignatureOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string'],
            'confirmed' => ['accepted'],
            'confirmation_text' => ['required', 'string', 'max:1000'],
        ]);

        $contract = PartnerContract::with(['application.user', 'generatedDocument'])->findOrFail($id);

        if (! $contract->generatedDocument) {
            throw ValidationException::withMessages(['contract' => 'Không tìm thấy hợp đồng cần ký.']);
        }

        if ($contract->status !== 'pending_sportgo_signature') {
            throw ValidationException::withMessages(['contract' => 'Hợp đồng không ở trạng thái chờ SportGo ký.']);
        }

        $signingRequest = $this->signing->requestOtp(
            $contract->generatedDocument,
            $request->user(),
            'sportgo',
            'admin_sign_partner_contract',
            $data['confirmation_text'],
            $data['signature_image'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP ký hợp đồng đã được gửi.',
            'data' => [
                'signing_request_id' => $signingRequest->id,
                'expires_at' => $signingRequest->expires_at,
                'hash_short' => substr($signingRequest->file_hash, 0, 12),
            ],
        ]);
    }

    public function verifyApproveSignatureOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signing_request_id' => ['required', 'integer', 'exists:document_signing_requests,id'],
            'otp' => ['required', 'digits:6'],
        ]);

        $contract = PartnerContract::with(['application.user', 'generatedDocument.signatures'])->findOrFail($id);
        $signingRequest = DocumentSigningRequest::query()
            ->whereKey($data['signing_request_id'])
            ->where('generated_document_id', $contract->generated_document_id)
            ->where('signer_side', 'sportgo')
            ->where('action', 'admin_sign_partner_contract')
            ->firstOrFail();

        $verifiedRequest = $this->signing->verifyOtp($signingRequest, $request->user(), $data['otp']);

        $contract = $this->partners->signAdminContract(
            $contract,
            $request->user(),
            $request,
            $verifiedRequest->signature_image
        );

        $signature = $contract->generatedDocument
            ? $contract->generatedDocument->signatures()->where('signer_side', 'sportgo')->where('status', 'signed')->latest()->first()
            : null;

        if ($signature) {
            $this->signing->markSigned($verifiedRequest, $signature);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'SportGo đã ký hợp đồng.',
            'data' => $contract->fresh(['application', 'generatedDocument.signatures']),
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
