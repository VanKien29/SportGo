<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Services\Payments\VnpayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VnpayPaymentController extends Controller
{
    public function __construct(private readonly VnpayPaymentService $vnpayPaymentService)
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

        $result = $this->vnpayPaymentService->createPaymentUrl($request, $booking);

        return response()->json([
            'payment_url' => $result['payment_url'],
            'payment' => $result['payment'],
            'booking' => $booking,
        ]);
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $result = $this->vnpayPaymentService->handleReturn($request->query());

        if (! $result['found']) {
            return response()->json([
                'message' => 'Không tìm thấy giao dịch thanh toán.',
            ], 404);
        }

        $status = $result['paid'] ? 'success' : 'failed';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['paid'] ? 'Thanh toán VNPAY thành công.' : 'Thanh toán VNPAY thất bại.',
                'payment_status' => $status,
                'booking_id' => $result['booking_id'],
            ]);
        }

        return redirect(rtrim(config('app.url'), '/').'/payment/vnpay/return?'.http_build_query([
            'booking_id' => $result['booking_id'],
            'payment_status' => $status,
        ]));
    }
}
