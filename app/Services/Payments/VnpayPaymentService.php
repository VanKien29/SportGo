<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SlotLock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VnpayPaymentService
{
    public function createPaymentUrl(Request $request, Booking $booking): array
    {
        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('method', 'vnpay')
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($payment && $this->isPendingStale($payment)) {
            $this->markPendingPaymentAsStale($payment);
            $payment = null;
        }

        if (! $payment) {
            $payment = Payment::query()->create([
                'payment_code' => 'PM' . Str::upper(Str::random(10)),
                'booking_id' => $booking->id,
                'amount' => $booking->required_payment_amount,
                'payment_kind' => $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
                'method' => 'vnpay',
                'status' => 'pending',
            ]);
        }

        $params = $this->paymentParams($request, $booking, $payment);
        $paymentUrl = $this->buildPaymentUrl($params);

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'vnpay_create_url',
            'request_payload' => $params,
            'status_before' => $payment->status,
            'status_after' => $payment->status,
        ]);

        return [
            'payment_url' => $paymentUrl,
            'payment' => $payment,
        ];
    }

    public function handleReturn(array $payload): array
    {
        $payment = $this->findPayment($payload);

        if (! $payment) {
            return ['found' => false];
        }

        $result = $this->applyGatewayPayload($payment, $payload, 'vnpay_return');

        return [
            'found' => true,
            'paid' => $result['paid'],
            'booking_id' => $payment->booking_id,
            'payment' => $payment->fresh(),
        ];
    }

    private function paymentParams(Request $request, Booking $booking, Payment $payment): array
    {
        return [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => config('services.vnpay.tmn_code'),
            'vnp_Amount' => (int) round((float) $payment->amount * 100),
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $request->ip(),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toán đơn đặt sân ' . $booking->booking_code,
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => config('services.vnpay.return_url'),
            'vnp_TxnRef' => $payment->payment_code,
            'vnp_BankCode' => 'NCB',
        ];
    }

    private function buildPaymentUrl(array $params): string
    {
        ksort($params);

        $query = $this->queryString($params);
        $secureHash = hash_hmac('sha512', $query, (string) config('services.vnpay.hash_secret'));

        return rtrim((string) config('services.vnpay.payment_url'), '?') . '?' . $query . '&vnp_SecureHash=' . $secureHash;
    }

    private function hashPayload(array $payload): string
    {
        unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);
        ksort($payload);

        return hash_hmac(
            'sha512',
            $this->queryString($payload),
            (string) config('services.vnpay.hash_secret')
        );
    }

    private function applyGatewayPayload(Payment $payment, array $payload, string $eventType): array
    {
        $statusBefore = $payment->status;
        $isValidSignature = $this->hasValidSignature($payload);
        $isValidAmount = $this->amountMatches($payment, $payload);
        $gatewayTxnId = $payload['vnp_TransactionNo'] ?? null;
        $hasDuplicateGatewayTxn = $this->hasDuplicateGatewayTxn($payment, $gatewayTxnId);

        // Điều kiện để xác định giao dịch đã được thanh toán thành công:
        $isPaid = $isValidSignature
            && $isValidAmount
            && ! $hasDuplicateGatewayTxn
            && ($payload['vnp_ResponseCode'] ?? null) === '00'
            && ($payload['vnp_TransactionStatus'] ?? null) === '00';

        DB::transaction(function () use ($payment, $payload, $eventType, $statusBefore, $gatewayTxnId, $isValidSignature, $isValidAmount, $hasDuplicateGatewayTxn, $isPaid): void {
            // ghi log nếu callback trùng giao dịch đã thanh toán trước đó 
            if ($payment->status === 'paid') {
                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => $eventType . '_duplicate',
                    'response_payload' => $payload,
                    'status_before' => $statusBefore,
                    'status_after' => $payment->status,
                    'gateway_txn_id' => $gatewayTxnId,
                    'error_code' => 'duplicate_callback',
                    'error_message' => 'VNPAY gọi lại giao dịch đã được xác nhận trước đó.',
                ]);

                return;
            }

            if ($payment->status !== 'paid') {
                // chỉ cập nhật gateway_txn_id nếu callback này có mã giao dịch chưa từng xuất hiện trước đó
                if (! $hasDuplicateGatewayTxn) {
                    $payment->gateway_txn_id = $gatewayTxnId;
                }

                $payment->gateway_response = $payload;

                if ($isPaid) {
                    // Thanh toán thành công, cập nhật trạng thái thanh toán và đơn đặt sân.
                    $payment->status = 'paid';
                    $payment->paid_at = Carbon::now();
                    $payment->save();

                    $payment->booking()->update([
                        'status' => 'confirmed',
                    ]);

                    SlotLock::query()
                        ->where('booking_id', $payment->booking_id)
                        ->delete();
                } else {
                    $payment->status = 'failed';
                    $payment->save();
                }
            }

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => $eventType,
                'response_payload' => $payload,
                'status_before' => $statusBefore,
                'status_after' => $payment->fresh()->status,
                'gateway_txn_id' => $gatewayTxnId,
                'error_code' => $this->gatewayErrorCode($payload, $isValidSignature, $isValidAmount, $hasDuplicateGatewayTxn),
                'error_message' => $this->gatewayErrorMessage($payload, $isValidSignature, $isValidAmount, $hasDuplicateGatewayTxn),
            ]);
        });

        return [
            'paid' => $payment->fresh()->status === 'paid',
        ];
    }

    // tìm giao dịch thanh toán dựa trên mã giao dịch của VNPAY trả về
    private function findPayment(array $payload): ?Payment
    {
        $paymentCode = $payload['vnp_TxnRef'] ?? null;

        return $paymentCode
            ? Payment::query()->where('payment_code', $paymentCode)->first()
            : null;
    }

    // xác thực chữ ký số của VNPAY trả về có hợp lệ hay không
    private function hasValidSignature(array $payload): bool
    {
        $secureHash = $payload['vnp_SecureHash'] ?? '';

        return $secureHash !== '' && hash_equals($secureHash, $this->hashPayload($payload));
    }

    // xác thực số tiền trả về có khớp với số tiền giao dịch hay không
    private function amountMatches(Payment $payment, array $payload): bool
    {
        if (! isset($payload['vnp_Amount'])) {
            return false;
        }

        return (int) $payload['vnp_Amount'] === (int) round((float) $payment->amount * 100);
    }

    private function hasDuplicateGatewayTxn(Payment $payment, ?string $gatewayTxnId): bool
    {
        if (! $gatewayTxnId) {
            return false;
        }

        return Payment::query()
            ->where('gateway_txn_id', $gatewayTxnId)
            ->whereKeyNot($payment->id)
            ->exists();
    }

    private function isPendingStale(Payment $payment): bool
    {
        $ttl = (int) config('services.vnpay.pending_ttl_minutes', 15);

        return $payment->status === 'pending'
            && $payment->created_at
            && $payment->created_at->lte(now()->subMinutes($ttl));
    }

    private function markPendingPaymentAsStale(Payment $payment): void
    {
        $statusBefore = $payment->status;

        $payment->forceFill([
            'status' => 'failed',
            'gateway_response' => [
                'reason' => 'stale_pending_payment',
                'message' => 'Giao dịch VNPAY pending đã quá thời gian chờ.',
            ],
        ])->save();

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'vnpay_pending_stale',
            'status_before' => $statusBefore,
            'status_after' => $payment->status,
            'error_code' => 'stale_pending_payment',
            'error_message' => 'Giao dịch VNPAY pending đã quá thời gian chờ, hệ thống tạo giao dịch mới.',
        ]);
    }

    // xác định mã lỗi cụ thể
    private function gatewayErrorCode(array $payload, bool $isValidSignature, bool $isValidAmount, bool $hasDuplicateGatewayTxn): ?string
    {
        if (! $isValidSignature) {
            return 'invalid_signature';
        }

        if (! $isValidAmount) {
            return 'invalid_amount';
        }

        if ($hasDuplicateGatewayTxn) {
            return 'duplicate_gateway_txn_id';
        }

        return $payload['vnp_ResponseCode'] ?? null;
    }

    // xác định thông điệp lỗi cụ thể
    private function gatewayErrorMessage(array $payload, bool $isValidSignature, bool $isValidAmount, bool $hasDuplicateGatewayTxn): ?string
    {
        if (! $isValidSignature) {
            return 'Chữ ký VNPAY không hợp lệ.';
        }

        if (! $isValidAmount) {
            return 'Số tiền VNPAY trả về không khớp với số tiền giao dịch.';
        }

        if ($hasDuplicateGatewayTxn) {
            return 'Mã giao dịch VNPAY đã tồn tại ở giao dịch khác.';
        }

        return match ($payload['vnp_ResponseCode'] ?? null) {
            '00' => null,
            '11' => 'Giao dịch VNPAY đã hết hạn thanh toán.',
            '24' => 'Người dùng đã hủy giao dịch VNPAY.',
            default => isset($payload['vnp_ResponseCode'])
                ? 'VNPAY trả về mã lỗi ' . $payload['vnp_ResponseCode'] . '.'
                : 'VNPAY không trả về mã kết quả giao dịch.',
        };
    }

    private function queryString(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = urlencode((string) $key) . '=' . urlencode((string) $value);
        }

        return implode('&', $parts);
    }
}
