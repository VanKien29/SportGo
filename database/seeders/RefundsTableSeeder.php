<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PolicyRule;
use App\Models\Refund;
use App\Models\SystemPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RefundsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('refunds') || ! Schema::hasTable('payments')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $policy = SystemPolicy::query()->where('key', 'booking_cancellation')->where('version', 1)->first();
        $refundRule = $policy
            ? PolicyRule::query()->where('system_policy_id', $policy->id)->where('rule_type', 'cancel_before_hours')->first()
            : null;

        $refunds = [
            // ===== pending_owner_confirmation — CHƯA THAO TÁC, chờ chủ sân duyệt =====
            [
                'payment_code' => 'PM-CANC-01',
                'amount' => 120000,
                'reason' => 'Khách hủy do thay đổi lịch, chờ chủ sân xác nhận hoàn tiền.',
                'refund_destination' => 'user_wallet',
                'status' => 'pending_owner_confirmation',
                'status_reason' => null,
                'owner_confirmed_by' => null,
                'owner_confirmed_at' => null,
                'owner_confirm_note' => null,
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            // ===== completed — chủ sân chọn hoàn vào ví =====
            [
                'payment_code' => 'PM-CANC-02',
                'amount' => 130000,
                'reason' => 'Chủ sân xác nhận hoàn tiền vào ví SportGo.',
                'refund_destination' => 'user_wallet',
                'status' => 'completed',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subHours(12),
                'owner_confirm_note' => 'Khách hủy hợp lệ theo chính sách, đồng ý hoàn tiền.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            // ===== completed_cash — chủ sân hoàn tiền mặt tại sân =====
            [
                'payment_code' => 'PM-CANC-03',
                'amount' => 150000,
                'reason' => 'Chủ sân xác nhận đã hoàn tiền mặt trực tiếp tại sân.',
                'refund_destination' => 'cash',
                'status' => 'completed_cash',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDays(2),
                'owner_confirm_note' => 'Đã đối soát booking và đồng ý hoàn.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => 'CASH-DEMO-003',
                'completed_at' => now()->subDays(2)->addHours(5),
                'cash_refunded_by' => $owner?->id,
                'cash_refunded_at' => now()->subDays(2)->addHours(5),
                'cash_refund_note' => 'Đã hoàn tiền mặt tại sân.',
            ],
            // ===== completed — hoàn tiền thành công =====
            [
                'payment_code' => 'PM-CANC-04',
                'amount' => 160000,
                'reason' => 'Yêu cầu hoàn đã được chủ sân xác nhận và hoàn vào ví SportGo.',
                'refund_destination' => 'user_wallet',
                'status' => 'completed',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDays(4)->addHours(2),
                'owner_confirm_note' => 'Đồng ý hoàn theo chính sách hủy trước giờ chơi.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => 'USER-WALLET-DEMO-004',
                'completed_at' => now()->subDays(4)->addHours(5),
            ],
            // ===== owner_rejected — chủ sân từ chối =====
            [
                'payment_code' => 'PM-CANC-05',
                'amount' => 120000,
                'reason' => 'Khách hủy sát giờ chơi, yêu cầu hoàn tiền.',
                'refund_destination' => 'user_wallet',
                'status' => 'owner_rejected',
                'status_reason' => 'Khách hủy quá sát giờ chơi, không đủ điều kiện hoàn tiền theo chính sách.',
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDays(2),
                'owner_confirm_note' => 'Không đủ điều kiện hoàn theo chính sách đã công bố.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            // ===== pending_owner_confirmation — booking cọc, CHƯA THAO TÁC =====
            [
                'payment_code' => 'PM-CANC-06',
                'amount' => 39000,
                'reason' => 'Khách hủy booking đặt cọc, chờ chủ sân xác nhận hoàn cọc.',
                'refund_destination' => 'user_wallet',
                'status' => 'pending_owner_confirmation',
                'status_reason' => null,
                'owner_confirmed_by' => null,
                'owner_confirmed_at' => null,
                'owner_confirm_note' => null,
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            // ===== completed — hoàn cọc xong =====
            [
                'payment_code' => 'PM-CANC-08',
                'amount' => 36000,
                'reason' => 'Khách hủy booking cọc sớm, đã hoàn cọc thành công.',
                'refund_destination' => 'user_wallet',
                'status' => 'completed',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDays(5)->addHours(2),
                'owner_confirm_note' => 'Đồng ý hoàn cọc do khách hủy sớm.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => 'USER-WALLET-DEMO-008',
                'completed_at' => now()->subDays(5)->addHours(5),
            ],
            // ===== pending_owner_confirmation — thêm 1 case CHƯA THAO TÁC =====
            [
                'payment_code' => 'PM-CANC-09',
                'amount' => 160000,
                'reason' => 'Khách bận công việc đột xuất, yêu cầu hoàn tiền.',
                'refund_destination' => 'user_wallet',
                'status' => 'pending_owner_confirmation',
                'status_reason' => null,
                'owner_confirmed_by' => null,
                'owner_confirmed_at' => null,
                'owner_confirm_note' => null,
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            // ===== pending_owner_confirmation — chờ chủ sân =====
            [
                'payment_code' => 'PM-CANC-10',
                'amount' => 130000,
                'reason' => 'Khách hủy do di chuyển xa, chờ chủ sân xác nhận hoàn tiền.',
                'refund_destination' => 'user_wallet',
                'status' => 'pending_owner_confirmation',
                'status_reason' => null,
                'owner_confirmed_by' => null,
                'owner_confirmed_at' => null,
                'owner_confirm_note' => null,
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
        ];

        foreach ($refunds as $row) {
            $payment = Payment::query()->where('payment_code', $row['payment_code'])->first();

            if (! $payment || ! $payment->booking_id) {
                continue;
            }

            $values = [
                'amount' => $row['amount'],
                'reason' => $row['reason'],
                'status' => $row['status'],
                'status_reason' => $row['status_reason'],
                'owner_confirmed_by' => $row['owner_confirmed_by'],
                'owner_confirmed_at' => $row['owner_confirmed_at'],
                'owner_confirm_note' => $row['owner_confirm_note'],
                'admin_confirmed_by' => $row['admin_confirmed_by'],
                'admin_confirmed_at' => $row['admin_confirmed_at'],
                'processed_by' => $row['processed_by'],
                'processed_at' => $row['processed_at'],
                'gateway_refund_txn_id' => $row['gateway_refund_txn_id'],
                'completed_at' => $row['completed_at'],
                'cash_refunded_by' => $row['cash_refunded_by'] ?? null,
                'cash_refunded_at' => $row['cash_refunded_at'] ?? null,
                'cash_refund_note' => $row['cash_refund_note'] ?? null,
                'policy_id' => $policy?->id,
                'policy_rule_id' => $refundRule?->id,
                'policy_evaluation_log_id' => null,
            ];

            if (Schema::hasColumn('refunds', 'refund_destination')) {
                $values['refund_destination'] = $row['refund_destination'];
            }

            Refund::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                ],
                $values,
            );
        }
    }
}
