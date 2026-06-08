<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RefundStatusHistoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('refund_status_histories') || ! Schema::hasTable('refunds')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();

        $this->seedByPaymentCode('PMADMREF1', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
        ]);

        $this->seedByPaymentCode('PMADMREFPROC1', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
        ]);

        $this->seedByPaymentCode('PMADMREFFAIL1', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin gửi yêu cầu hoàn tiền qua kênh thanh toán.'],
        ]);

        $this->seedByPaymentCode('PMADMREFCOMP1', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin gửi yêu cầu hoàn tiền qua kênh thanh toán.'],
            ['admin_processing', 'completed', $admin?->id, 'admin', 'Cổng thanh toán báo hoàn tiền thành công.'],
        ]);

        $this->seedByPaymentCode('PMADMREFREJ1', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_rejected', $owner?->id, 'owner', 'Khách hủy quá sát giờ chơi nên không đủ điều kiện hoàn tiền.'],
        ]);
    }

    private function seedByPaymentCode(string $paymentCode, array $rows): void
    {
        $payment = Payment::query()->where('payment_code', $paymentCode)->first();
        $refund = $payment
            ? Refund::query()->where('payment_id', $payment->id)->first()
            : null;

        if (! $refund) {
            return;
        }

        foreach ($rows as [$oldStatus, $newStatus, $actorId, $actorType, $reason]) {
            RefundStatusHistory::query()->firstOrCreate(
                [
                    'refund_id' => $refund->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
                [
                    'changed_by' => $actorId,
                    'actor_type' => $actorType,
                    'metadata' => ['source' => 'RefundStatusHistoriesTableSeeder'],
                    'created_at' => now()->subDays(3),
                ],
            );
        }
    }
}
