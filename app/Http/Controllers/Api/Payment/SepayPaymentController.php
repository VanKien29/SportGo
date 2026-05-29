<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Services\Payments\SepayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SepayPaymentController extends Controller
{
    public function __construct(private readonly SepayPaymentService $sepayPaymentService)
    {
    }

    public function create(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if ($booking->customer_id !== $request->user()?->id) {
            return response()->json([
                'message' => 'Bạn không có quyền thanh toán đơn đặt sân này.',
            ], 403);
        }

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Đơn đặt sân này không ở trạng thái chờ thanh toán.',
            ], 422);
        }

        if ((float) $booking->required_payment_amount <= 0) {
            return response()->json([
                'message' => 'Đây là đơn đặt sân thanh toán trực tiếp tại sân.',
            ], 422);
        }

        $lock = SlotLock::query()
            ->where('booking_id', $booking->id)
            ->where('expires_at', '>', now())
            ->first();

        if (! $lock) {
            return response()->json([
                'message' => 'Đơn đặt sân đã hết thời gian giữ chỗ. Vui lòng đặt lại.',
            ], 422);
        }

        try {
            $result = $this->sepayPaymentService->createPayment($booking);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Đã tạo thông tin thanh toán SePay.',
            'payment' => $result['payment'],
            'payment_account' => $result['payment_account'],
            'system_bank_account' => $result['system_bank_account'],
            'transfer_content' => $result['transfer_content'],
            'qr_url' => $result['qr_url'],
        ]);
    }

    public function ipn(Request $request): JsonResponse
    {
        if (! $this->sepayPaymentService->ipnIsAuthorized($request->header('Authorization'))) {
            return response()->json([
                'success' => false,
                'message' => 'SePay IPN không hợp lệ.',
            ], 401);
        }

        $result = $this->sepayPaymentService->handleIpn($request->all());

        return response()->json([
            'success' => true,
            'processed' => $result['success'] ?? false,
            'error_code' => $result['error_code'] ?? null,
            'message' => $result['message'] ?? null,
        ]);
    }
}
