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
        $secureHash = $payload['vnp_SecureHash'] ?? '';
        $paymentCode = $payload['vnp_TxnRef'] ?? null;

        $payment = $paymentCode
            ? Payment::query()->where('payment_code', $paymentCode)->first()
            : null;

        if (! $payment) {
            return ['found' => false];
        }

        $statusBefore = $payment->status;
        $isValidSignature = hash_equals($secureHash, $this->hashPayload($payload));
        $responseCode = $payload['vnp_ResponseCode'] ?? null;
        $transactionStatus = $payload['vnp_TransactionStatus'] ?? null;
        $gatewayTxnId = $payload['vnp_TransactionNo'] ?? null;
        $isPaid = $isValidSignature && $responseCode === '00' && $transactionStatus === '00';

        DB::transaction(function () use ($payment, $payload, $statusBefore, $gatewayTxnId, $isValidSignature, $isPaid): void {
            $payment->gateway_txn_id = $gatewayTxnId;
            $payment->gateway_response = $payload;

            if ($isPaid) {
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

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'vnpay_return',
                'response_payload' => $payload,
                'status_before' => $statusBefore,
                'status_after' => $payment->status,
                'gateway_txn_id' => $gatewayTxnId,
                'error_code' => $isValidSignature ? ($payload['vnp_ResponseCode'] ?? null) : 'invalid_signature',
                'error_message' => $isValidSignature ? null : 'VNPAY secure hash mismatch.',
            ]);
        });

        return [
            'found' => true,
            'paid' => $isPaid,
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
