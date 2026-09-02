<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Services\Finance\SepayPayoutService;
use App\Services\Payments\SepayPaymentService;
use App\Services\Payments\PlatformFeePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SepayPaymentController extends Controller
{
    public function __construct(
        private readonly SepayPaymentService $sepayPaymentService,
        private readonly SepayPayoutService $sepayPayoutService,
        private readonly PlatformFeePaymentService $platformFeePaymentService,
    ) {}

    public function create(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if ($booking->customer_id !== $request->user()?->id) {
            return response()->json([
                'message' => 'Bạn không có quyền thanh toán đơn đặt sân này.',
            ], 403);
        }

        $isPayLaterPayment = in_array($booking->status, ['pending_approval', 'confirmed'], true)
            && $booking->payment_option === 'no_prepay'
            && ($booking->effective_payment_option ?: $booking->payment_option) === 'no_prepay';
        $isDepositPayment = $booking->status === 'pending_payment'
            || ($booking->status === 'pending_approval' && $booking->payment_option === 'deposit');

        if (! $isDepositPayment && ! $isPayLaterPayment) {
            return response()->json([
                'message' => 'Đơn đặt sân này chưa ở trạng thái có thể thanh toán chuyển khoản.',
            ], 422);
        }

        $payFull = $request->boolean('pay_full')
            && in_array($booking->payment_option, ['deposit', 'no_prepay'], true);
        $paidAmount = (float) $booking->payments()
            ->where('status', 'paid')
            ->sum('amount');
        $paymentAmount = $payFull
            ? max((float) $booking->total_price - $paidAmount, 0)
            : ($isPayLaterPayment
                ? (float) $booking->total_price
                : (float) $booking->required_payment_amount);

        if ($paymentAmount <= 0) {
            return response()->json([
                'message' => 'Đây là đơn đặt sân thanh toán trực tiếp tại sân.',
            ], 422);
        }

        if ($booking->payment_option === 'deposit' && ! $payFull
            && $paidAmount + 0.01 >= (float) $booking->required_payment_amount) {
            return response()->json([
                'message' => 'Khoản cọc đã được ghi nhận. Vui lòng chờ chủ sân duyệt booking.',
            ], 422);
        }

        $lock = $booking->status === 'confirmed'
            ? true
            : ($booking->status === 'pending_approval'
                ? now()->lt(app(\App\Services\Bookings\BookingApprovalService::class)->approvalDeadline($booking))
                : SlotLock::query()
                ->where('booking_id', $booking->id)
                ->where('expires_at', '>', now())
                ->exists());

        if (! $lock) {
            return response()->json([
                'message' => 'Đơn đặt sân đã hết thời gian giữ chỗ. Vui lòng đặt lại.',
            ], 422);
        }

        try {
            $result = $this->sepayPaymentService->createPayment($booking, $payFull);
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

    public function cancel(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if ($booking->customer_id !== $request->user()?->id) {
            return response()->json([
                'message' => 'Bạn không có quyền hủy thanh toán đơn đặt sân này.',
            ], 403);
        }

        try {
            $result = $this->sepayPaymentService->cancelPendingPayment($booking, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Đã hủy thanh toán và hủy đơn đặt sân.',
            'booking' => $result['booking'],
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

        if (($result['error_code'] ?? null) === 'payment_not_found') {
            $result = $this->platformFeePaymentService->handleIpn($request->all());
        }

        if (($result['error_code'] ?? null) === 'platform_fee_payment_not_found') {
            $result = $this->sepayPayoutService->handleIpn($request->all());
        }

        return response()->json([
            'success' => true,
            'processed' => $result['success'] ?? false,
            'error_code' => $result['error_code'] ?? null,
            'message' => $result['message'] ?? null,
        ]);
    }
}
