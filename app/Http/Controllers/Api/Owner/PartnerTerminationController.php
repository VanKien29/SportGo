<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerTerminationFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerTerminationController extends Controller
{
    public function __construct(private readonly PartnerTerminationFlowService $terminations)
    {
    }

    public function eligibility(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->terminations->eligibility($request->user(), $id),
        ]);
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'detail_reason' => ['nullable', 'string', 'max:5000'],
            'requested_effective_date' => ['nullable', 'date'],
            'future_booking_policy' => ['nullable', Rule::in([
                PartnerTerminationFlowService::POLICY_CANCEL_ALL,
                PartnerTerminationFlowService::POLICY_SERVE_UNTIL_LAST,
                PartnerTerminationFlowService::POLICY_MANUAL,
            ])],
            'warning_accepted' => ['accepted'],
            'attachments' => ['nullable', 'array'],
        ]);

        $termination = $this->terminations->previewOwnerRequest($request->user(), $id, $data, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo bản xem trước đơn yêu cầu chấm dứt.',
            'data' => $termination,
        ]);
    }

    public function sendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'termination_request_id' => ['required', 'integer', 'exists:partner_termination_requests,id'],
            'signature_image' => ['required', 'string'],
        ]);

        $termination = PartnerTerminationRequest::query()
            ->whereKey($data['termination_request_id'])
            ->where('venue_cluster_id', $id)
            ->firstOrFail();
        $signingRequest = $this->terminations->sendOwnerRequestOtp($termination, $request->user(), $data['signature_image'], $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP ký đơn đã được gửi qua email.',
            'data' => [
                'signing_request_id' => $signingRequest->id,
                'expires_at' => $signingRequest->expires_at,
                'hash_short' => substr($signingRequest->file_hash, 0, 12),
            ],
        ]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'termination_request_id' => ['required', 'integer', 'exists:partner_termination_requests,id'],
            'signing_request_id' => ['required', 'integer', 'exists:document_signing_requests,id'],
            'otp' => ['required', 'digits:6'],
        ]);

        $termination = PartnerTerminationRequest::query()
            ->whereKey($data['termination_request_id'])
            ->where('venue_cluster_id', $id)
            ->firstOrFail();

        $termination = $this->terminations->submitOwnerRequest(
            $termination,
            $request->user(),
            (int) $data['signing_request_id'],
            $data['otp'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã ký và gửi yêu cầu chấm dứt hợp đồng.',
            'data' => $termination,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $termination = PartnerTerminationRequest::query()->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->terminations->showForOwner($termination, $request->user()),
        ]);
    }

    public function futureBookings(Request $request, string $id): JsonResponse
    {
        $termination = PartnerTerminationRequest::query()->findOrFail($id);

        return response()->json([
            'status' => 'success',
            ...$this->terminations->futureBookings($termination, $request->user()),
        ]);
    }

    public function bulkAction(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
            'action' => ['required', Rule::in([
                PartnerTerminationFlowService::POLICY_CANCEL_ALL,
                PartnerTerminationFlowService::POLICY_SERVE_UNTIL_LAST,
                PartnerTerminationFlowService::POLICY_MANUAL,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->bulkBookingAction(
            $termination,
            $request->user(),
            $data['booking_ids'],
            $data['action'],
            $data['reason'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật phương án xử lý booking tương lai.',
            'data' => $termination,
        ]);
    }

    public function storeWithdrawal(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'owner_wallet_id' => ['required', 'integer', 'exists:owner_wallets,id'],
            'owner_bank_account_id' => ['required', 'integer', 'exists:owner_bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:50000'],
            'owner_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $withdrawal = $this->terminations->createWithdrawal($termination, $request->user(), $data);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi yêu cầu rút tiền trong hồ sơ chấm dứt.',
            'data' => $withdrawal,
        ], 201);
    }

    public function cancelSendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'generated_document_id' => ['required', 'integer', 'exists:generated_documents,id'],
            'signature_image' => ['required', 'string'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $signingRequest = $this->terminations->sendOwnerCancelOtp(
            $termination,
            $request->user(),
            (int) $data['generated_document_id'],
            $data['signature_image'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP hủy yêu cầu đã được gửi.',
            'data' => [
                'signing_request_id' => $signingRequest->id,
                'expires_at' => $signingRequest->expires_at,
                'hash_short' => substr($signingRequest->file_hash, 0, 12),
            ],
        ]);
    }

    public function cancelPreview(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $document = $this->terminations->previewOwnerCancellation(
            $termination,
            $request->user(),
            $data['reason']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo bản xem trước văn bản hủy yêu cầu.',
            'data' => [
                'document' => $document->load('signatures.signer'),
            ],
        ], 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signing_request_id' => ['required', 'integer', 'exists:document_signing_requests,id'],
            'otp' => ['required', 'digits:6'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->cancelOwnerRequest(
            $termination,
            $request->user(),
            (int) $data['signing_request_id'],
            $data['otp'],
            $data['reason'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã hủy yêu cầu chấm dứt hợp đồng.',
            'data' => $termination,
        ]);
    }

    public function finalDocumentSignSendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $signingRequest = $this->terminations->sendFinalDocumentOtp($termination, $request->user(), 'owner', $data['signature_image'], $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Mã OTP ký biên bản cuối đã được gửi.',
            'data' => [
                'signing_request_id' => $signingRequest->id,
                'expires_at' => $signingRequest->expires_at,
                'hash_short' => substr($signingRequest->file_hash, 0, 12),
            ],
        ]);
    }

    public function finalDocumentSign(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signing_request_id' => ['required', 'integer', 'exists:document_signing_requests,id'],
            'otp' => ['required', 'digits:6'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->signFinalDocument(
            $termination,
            $request->user(),
            'owner',
            (int) $data['signing_request_id'],
            $data['otp'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Chủ sân đã ký xác nhận biên bản chấm dứt cuối.',
            'data' => $termination,
        ]);
    }

    public function acknowledgeUnilateralNotice(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'accepted' => ['accepted'],
        ]);
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->acknowledgeUnilateralNotice($termination, $request->user(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xác nhận nhận công văn. Hãy tiếp tục xử lý booking và các nghĩa vụ tài chính còn lại.',
            'data' => $termination,
        ]);
    }

    public function requestUnilateralReconsideration(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->requestUnilateralReconsideration($termination, $request->user(), $data['reason']);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi yêu cầu xem xét lại cho SportGo. Công văn vẫn có hiệu lực cho đến khi admin thu hồi.',
            'data' => $termination,
        ]);
    }
}
