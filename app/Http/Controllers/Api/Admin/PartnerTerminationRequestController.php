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
        return response()->json([
            'status' => 'conflict',
            'message' => 'Admin ký biên bản bằng phiên đăng nhập hiện tại; hệ thống không gửi OTP email cho thao tác này.',
        ], 409);
    }

    public function finalDocumentSign(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string', 'max:3000000'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->signFinalDocument(
            $termination,
            $request->user(),
            'sportgo',
            null,
            null,
            $request,
            $data['signature_image']
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

    public function unilateralNoticeSignSendOtp(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'status' => 'conflict',
            'message' => 'Admin ký công văn bằng phiên đăng nhập hiện tại; hệ thống không gửi OTP email cho thao tác này.',
        ], 409);
    }

    public function unilateralNoticeSign(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string', 'max:3000000'],
            'confirmation' => ['required', 'accepted'],
        ]);
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->signAndIssueUnilateralNotice(
            $termination,
            $request->user(),
            null,
            null,
            $request,
            $data['signature_image']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'SportGo đã ký và gửi công văn. Cụm sân đã khóa nhận booking mới, chờ chủ sân xác nhận đã nhận.',
            'data' => $termination,
        ]);
    }

    public function withdrawUnilateralNotice(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->withdrawUnilateralNotice($termination, $request->user(), $data['reason']);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã thu hồi công văn. Văn bản và lịch sử ký vẫn được lưu để đối soát.',
            'data' => $termination,
        ]);
    }

    public function resolveUnilateralReconsideration(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $termination = PartnerTerminationRequest::query()->findOrFail($id);
        $termination = $this->terminations->resolveUnilateralReconsideration($termination, $request->user(), $data['note']);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã phản hồi yêu cầu xem xét lại và giữ nguyên công văn.',
            'data' => $termination,
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
