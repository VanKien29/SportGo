<?php

namespace App\Services\Payments;

use App\Events\BookingPaymentUpdated;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SlotLock;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Bookings\BookingApprovalService;
use App\Services\Bookings\BookingLifecycleService;
use App\Services\Memberships\SystemVipService;
use App\Services\Wallets\OwnerWalletService;
use App\Services\Wallets\SystemWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SepayPaymentService
{
    public function __construct(
        private readonly OwnerWalletService $ownerWalletService,
        private readonly SystemWalletService $systemWalletService,
        private readonly BookingService $bookingService,
        private readonly SystemVipService $systemVipService,
        private readonly BookingApprovalService $bookingApprovals,
        private readonly BookingLifecycleService $bookingLifecycle,
    ) {}

    public function createPayment(Booking $booking): array
    {
        $effectivePaymentOption = $booking->effective_payment_option ?: $booking->payment_option;
        $isPayLaterPayment = $booking->payment_option === 'no_prepay'
            && $effectivePaymentOption === 'no_prepay'
            && in_array($booking->status, ['pending_approval', 'confirmed'], true);
        $paidAmount = (float) $booking->payments()
            ->where('status', 'paid')
            ->sum('amount');

        if ($booking->payment_option === 'deposit') {
            if ($paidAmount + 0.01 >= (float) $booking->required_payment_amount) {
                throw new RuntimeException('Khoản cọc đã được ghi nhận. Vui lòng chờ chủ sân duyệt booking.');
            }
        }

        if ($isPayLaterPayment && $paidAmount + 0.01 >= (float) $booking->total_price) {
            throw new RuntimeException('Booking này đã được thanh toán đủ.');
        }

        if ($isPayLaterPayment && $paidAmount > 0.009) {
            throw new RuntimeException('Booking trả sau đã phát sinh khoản thanh toán một phần. Vui lòng liên hệ sân để đối soát trước khi thanh toán tiếp.');
        }

        $paymentAmount = $isPayLaterPayment
            ? (float) $booking->total_price
            : (float) $booking->required_payment_amount;

        $account = $this->resolveSystemBankAccount();

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('method', 'sepay')
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment) {
            $payment = Payment::query()->create([
                'payment_code' => 'PM'.Str::upper(Str::random(10)),
                'booking_id' => $booking->id,
                'system_bank_account_id' => $account->id,
                'amount' => $paymentAmount,
                'wallet_amount' => 0,
                'gateway_amount' => $paymentAmount,
                'payment_kind' => $isPayLaterPayment || $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
                'method' => 'sepay',
                'status' => 'pending',
            ]);
        } else {
            $payment->update([
                'system_bank_account_id' => $payment->system_bank_account_id ?: $account->id,
                'amount' => $paymentAmount,
                'wallet_amount' => 0,
                'gateway_amount' => $paymentAmount,
                'payment_kind' => $isPayLaterPayment || $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
            ]);
        }

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'sepay_create_payment',
            'request_payload' => [
                'system_bank_account_id' => $account->id,
                'transfer_content' => $payment->payment_code,
                'qr_url' => $this->qrUrl($payment, $account),
            ],
            'status_before' => $payment->status,
            'status_after' => $payment->status,
        ]);

        return [
            'payment' => $payment->fresh(),
            'payment_account' => $account,
            'system_bank_account' => $account,
            'transfer_content' => $payment->payment_code,
            'qr_url' => $this->qrUrl($payment, $account),
        ];
    }

    public function createCounterCollectionPayment(Booking $booking, User $actor, ?float $amount = null): array
    {
        return DB::transaction(function () use ($booking, $actor, $amount): array {
            $booking = Booking::query()
                ->with('payments')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($booking->status, ['cancelled', 'expired', 'rejected', 'no_show'], true)) {
                throw new RuntimeException('Booking này không còn ở trạng thái có thể thu tiền.');
            }

            if (in_array($booking->status, ['pending_approval', 'pending_payment'], true)
                && ($booking->effective_payment_option ?: $booking->payment_option) === 'no_prepay') {
                throw new RuntimeException('Vui lòng duyệt booking trước khi tạo QR thu tiền trả sau.');
            }

            $outstandingAmount = $this->outstandingAmount($booking);
            $collectionAmount = round((float) ($amount ?: $outstandingAmount), 2);

            $isPayLater = ($booking->effective_payment_option ?: $booking->payment_option) === 'no_prepay';
            if ($isPayLater && abs($collectionAmount - $outstandingAmount) > 0.009) {
                throw new RuntimeException('Booking trả sau chỉ được thu đủ một lần, không hỗ trợ thanh toán từng phần.');
            }

            if ($collectionAmount <= 0 || $collectionAmount > $outstandingAmount) {
                throw new RuntimeException('Số tiền thu không hợp lệ so với số còn phải thu.');
            }

            $account = $this->resolveSystemBankAccount();
            $this->failPendingNonSepayPayments($booking, $actor);

            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('method', 'sepay')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->latest()
                ->first();

            $reused = (bool) $payment;

            if (! $payment) {
                $payment = Payment::query()->create([
                    'payment_code' => 'PM'.Str::upper(Str::random(10)),
                    'booking_id' => $booking->id,
                    'system_bank_account_id' => $account->id,
                    'amount' => $collectionAmount,
                    'wallet_amount' => 0,
                    'gateway_amount' => $collectionAmount,
                    'payment_kind' => $this->paymentKindForCounterCollection($booking, $collectionAmount),
                    'method' => 'sepay',
                    'gateway_response' => [
                        'counter_collection' => [
                            'source' => 'owner_counter_qr',
                            'actor_id' => $actor->id,
                            'created_at' => now()->toIso8601String(),
                        ],
                    ],
                    'status' => 'pending',
                ]);
            } else {
                $account = $payment->system_bank_account_id
                    ? (SystemBankAccount::query()->find($payment->system_bank_account_id) ?: $account)
                    : $account;
                $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];

                $payment->update([
                    'system_bank_account_id' => $payment->system_bank_account_id ?: $account->id,
                    'wallet_amount' => 0,
                    'gateway_amount' => $payment->amount,
                    'gateway_response' => array_merge($gatewayResponse, [
                        'counter_collection_reopened' => [
                            'actor_id' => $actor->id,
                            'reopened_at' => now()->toIso8601String(),
                        ],
                    ]),
                ]);
            }

            $this->bookingService->ensurePendingPaymentLocks($booking, $actor->id);

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => $reused ? 'counter_sepay_qr_reopened' : 'counter_sepay_qr_created',
                'request_payload' => [
                    'actor_id' => $actor->id,
                    'system_bank_account_id' => $account->id,
                    'transfer_content' => $payment->payment_code,
                    'qr_url' => $this->qrUrl($payment, $account),
                    'amount' => (float) $payment->amount,
                ],
                'status_before' => $payment->status,
                'status_after' => $payment->status,
            ]);

            return [
                'payment' => $payment->fresh(),
                'payment_account' => $account,
                'system_bank_account' => $account,
                'transfer_content' => $payment->payment_code,
                'qr_url' => $this->qrUrl($payment, $account),
                'reused' => $reused,
            ];
        });
    }

    public function handleIpn(array $payload): array
    {
        $normalized = $this->normalizeIpnPayload($payload);
        $payment = $this->findPaymentFromIpn($normalized);

        if (! $payment) {
            return [
                'success' => false,
                'error_code' => 'payment_not_found',
                'message' => 'Không tìm thấy payment tương ứng với giao dịch SePay.',
            ];
        }

        $result = DB::transaction(function () use ($payment, $payload, $normalized): array {
            $payment = Payment::query()
                ->with(['booking.venueCluster', 'systemBankAccount'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statusBefore = $payment->status;
            $gatewayTxnId = $normalized['transaction_id'];

            if ($payment->status === 'paid') {
                $isCounterCollection = data_get($payment->gateway_response, 'counter_collection.source') === 'owner_counter_qr';
                if (($payment->payment_context ?? 'booking') === 'vip_subscription') {
                    $this->systemVipService->activateSubscriptionFromPayment($payment);
                } else {
                    $this->ownerWalletService->creditBookingPayment($payment, $normalized);
                    $this->recordSystemWalletPayment($payment, $normalized);
                    if ($isCounterCollection && $payment->booking) {
                        $this->bookingService->markCounterBookingCheckedInAfterPayment(
                            $payment->booking->fresh(),
                        );
                    }
                }
                $this->logIpn($payment, $payload, $statusBefore, 'sepay_ipn_duplicate', $gatewayTxnId, 'duplicate_callback', 'SePay gửi lại webhook cho payment đã thanh toán.');

                return [
                    'success' => true,
                    'payment' => $payment,
                ];
            }

            // A transfer callback may arrive after the approval window or after
            // an owner collected cash. The failed payment is still a valid
            // reference for SePay, but the money must be refunded immediately.
            $booking = $payment->booking;
            $paymentGatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];
            $isCounterCollection = data_get($paymentGatewayResponse, 'counter_collection.source') === 'owner_counter_qr';
            $latePayment = $payment->status === 'failed'
                && $booking
                && in_array($booking->payment_option, ['deposit', 'no_prepay'], true)
                && (
                    in_array($booking->status, ['expired', 'cancelled', 'rejected'], true)
                    || ($booking->status === 'confirmed'
                        && (
                            ($booking->payment_option === 'deposit' && $booking->effective_payment_option === 'no_prepay')
                            || data_get($paymentGatewayResponse, 'replaced_by_counter_collection') !== null
                        ))
                );

            if ($payment->status !== 'pending' && ! $latePayment) {
                $this->logIpn($payment, $payload, $statusBefore, 'sepay_ipn_ignored', $gatewayTxnId, 'payment_not_pending', 'Payment không còn ở trạng thái chờ thanh toán.');

                return [
                    'success' => false,
                    'error_code' => 'payment_not_pending',
                    'message' => 'Payment không còn ở trạng thái chờ thanh toán.',
                    'payment' => $payment,
                ];
            }

            $isDuplicateGatewayTxn = $this->isDuplicateGatewayTxn($payment, $gatewayTxnId);
            $errorCode = $this->ipnErrorCode($payment, $normalized, $isDuplicateGatewayTxn);

            $payment->gateway_response = $payload;

            if ($gatewayTxnId !== '' && ! $isDuplicateGatewayTxn) {
                $payment->gateway_txn_id = $gatewayTxnId;
            }

            if ($errorCode === null) {
                $payment->status = 'paid';
                $payment->wallet_amount = 0;
                $payment->gateway_amount = $payment->amount;
                $payment->paid_at = now();
                // Keep the counter-collection marker so duplicate callbacks
                // can still be identified as owner-created QR payments.
                $payment->gateway_response = array_merge($paymentGatewayResponse, [
                    'sepay_ipn' => $payload,
                ]);
                $payment->save();

                if (($payment->payment_context ?? 'booking') === 'vip_subscription') {
                    $this->systemVipService->activateSubscriptionFromPayment($payment);
                } else {
                    $booking = $payment->booking;
                    $latePayment = $booking
                        && in_array($booking->payment_option, ['deposit', 'no_prepay'], true)
                        && (
                            in_array($booking->status, ['expired', 'cancelled', 'rejected'], true)
                            || ($booking->status === 'confirmed'
                                && (
                                    ($booking->payment_option === 'deposit' && $booking->effective_payment_option === 'no_prepay')
                                    || data_get($paymentGatewayResponse, 'replaced_by_counter_collection') !== null
                                ))
                        );

                    if ($latePayment) {
                        $payment->save();
                        $this->bookingApprovals->refundLatePayment(
                            $booking,
                            $payment,
                            'Khoản chuyển khoản đến sau khi booking đã hết hiệu lực hoặc đã được thu tiền mặt.',
                        );
                        $this->logIpn($payment->fresh(), $payload, $statusBefore, 'sepay_late_payment_refunded', $gatewayTxnId, 'late_payment_refunded', 'Khoản chuyển khoản đến muộn đã được hoàn vào ví khách hàng.');

                        return [
                            'success' => true,
                            'error_code' => 'late_payment_refunded',
                            'message' => 'Khoản chuyển khoản đến muộn đã được hoàn vào ví khách hàng.',
                            'payment' => $payment->fresh(),
                        ];
                    }

                    if (in_array($booking?->status, ['pending_approval', 'pending_payment'], true)) {
                        // Booking cọc đã chờ duyệt từ lúc tạo. Thanh toán cọc
                        // chỉ ghi nhận tiền; chủ sân vẫn phải duyệt trong SLA.
                        $nextStatus = in_array($booking?->payment_option, ['deposit', 'no_prepay'], true)
                            ? 'pending_approval'
                            : 'confirmed';
                        $bookingStatusBefore = $booking?->status;
                        $payment->booking()->update([
                            'status' => $nextStatus,
                            'effective_payment_option' => $booking?->payment_option ?: 'no_prepay',
                            'payment_deadline_at' => null,
                        ]);
                        if ($nextStatus === 'confirmed') {
                            $confirmedBooking = $booking->fresh(['customer', 'venueCluster']);
                            $this->bookingLifecycle->recordHistory(
                                $confirmedBooking,
                                $bookingStatusBefore,
                                'confirmed',
                                'payment_received',
                                'Đã nhận đủ thanh toán trực tuyến và xác nhận booking.',
                            );
                            $this->bookingLifecycle->notifyStatusChanged(
                                $confirmedBooking,
                                'confirmed',
                                'payment_received',
                                'Đã nhận đủ thanh toán trực tuyến và xác nhận booking.',
                            );
                            $this->bookingService->syncVenueMembershipForSuccessfulBooking($confirmedBooking);
                        }
                    }

                    if (! in_array($booking?->payment_option, ['deposit', 'no_prepay'], true)
                        || $booking?->status !== 'pending_approval') {
                        SlotLock::query()
                            ->where('booking_id', $payment->booking_id)
                            ->delete();
                    }

                    $this->ownerWalletService->creditBookingPayment($payment, $normalized);
                    $this->recordSystemWalletPayment($payment->fresh(['booking', 'systemBankAccount']), $normalized);

                    if ($isCounterCollection && $payment->booking) {
                        $this->bookingService->markCounterBookingCheckedInAfterPayment(
                            $payment->booking->fresh(),
                        );
                    }
                }
            } else {
                $payment->status = 'failed';
                $payment->save();
            }

            $this->logIpn(
                $payment->fresh(),
                $payload,
                $statusBefore,
                'sepay_ipn',
                $gatewayTxnId,
                $errorCode,
                $this->ipnErrorMessage($errorCode),
            );

            return [
                'success' => $errorCode === null,
                'error_code' => $errorCode,
                'message' => $this->ipnErrorMessage($errorCode),
                'payment' => $payment->fresh(),
            ];
        });

        $payment = $result['payment'] ?? null;
        if ($payment?->booking_id) {
            $booking = Booking::query()->find($payment->booking_id);
            if ($booking?->customer_id) {
                try {
                    broadcast(new BookingPaymentUpdated(
                        $booking->id,
                        $booking->customer_id,
                        $payment->id,
                        (string) $payment->status,
                        (string) $booking->status,
                    ));
                } catch (\Throwable $exception) {
                    // A websocket outage must not make SePay retry a webhook
                    // that has already been processed successfully.
                    report($exception);
                }
            }
        }

        return $result;
    }

    public function cancelPendingPayment(
        Booking $booking,
        string $cancelledBy,
        ?string $reason = null,
        string $eventType = 'sepay_payment_cancelled',
    ): array
    {
        return DB::transaction(function () use ($booking, $cancelledBy, $reason, $eventType): array {
            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status !== 'pending_payment') {
                throw new RuntimeException('Chỉ có thể hủy thanh toán khi đơn đang chờ thanh toán.');
            }

            $cancelReason = $reason ?: 'Khách hàng hủy thanh toán chuyển khoản.';
            $errorCode = $eventType === 'sepay_payment_cancelled' ? 'customer_cancelled' : 'owner_cancelled';
            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $statusBefore = $payment->status;
                $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];

                $payment->gateway_response = array_merge($gatewayResponse, [
                    'cancelled_by' => $cancelledBy,
                    'cancelled_at' => now()->toIso8601String(),
                    'cancelled_reason' => $cancelReason,
                ]);
                $payment->status = 'failed';
                $payment->save();

                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => $eventType,
                    'status_before' => $statusBefore,
                    'status_after' => $payment->status,
                    'error_code' => $errorCode,
                    'error_message' => $cancelReason,
                ]);
            }

            $booking->status = 'cancelled';
            $booking->status_reason = $cancelReason;
            $booking->cancelled_by = $cancelledBy;
            $booking->cancelled_at = now();
            $booking->save();

            $this->bookingService->releaseVoucherUsageForBooking($booking, 'cancelled');

            SlotLock::query()
                ->where('booking_id', $booking->id)
                ->delete();

            return [
                'booking' => $booking->fresh(['venueCourt.venueCluster', 'venueCourt.courtType']),
            ];
        });
    }

    public function ipnIsAuthorized(?string $authorization): bool
    {
        $apiKey = config('services.sepay.webhook_api_key');

        if (! $apiKey) {
            return true;
        }

        return hash_equals('Apikey '.$apiKey, (string) $authorization);
    }

    private function resolveSystemBankAccount(): SystemBankAccount
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($account) {
            return $account;
        }

        throw new RuntimeException('Chưa có tài khoản ngân hàng hệ thống đang hoạt động trong bảng system_bank_accounts.');
    }

    private function qrUrl(Payment $payment, SystemBankAccount $account): string
    {
        return rtrim((string) config('services.sepay.qr_base_url', 'https://vietqr.app/img'), '?').'?'.http_build_query([
            'acc' => $account->account_number,
            'bank' => $account->bank_code ?: $account->bank_name,
            'amount' => (int) round((float) $payment->amount),
            'des' => $payment->payment_code,
            'template' => 'compact',
        ]);
    }

    private function outstandingAmount(Booking $booking): float
    {
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->sum('amount');

        return round(max((float) $booking->total_price - $paidAmount, 0), 2);
    }

    private function paymentKindForCounterCollection(Booking $booking, float $amount): string
    {
        if ((int) round($amount) >= (int) round((float) $booking->total_price)) {
            return 'full';
        }

        if ($booking->payment_option === 'deposit' && (int) round($amount) === (int) round((float) $booking->required_payment_amount)) {
            return 'deposit';
        }

        return 'partial';
    }

    private function failPendingNonSepayPayments(Booking $booking, User $actor): void
    {
        Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->where('method', '!=', 'sepay')
            ->lockForUpdate()
            ->get()
            ->each(function (Payment $payment) use ($actor): void {
                $statusBefore = $payment->status;
                $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];

                $payment->update([
                    'gateway_response' => array_merge($gatewayResponse, [
                        'replaced_by_counter_qr' => [
                            'actor_id' => $actor->id,
                            'replaced_at' => now()->toIso8601String(),
                        ],
                    ]),
                    'status' => 'failed',
                ]);

                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => 'counter_payment_replaced_by_qr',
                    'request_payload' => [
                        'actor_id' => $actor->id,
                        'booking_id' => $payment->booking_id,
                    ],
                    'status_before' => $statusBefore,
                    'status_after' => $payment->status,
                    'error_code' => 'counter_qr_replaced',
                    'error_message' => 'Payment pending được thay thế bởi QR SePay tại quầy.',
                ]);
            });
    }

    private function recordSystemWalletPayment(Payment $payment, array $metadata): void
    {
        $payment->loadMissing(['booking', 'systemBankAccount']);

        $account = $payment->systemBankAccount ?: $this->resolveSystemBankAccount();
        $transactionRef = $metadata['transaction_id'] ?: $payment->gateway_txn_id ?: 'PAYMENT-'.$payment->id;

        $this->systemWalletService->recordIncoming(
            $account,
            $transactionRef,
            (float) $payment->amount,
            'booking_payment',
            'payment',
            $payment->id,
            'SePay thu hộ booking '.$payment->booking?->booking_code.'.',
            array_merge($metadata, [
                'payment_id' => $payment->id,
                'payment_code' => $payment->payment_code,
                'booking_id' => $payment->booking_id,
                'booking_code' => $payment->booking?->booking_code,
            ]),
            $metadata['transaction_date'] ?? now(),
        );

        $systemVoucherAmount = $this->systemVoucherAmountForPayment($payment);

        $this->systemWalletService->reserveVoucher($systemVoucherAmount, $payment->id, $account, [
            'payment_id' => $payment->id,
            'payment_code' => $payment->payment_code,
            'booking_id' => $payment->booking_id,
            'booking_code' => $payment->booking?->booking_code,
            'customer_paid_amount' => (float) $payment->amount,
            'system_discount_amount' => (float) ($payment->booking?->system_discount_amount ?? 0),
        ]);
    }

    private function systemVoucherAmountForPayment(Payment $payment): float
    {
        $booking = $payment->booking;
        $systemDiscount = (float) ($booking?->system_discount_amount ?? 0);

        if ($systemDiscount <= 0) {
            return 0;
        }

        $customerPayable = (float) ($booking?->final_amount ?? $booking?->total_price ?? $payment->amount);

        if ($customerPayable <= 0) {
            return round($systemDiscount, 2);
        }

        $ratio = min(max((float) $payment->amount / $customerPayable, 0), 1);

        return round($systemDiscount * $ratio, 2);
    }

    private function normalizeIpnPayload(array $payload): array
    {
        return [
            'gateway' => $payload['gateway'] ?? null,
            'transaction_date' => $payload['transaction_date'] ?? $payload['transactionDate'] ?? null,
            'account_number' => (string) ($payload['account_number'] ?? $payload['accountNumber'] ?? ''),
            'payment_code' => $payload['payment_code'] ?? $payload['code'] ?? null,
            'content' => (string) ($payload['content'] ?? ''),
            'transfer_type' => Str::lower((string) ($payload['transfer_type'] ?? $payload['transferType'] ?? '')),
            'amount' => $payload['amount'] ?? $payload['transferAmount'] ?? null,
            'reference_code' => $payload['reference_code'] ?? $payload['referenceCode'] ?? null,
            'transaction_id' => (string) ($payload['transaction_id'] ?? $payload['id'] ?? $payload['reference_code'] ?? $payload['referenceCode'] ?? ''),
        ];
    }

    private function findPaymentFromIpn(array $payload): ?Payment
    {
        $paymentCode = $payload['payment_code'] ?: $this->extractPaymentCode((string) $payload['content']);

        return $paymentCode
            ? Payment::query()->where('payment_code', Str::upper($paymentCode))->first()
            : null;
    }

    private function extractPaymentCode(string $content): ?string
    {
        if (preg_match('/\bPM[A-Z0-9]{10}\b/i', $content, $matches)) {
            return Str::upper($matches[0]);
        }

        return null;
    }

    private function isDuplicateGatewayTxn(Payment $payment, string $gatewayTxnId): bool
    {
        if ($gatewayTxnId === '') {
            return false;
        }

        return Payment::query()
            ->where('gateway_txn_id', $gatewayTxnId)
            ->whereKeyNot($payment->id)
            ->exists();
    }

    private function ipnErrorCode(Payment $payment, array $payload, bool $isDuplicateGatewayTxn): ?string
    {
        if ($payload['transaction_id'] === '') {
            return 'missing_transaction_id';
        }

        if ($isDuplicateGatewayTxn) {
            return 'duplicate_gateway_txn_id';
        }

        if (! in_array($payload['transfer_type'], ['in', 'credit'], true)) {
            return 'invalid_transfer_type';
        }

        if ((int) round((float) $payload['amount']) !== (int) round((float) $payment->amount)) {
            return 'invalid_amount';
        }

        if (! $this->accountMatchesPayment($payment, $payload)) {
            return 'invalid_bank_account';
        }

        return null;
    }

    private function ipnErrorMessage(?string $errorCode): ?string
    {
        return match ($errorCode) {
            'missing_transaction_id' => 'SePay webhook thiếu mã giao dịch.',
            'duplicate_gateway_txn_id' => 'Mã giao dịch SePay đã tồn tại ở payment khác.',
            'invalid_transfer_type' => 'SePay webhook không phải giao dịch tiền vào.',
            'invalid_amount' => 'Số tiền SePay trả về không khớp với số tiền payment.',
            'invalid_bank_account' => 'Tài khoản nhận tiền SePay không khớp với payment.',
            default => null,
        };
    }

    private function accountMatchesPayment(Payment $payment, array $payload): bool
    {
        $account = $payment->systemBankAccount;

        if (! $account || $payload['account_number'] === '') {
            return true;
        }

        return $payload['account_number'] === $account->account_number;
    }

    private function logIpn(
        Payment $payment,
        array $payload,
        ?string $statusBefore,
        string $eventType,
        string $gatewayTxnId,
        ?string $errorCode,
        ?string $errorMessage
    ): void {
        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => $eventType,
            'response_payload' => $payload,
            'status_before' => $statusBefore,
            'status_after' => $payment->status,
            'gateway_txn_id' => $gatewayTxnId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
