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
            'message' => 'Da tao ban xem truoc don yeu cau cham dut.',
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
            'message' => 'Ma OTP ky don da duoc gui qua email.',
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
            'message' => 'Da ky va gui yeu cau cham dut hop dong.',
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
            'message' => 'Da cap nhat phuong an xu ly booking tuong lai.',
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
            'message' => 'Da gui yeu cau rut tien trong ho so cham dut.',
            'data' => $withdrawal,
        ], 201);
    }

    public function cancelSendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $signingRequest = $this->terminations->sendOwnerCancelOtp($termination, $request->user(), $data['signature_image'], $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Ma OTP huy yeu cau da duoc gui.',
            'data' => [
                'signing_request_id' => $signingRequest->id,
                'expires_at' => $signingRequest->expires_at,
                'hash_short' => substr($signingRequest->file_hash, 0, 12),
            ],
        ]);
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
            'message' => 'Da huy yeu cau cham dut hop dong.',
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
            'message' => 'Ma OTP ky bien ban cuoi da duoc gui.',
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
            'message' => 'Owner da ky xac nhan bien ban cham dut cuoi.',
            'data' => $termination,
        ]);
    }
}
