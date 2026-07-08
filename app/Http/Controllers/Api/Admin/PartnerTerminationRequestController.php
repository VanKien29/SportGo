<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PartnerTerminationRequest;
use App\Models\SystemSetting;
use App\Services\Partner\PartnerTerminationFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerTerminationRequestController extends Controller
{
    public function __construct(private readonly PartnerTerminationFlowService $terminations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:80'],
            'venue_cluster_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->terminations->adminIndex($filters));
    }

    public function show(string $id): JsonResponse
    {
        $termination = PartnerTerminationRequest::query()->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->terminations->adminShow($termination),
        ]);
    }

    public function markReadyFinalDocument(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->markReadyForFinalDocument($termination, $request->user(), $data['note'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Da xac nhan du dieu kien va sinh bien ban cham dut cuoi neu can.',
            'data' => $termination,
        ]);
    }

    public function previewFinalDocument(Request $request, string $id): JsonResponse
    {
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $document = $this->terminations->previewFinalDocument($termination, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Da sinh ban xem truoc bien ban cham dut cuoi.',
            'data' => $document->fresh(['signatures.signer', 'signingRequests']),
        ]);
    }

    public function finalDocumentSignSendOtp(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $signingRequest = $this->terminations->sendFinalDocumentOtp($termination, $request->user(), 'sportgo', $data['signature_image'], $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Ma OTP ky bien ban cuoi da duoc gui cho admin.',
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
            'sportgo',
            (int) $data['signing_request_id'],
            $data['otp'],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'SportGo da ky xac nhan bien ban cham dut cuoi.',
            'data' => $termination,
        ]);
    }

    public function manualResolveBooking(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $booking = Booking::query()->findOrFail($data['booking_id']);
        $action = $this->terminations->manualResolveBooking($termination, $booking, $request->user(), $data['note'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Da ghi nhan booking duoc xu ly thu cong.',
            'data' => $action,
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'view_grace_days' => SystemSetting::integer('partner_termination_view_grace_days', 14),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'view_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'scope' => ['nullable', Rule::in(['partner_termination'])],
        ]);

        $this->terminations->updateSettings((int) $data['view_grace_days']);

        return response()->json([
            'status' => 'success',
            'message' => 'Da cap nhat thoi gian owner con duoc xem ho so sau cham dut.',
            'data' => [
                'view_grace_days' => (int) $data['view_grace_days'],
            ],
        ]);
    }
}
