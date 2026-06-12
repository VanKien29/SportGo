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
        $admin = User::query()->where('username', 'admin')->first();
        $policy = SystemPolicy::query()->where('key', 'booking_cancellation')->where('version', 1)->first();
        $refundRule = $policy
            ? PolicyRule::query()->where('system_policy_id', $policy->id)->where('rule_type', 'cancel_before_hours')->first()
            : null;

        $refunds = [
            [
                'payment_code' => 'PMADMREF1',
                'amount' => 150000,
                'reason' => 'Khách hủy sân trước giờ chơi, đang chờ chủ sân xác nhận.',
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
            [
                'payment_code' => 'PMADMREFPROC1',
                'amount' => 180000,
                'reason' => 'Chủ sân đồng ý hoàn tiền, đang chờ admin xử lý chuyển tiền.',
                'status' => 'owner_confirmed',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subHours(6),
                'owner_confirm_note' => 'Khách báo hủy hợp lệ theo chính sách.',
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'processed_by' => null,
                'processed_at' => null,
                'gateway_refund_txn_id' => null,
                'completed_at' => null,
            ],
            [
                'payment_code' => 'PMADMREFFAIL1',
                'amount' => 125000,
                'reason' => 'Admin đã gửi lệnh hoàn, đang chờ kết quả từ cổng thanh toán.',
                'status' => 'admin_processing',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDay(),
                'owner_confirm_note' => 'Đã đối soát booking và đồng ý hoàn.',
                'admin_confirmed_by' => $admin?->id,
                'admin_confirmed_at' => now()->subHours(3),
                'processed_by' => $admin?->id,
                'processed_at' => now()->subHours(3),
                'gateway_refund_txn_id' => 'RFSEED-PROCESSING-001',
                'completed_at' => null,
            ],
            [
                'payment_code' => 'PMADMREFCOMP1',
                'amount' => 90000,
                'reason' => 'Yêu cầu hoàn đã được chủ sân và admin xác nhận thành công.',
                'status' => 'completed',
                'status_reason' => null,
                'owner_confirmed_by' => $owner?->id,
                'owner_confirmed_at' => now()->subDays(3)->addHours(2),
                'owner_confirm_note' => 'Đồng ý hoàn theo chính sách hủy trước giờ chơi.',
                'admin_confirmed_by' => $admin?->id,
                'admin_confirmed_at' => now()->subDays(3)->addHours(4),
                'processed_by' => $admin?->id,
                'processed_at' => now()->subDays(3)->addHours(4),
                'gateway_refund_txn_id' => 'RFSEED-COMPLETED-001',
                'completed_at' => now()->subDays(3)->addHours(5),
            ],
            [
                'payment_code' => 'PMADMREFREJ1',
                'amount' => 110000,
                'reason' => 'Khách yêu cầu hoàn sau thời hạn được phép.',
                'status' => 'owner_rejected',
                'status_reason' => 'Khách hủy quá sát giờ chơi nên không đủ điều kiện hoàn tiền.',
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
                'policy_id' => $policy?->id,
                'policy_rule_id' => $refundRule?->id,
                'policy_evaluation_log_id' => null,
            ];

            if (Schema::hasColumn('refunds', 'refund_destination')) {
                $values['refund_destination'] = 'bank_account';
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
