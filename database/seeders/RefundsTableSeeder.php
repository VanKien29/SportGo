<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\UserPayoutAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RefundsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('refunds') || ! Schema::hasTable('payments')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $owner = User::query()->where('username', 'owner')->first();
        $customer = User::query()->where('username', 'user')->first();
        $payoutAccount = $customer && Schema::hasTable('user_payout_accounts')
            ? UserPayoutAccount::query()
                ->where('user_id', $customer->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->first()
            : null;

        $refunds = [
            [
                'PMADMREF1',
                150000,
                'Khách hủy lịch trong thời gian được hoàn tiền.',
                'pending_confirmation',
                null,
                null,
                null,
                null,
                null,
                now()->subDay(),
            ],
            [
                'PMADMREFPROC1',
                180000,
                'Hoàn tiền qua ngân hàng, dùng để test QR và export MB bulk.',
                'processing',
                null,
                $owner?->id,
                now()->subHours(20),
                'Owner đã xác nhận hoàn tiền qua tài khoản ngân hàng.',
                'RFSEEDREF001',
                now()->subHours(18),
            ],
            [
                'PMADMREFCOMP1',
                90000,
                'Đã chuyển khoản hoàn tiền cho khách.',
                'completed',
                null,
                $owner?->id,
                now()->subDays(2),
                'Owner xác nhận và admin đã hoàn tất chuyển khoản.',
                'RFSEEDREF002',
                now()->subDays(2),
            ],
            [
                'PMADMREFFAIL1',
                125000,
                'Hoàn tiền lỗi, cần xử lý lại thông tin nhận tiền.',
                'failed',
                'Ngân hàng trả lỗi số tài khoản không hợp lệ.',
                $owner?->id,
                now()->subDays(3),
                'Owner xác nhận nhưng giao dịch hoàn tiền bị lỗi.',
                'RFSEEDREF003',
                now()->subDays(3),
            ],
            [
                'PMADMREFREJ1',
                110000,
                'Yêu cầu hoàn tiền bị từ chối do quá hạn chính sách.',
                'rejected',
                'Yêu cầu hủy nằm ngoài khung thời gian được hoàn tiền.',
                null,
                null,
                null,
                null,
                null,
            ],
        ];

        foreach ($refunds as [$paymentCode, $amount, $reason, $status, $statusReason, $ownerConfirmedBy, $ownerConfirmedAt, $ownerNote, $transferCode, $qrCreatedAt]) {
            $payment = Payment::query()->where('payment_code', $paymentCode)->first();

            if (! $payment) {
                continue;
            }

            Refund::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                ],
                [
                    'customer_id' => $customer?->id,
                    'amount' => $amount,
                    'reason' => $reason,
                    'refund_destination' => $status === 'rejected' ? 'original_payment' : 'bank_account',
                    'user_payout_account_id' => $status === 'rejected' ? null : $payoutAccount?->id,
                    'status' => $status,
                    'status_reason' => $statusReason,
                    'owner_confirmed_by' => $ownerConfirmedBy,
                    'owner_confirmed_at' => $ownerConfirmedAt,
                    'owner_confirm_note' => $ownerNote,
                    'processed_by' => in_array($status, ['processing', 'completed', 'failed', 'rejected'], true) ? $admin?->id : null,
                    'processed_at' => in_array($status, ['processing', 'completed', 'failed', 'rejected'], true) ? now()->subHours(12) : null,
                    'admin_confirmed_by' => $status === 'completed' ? $admin?->id : null,
                    'admin_confirmed_at' => $status === 'completed' ? now()->subDays(1) : null,
                    'gateway_refund_txn_id' => $status === 'completed' ? 'MB-REFUND-SEED-001' : null,
                    'payout_transfer_code' => $transferCode,
                    'payout_qr_created_at' => $qrCreatedAt,
                ]
            );
        }
    }
}
